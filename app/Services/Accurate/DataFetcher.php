<?php

namespace App\Services\Accurate;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DataFetcher
{
    protected EndpointFieldProvider $endpointFieldProvider;
    protected DatabaseClientManager $databaseClientManager;

    public function __construct(
        EndpointFieldProvider $endpointFieldProvider,
        DatabaseClientManager $databaseClientManager
    ) {
        $this->endpointFieldProvider = $endpointFieldProvider;
        $this->databaseClientManager = $databaseClientManager;
    }

    public function fetchModuleDataPage(
        string $endpoint,
        array $params = [],
        int $pageNumber = 1,
        int $pageSize = 50,
        ?array $targetDbInfo = null,
        ?string $accessToken = null
    ): array {
        $fields = $this->endpointFieldProvider->getFieldsForEndpoint($endpoint);
        if ($fields && !isset($params['fields']) && !isset($params['sp.fields'])) {
            $params['fields'] = $fields;
            $params['sp.fields'] = $fields;
        }

        $params['sp.pageSize'] = $pageSize;
        $params['sp.page'] = $pageNumber;
        if (!array_key_exists('filter.invoiceDp', $params)) {
            $params['filter.invoiceDp'] = false;
        }

        $params = $this->normalizeAccurateParams($params);


        $client = $targetDbInfo
            ? $this->databaseClientManager->getDataClientForDatabase($targetDbInfo, $accessToken, 600)
            : $this->databaseClientManager->getDataClient(600);

        $response = $this->sendGetWithRetry(
            $client,
            $endpoint,
            $params,
            [
                'endpoint' => $endpoint,
                'mode' => 'page',
                'page' => $pageNumber,
            ]
        );
        if ($response->failed()) {
            Log::error('Failed to fetch page (no-merge mode) from Accurate', [
                'endpoint' => $endpoint,
                'page' => $pageNumber,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);
            if ($this->isTokenInvalidResponse($response->status(), $response->body())) {
                throw new Exception('ACCURATE_TOKEN_INVALID: Sesi Accurate habis atau token tidak valid. Silakan login Accurate ulang.');
            }

            throw new Exception('Failed to fetch module page data from Accurate');
        }

        $responseData = $response->json();
        $pageData = $responseData['d'] ?? [];

        return [
            'data' => $pageData,
            'has_more' => count($pageData) === $pageSize,
            'page' => $pageNumber,
            'count' => count($pageData),
        ];
    }

    public function fetchModuleData(
        string $endpoint,
        array $params = [],
        ?array $targetDbInfo = null,
        ?string $accessToken = null
    ): array {
        try {
            $isDetailEndpoint = str_contains($endpoint, '/detail.do');
            $timeoutSeconds = $isDetailEndpoint ? (int) env('ACCURATE_DETAIL_TIMEOUT_SECONDS', 120) : 600;

            $client = $targetDbInfo
                ? $this->databaseClientManager->getDataClientForDatabase($targetDbInfo, $accessToken, $timeoutSeconds)
                : $this->databaseClientManager->getDataClient($timeoutSeconds);

            if ($isDetailEndpoint) {
                Log::debug('Fetching Accurate detail', [
                    'endpoint' => $endpoint,
                    'timeout_seconds' => $timeoutSeconds,
                    'params' => $params,
                ]);

                $response = $this->sendGetWithRetry(
                    $client,
                    $endpoint,
                    $params,
                    [
                        'endpoint' => $endpoint,
                        'mode' => 'detail',
                    ]
                );

                if ($response->failed()) {
                    Log::error('Failed to fetch detail from Accurate API', [
                        'endpoint' => $endpoint,
                        'timeout_seconds' => $timeoutSeconds,
                        'status' => $response->status(),
                        'error' => $response->body(),
                    ]);
                    if ($this->isTokenInvalidResponse($response->status(), $response->body())) {
                        throw new Exception('ACCURATE_TOKEN_INVALID: Sesi Accurate habis atau token tidak valid. Silakan login Accurate ulang.');
                    }

                    throw new Exception('Failed to fetch module detail data from Accurate');
                }

                return $response->json()['d'] ?? [];
            }

            $allData = [];
            $pageNumber = 1;
            $pageSize = 50;

            $fields = $this->endpointFieldProvider->getFieldsForEndpoint($endpoint);
            if ($fields && !isset($params['fields']) && !isset($params['sp.fields'])) {
                $params['fields'] = $fields;
                $params['sp.fields'] = $fields;
            }

            $fieldsCount = $fields ? count(explode(',', $fields)) : 0;
            Log::info("Starting fetch from Accurate API", [
                'endpoint' => $endpoint,
                'fields_count' => $fieldsCount,
                'page_size' => $pageSize,
                'has_date_filter' => isset($params['filter.transDate.op']) || isset($params['filter.lastUpdate.op']),
            ]);

            $params['sp.pageSize'] = $pageSize;
            if (!array_key_exists('filter.invoiceDp', $params)) {
                $params['filter.invoiceDp'] = false;
            }

            $params = $this->normalizeAccurateParams($params);

            do {
                $params['sp.page'] = $pageNumber;
                Log::debug("Fetching page from Accurate", [
                    'endpoint' => $endpoint,
                    'page' => $pageNumber,
                    'page_size' => $pageSize,
                ]);

                $response = $this->sendGetWithRetry(
                    $client,
                    $endpoint,
                    $params,
                    [
                        'endpoint' => $endpoint,
                        'mode' => 'list',
                        'page' => $pageNumber,
                    ]
                );

                if ($response->failed()) {
                    Log::error("Failed to fetch from Accurate API", [
                        'endpoint' => $endpoint,
                        'page' => $pageNumber,
                        'status' => $response->status(),
                        'error' => $response->body(),
                    ]);
                    throw new Exception('Failed to fetch module data from Accurate');
                }

                $responseData = $response->json();
                $pageData = $responseData['d'] ?? [];

                Log::info("Page fetched successfully", [
                    'endpoint' => $endpoint,
                    'page' => $pageNumber,
                    'records_in_page' => count($pageData),
                    'total_records_so_far' => count($allData) + count($pageData),
                ]);

                $allData = array_merge($allData, $pageData);
                $hasMoreData = count($pageData) === $pageSize;

                $pageNumber++;
            } while ($hasMoreData);

            Log::info("Fetch completed from Accurate API", [
                'endpoint' => $endpoint,
                'total_records' => count($allData),
                'total_pages' => $pageNumber - 1,
            ]);

            return $allData;
        } catch (\Exception $e) {
            Log::error("Exception during fetchModuleData", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function sendGetWithRetry(PendingRequest $client, string $endpoint, array $params, array $context = []): Response
    {
        $maxRetries = (int) env('ACCURATE_API_MAX_RETRIES', 5);
        if ($maxRetries < 0) {
            $maxRetries = 0;
        }

        $baseDelayMs = (int) env('ACCURATE_API_RETRY_BASE_DELAY_MS', 300);
        if ($baseDelayMs < 50) {
            $baseDelayMs = 50;
        }

        $throttleIntervalMs = (int) env('ACCURATE_API_THROTTLE_INTERVAL_MS', 180);
        if ($throttleIntervalMs < 0) {
            $throttleIntervalMs = 0;
        }

        $attempt = 0;

        do {
            $this->applyGlobalThrottle($throttleIntervalMs);

            $response = $client->get($endpoint, $params);
            if (!$response->failed()) {
                return $response;
            }

            $status = $response->status();
            $isRetryable = in_array($status, [408, 429, 500, 502, 503, 504], true);

            if (!$isRetryable || $attempt >= $maxRetries) {
                return $response;
            }

            $retryAfterHeader = $response->header('Retry-After');
            $retryAfterMs = is_numeric($retryAfterHeader) ? ((int) $retryAfterHeader * 1000) : 0;

            $backoffMs = $retryAfterMs > 0
                ? $retryAfterMs
                : (int) ($baseDelayMs * (2 ** $attempt));

            $jitterMs = random_int(50, 200);
            $sleepMs = min(5000, $backoffMs + $jitterMs);

            Log::warning('Accurate API retry scheduled', array_merge($context, [
                'status' => $status,
                'attempt' => $attempt + 1,
                'max_retries' => $maxRetries,
                'sleep_ms' => $sleepMs,
            ]));

            usleep($sleepMs * 1000);
            $attempt++;
        } while (true);
    }

    private function applyGlobalThrottle(int $intervalMs): void
    {
        if ($intervalMs <= 0) {
            return;
        }

        $token = session('accurate_access_token');
        if (!$token) {
            usleep($intervalMs * 1000);
            return;
        }

        $tokenHash = sha1($token);
        $lockKey = 'accurate:throttle:lock:' . $tokenHash;
        $lastRequestKey = 'accurate:throttle:last:' . $tokenHash;

        Cache::lock($lockKey, 5)->block(5, function () use ($intervalMs, $lastRequestKey) {
            $nowMicro = (int) round(microtime(true) * 1000000);
            $lastMicro = (int) Cache::get($lastRequestKey, 0);
            $minGapMicro = $intervalMs * 1000;

            if ($lastMicro > 0) {
                $elapsedMicro = $nowMicro - $lastMicro;
                if ($elapsedMicro < $minGapMicro) {
                    usleep($minGapMicro - $elapsedMicro);
                }
            }

            Cache::put($lastRequestKey, (int) round(microtime(true) * 1000000), now()->addMinutes(10));
        });
    }

    private function isTokenInvalidResponse(int $status, string $body): bool
    {
        \Illuminate\Support\Facades\Log::info("DEBUG_ACCURATE_RESPONSE", ['status' => $status, 'body' => $body]);
        
        if (in_array($status, [401, 403], true)) {
            return true;
        }

        $normalizedBody = strtolower($body);

        return str_contains($normalizedBody, 'invalid_token')
            || str_contains($normalizedBody, 'token invalid')
            || str_contains($normalizedBody, 'invalid token');
    }

    private function normalizeAccurateParams(array $params): array
    {
        if (!array_key_exists('filter.invoiceDp', $params)) {
            return $params;
        }

        $value = $params['filter.invoiceDp'];
        if (is_bool($value)) {
            $params['filter.invoiceDp'] = $value ? 'true' : 'false';
            return $params;
        }

        if (is_int($value)) {
            $params['filter.invoiceDp'] = $value === 1 ? 'true' : 'false';
            return $params;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', '1', 'yes', 'on'], true)) {
                $params['filter.invoiceDp'] = 'true';
            } elseif (in_array($normalized, ['false', '0', 'no', 'off', ''], true)) {
                $params['filter.invoiceDp'] = 'false';
            }
        }

        return $params;
    }
}
