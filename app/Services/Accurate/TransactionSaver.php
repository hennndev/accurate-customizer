<?php

namespace App\Services\Accurate;

use Exception;
use Illuminate\Support\Facades\Log;

class TransactionSaver
{
    protected DataCleaner $dataCleaner;
    protected DatabaseClientManager $databaseClientManager;

    public function __construct(
        DataCleaner $dataCleaner,
        DatabaseClientManager $databaseClientManager
    ) {
        $this->dataCleaner = $dataCleaner;
        $this->databaseClientManager = $databaseClientManager;
    }

    public function bulkSaveToAccurate(string $endpoint, array $data, ?array $targetDbInfo = null, ?string $accessToken = null, bool $forceCreate = false)
    {
        set_time_limit(0);
        if (
            str_contains($endpoint, 'warehouse') ||
            str_contains($endpoint, 'price-category') ||
            str_contains($endpoint, 'work-order') ||
            str_contains($endpoint, 'bill-of-material')
        ) {
            return $this->saveOneByOne($endpoint, $data, $targetDbInfo, $accessToken, $forceCreate);
        }

        preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
        $module = $matches[1] ?? null;
        $accurateDatabaseId = $this->databaseClientManager->getAccurateDatabaseId($targetDbInfo);

        $client = $targetDbInfo
            ? $this->databaseClientManager->getDataClientForDatabase($targetDbInfo, $accessToken)
            : $this->databaseClientManager->getDataClient();

        if (str_contains($endpoint, '/tax/')) {
            $data = array_map(function ($item) use ($client) {
                $salesTaxGlAccountId = $item['salesTaxGlAccountId'] ?? null;
                $purchaseTaxGlAccountId = $item['purchaseTaxGlAccountId'] ?? null;

                unset($item['salesTaxGlAccountId']);
                unset($item['purchaseTaxGlAccountId']);

                $taxType = $item['taxType'] ?? '';
                $salesAccountNo = null;
                $purchaseAccountNo = null;

                if ($salesTaxGlAccountId !== null) {
                    try {
                        $response = $client->get('/api/glaccount/detail.do', [
                            'id' => $salesTaxGlAccountId
                        ]);
                        if ($response->successful() && isset($response->json()['d']['no'])) {
                            $salesAccountNo = $response->json()['d']['no'];
                        }
                    } catch (\Exception $e) {
                    }
                }

                if ($purchaseTaxGlAccountId !== null) {
                    try {
                        $response = $client->get('/api/glaccount/detail.do', [
                            'id' => $purchaseTaxGlAccountId
                        ]);
                        if ($response->successful() && isset($response->json()['d']['no'])) {
                            $purchaseAccountNo = $response->json()['d']['no'];
                        }
                    } catch (\Exception $e) {
                    }
                }
                $item['salesTaxGlAccountNo'] = $salesAccountNo;
                $item['purchaseTaxGlAccountNo'] = $purchaseAccountNo;
                return $item;
            }, $data);
        }

        $cleanedData = array_map(function ($item) use ($endpoint) {
            return $this->dataCleaner->cleanDataItem($item, $endpoint);
        }, $data);
        $requestBody = [
            'data' => $cleanedData
        ];

        Log::info('SAVING_TO_ACCURATE', [
            'data' => $requestBody
        ]);
        $response = $client->post($endpoint, $requestBody);

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->body();

            if ($this->isTokenInvalidResponse($status, $body)) {
                throw new Exception('ACCURATE_TOKEN_INVALID: Sesi Accurate habis atau token tidak valid. Silakan login Accurate ulang.');
            }

            throw new Exception('Failed to save data to Accurate');
        }

        $responseData = $response->json();

        return $responseData;
    }

    protected function saveOneByOne(string $endpoint, array $data, ?array $targetDbInfo = null, ?string $accessToken = null, bool $forceCreate = false)
    {
        set_time_limit(0);

        $accurateDatabaseId = $this->databaseClientManager->getAccurateDatabaseId($targetDbInfo);

        $client = $targetDbInfo
            ? $this->databaseClientManager->getDataClientForDatabase($targetDbInfo, $accessToken)
            : $this->databaseClientManager->getDataClient();

        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $saveEndpoint = str_replace('bulk-save.do', 'save.do', $endpoint);

        foreach ($data as $index => $item) {
            $cleanedItem = $this->dataCleaner->cleanDataItem($item, $endpoint);

            try {
                $response = $client->post($saveEndpoint, $cleanedItem);
                if ($response->failed()) {
                    $status = $response->status();
                    $body = $response->body();

                    Log::error('Failed to save one-by-one to Accurate API', [
                        'endpoint' => $saveEndpoint,
                        'status' => $status,
                        'error' => $body,
                    ]);

                    if ($this->isTokenInvalidResponse($status, $body)) {
                        throw new Exception('ACCURATE_TOKEN_INVALID: Sesi Accurate habis atau token tidak valid. Silakan login Accurate ulang.');
                    }

                    throw new Exception('Failed to save data to Accurate');
                }

                $result = $response->json();
                $results[] = $result;

                if (isset($result['s']) && $result['s'] === true) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            } catch (\Exception $e) {
                $results[] = [
                    's' => false,
                    'd' => $e->getMessage()
                ];
                $failedCount++;
            }
        }

        return [
            's' => $failedCount === 0,
            'd' => $results,
            'total' => count($data),
            'success' => $successCount,
            'failed' => $failedCount
        ];
    }

    private function isTokenInvalidResponse(int $status, string $body): bool
    {
        if (in_array($status, [401, 403], true)) {
            return true;
        }

        $normalizedBody = strtolower($body);

        return str_contains($normalizedBody, 'invalid_token')
            || str_contains($normalizedBody, 'token invalid')
            || str_contains($normalizedBody, 'invalid token');
    }

    private function isMasterDataModule(string $endpoint): bool
    {
        $masterDataModules = [
            'customer', 'vendor', 'item', 'branch', 'department', 'employee', 'warehouse', 'project',
            'customer-category', 'vendor-category', 'item-category', 'price-category', 'data-classification',
            'vendor-price', 'glaccount', 'currency', 'tax', 'unit', 'fob', 'bill-of-material'
        ];

        foreach ($masterDataModules as $module) {
            if (str_contains($endpoint, '/' . $module . '/')) {
                return true;
            }
        }

        return false;
    }
}
