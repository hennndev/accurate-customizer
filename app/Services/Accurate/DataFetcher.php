<?php

namespace App\Services\Accurate;

use Exception;
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

    public function fetchModuleDataPage(string $endpoint, array $params = [], int $pageNumber = 1, int $pageSize = 50): array
    {
        $fields = $this->endpointFieldProvider->getFieldsForEndpoint($endpoint);
        if ($fields && !isset($params['fields']) && !isset($params['sp.fields'])) {
            $params['fields'] = $fields;
            $params['sp.fields'] = $fields;
        }

        $params['sp.pageSize'] = $pageSize;
        $params['sp.page'] = $pageNumber;
        $params['filter.invoiceDp'] = false;

        Log::debug('Fetching page (no-merge mode) from Accurate', [
            'endpoint' => $endpoint,
            'page' => $pageNumber,
            'page_size' => $pageSize,
        ]);

        $response = $this->databaseClientManager->getDataClient()->get($endpoint, $params);
        if ($response->failed()) {
            Log::error('Failed to fetch page (no-merge mode) from Accurate', [
                'endpoint' => $endpoint,
                'page' => $pageNumber,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);
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

    public function fetchModuleData(string $endpoint, array $params = []): array
    {
        try {
            if (str_contains($endpoint, '/detail.do')) {
                $response = $this->databaseClientManager->getDataClient()->get($endpoint, $params);

                if ($response->failed()) {
                    Log::error('Failed to fetch detail from Accurate API', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'error' => $response->body(),
                    ]);
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
            $params['filter.invoiceDp'] = false;

            do {
                $params['sp.page'] = $pageNumber;
                Log::debug("Fetching page from Accurate", [
                    'endpoint' => $endpoint,
                    'page' => $pageNumber,
                    'page_size' => $pageSize,
                ]);
                
                $response = $this->databaseClientManager->getDataClient()->get($endpoint, $params);

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
}
