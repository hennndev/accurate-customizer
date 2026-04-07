<?php

namespace App\Services\Accurate;

use Exception;
use Illuminate\Support\Facades\Log;

class TransactionSaver
{
    protected ModuleFieldProvider $moduleFieldProvider;
    protected DataCleaner $dataCleaner;
    protected DatabaseClientManager $databaseClientManager;
    protected NumberMappingManager $numberMappingManager;
    protected EntityMappingManager $entityMappingManager;

    public function __construct(
        ModuleFieldProvider $moduleFieldProvider,
        DataCleaner $dataCleaner,
        DatabaseClientManager $databaseClientManager,
        NumberMappingManager $numberMappingManager,
        EntityMappingManager $entityMappingManager
    ) {
        $this->moduleFieldProvider = $moduleFieldProvider;
        $this->dataCleaner = $dataCleaner;
        $this->databaseClientManager = $databaseClientManager;
        $this->numberMappingManager = $numberMappingManager;
        $this->entityMappingManager = $entityMappingManager;
    }

    public function bulkSaveToAccurate(string $endpoint, array $data, ?array $targetDbInfo = null, ?string $accessToken = null)
    {
        set_time_limit(0);
        if (
            str_contains($endpoint, 'warehouse') ||
            str_contains($endpoint, 'price-category') ||
            str_contains($endpoint, 'work-order') ||
            str_contains($endpoint, 'bill-of-material')
        ) {
            return $this->saveOneByOne($endpoint, $data, $targetDbInfo, $accessToken);
        }

        preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
        $module = $matches[1] ?? null;
        $accurateDatabaseId = $this->databaseClientManager->getAccurateDatabaseId($targetDbInfo);

        if ($module && $accurateDatabaseId) {
            $numberField = $this->moduleFieldProvider->getNumberFieldForModule($module, $data[0] ?? []);

            if ($numberField) {
                foreach ($data as &$item) {
                    $sourceIdentifier = $item[$numberField] ?? null;

                    if ($sourceIdentifier) {
                        $existingId = \App\Models\AccurateEntityMapping::getAccurateId(
                            $accurateDatabaseId,
                            $module,
                            $sourceIdentifier
                        );

                        if ($existingId) {
                            if ($numberField === 'id') {
                                $item['_sourceId'] = $item['id'];
                            } else {
                                $item['_sourceNumber'] = $item[$numberField] ?? null;
                            }
                            $item['id'] = $existingId;
                            $item['_isUpdate'] = true;
                            if ($numberField !== 'id') {
                                unset($item[$numberField]);
                            }
                        }
                    }
                }
                unset($item);
            }
        }

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
            $isUpdate = isset($item['_isUpdate']) && $item['_isUpdate'] === true;
            return $this->dataCleaner->cleanDataItem($item, $endpoint, $isUpdate);
        }, $data);

        $requestBody = [
            'data' => $cleanedData
        ];
        $response = $client->post($endpoint, $requestBody);
        $responseData = $response->json();

        if (isset($responseData['s']) && $responseData['s'] === true && $module && $accurateDatabaseId) {
            $this->entityMappingManager->storeEntityMappings($module, $data, $responseData, $accurateDatabaseId);
        }

        if ($accurateDatabaseId) {
            $this->numberMappingManager->storeNumberMappings($endpoint, $data, $responseData, $accurateDatabaseId);
        }
        return $responseData;
    }

    protected function saveOneByOne(string $endpoint, array $data, ?array $targetDbInfo = null, ?string $accessToken = null)
    {
        set_time_limit(0);

        preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
        $module = $matches[1] ?? null;

        $accurateDatabaseId = $this->databaseClientManager->getAccurateDatabaseId($targetDbInfo);

        $client = $targetDbInfo
            ? $this->databaseClientManager->getDataClientForDatabase($targetDbInfo, $accessToken)
            : $this->databaseClientManager->getDataClient();

        if ($module && $accurateDatabaseId) {
            $numberField = $this->moduleFieldProvider->getNumberFieldForModule($module, $data[0] ?? []);

            if ($numberField) {
                foreach ($data as &$item) {
                    $sourceIdentifier = $item[$numberField] ?? null;

                    if ($sourceIdentifier) {
                        $existingId = \App\Models\AccurateEntityMapping::getAccurateId(
                            $accurateDatabaseId,
                            $module,
                            $sourceIdentifier
                        );

                        if ($existingId) {
                            if ($numberField === 'id') {
                                $item['_sourceId'] = $item['id'];
                            } else {
                                $item['_sourceNumber'] = $item[$numberField] ?? null;
                            }

                            $item['id'] = $existingId;
                            $item['_isUpdate'] = true;

                            if ($numberField !== 'id') {
                                unset($item[$numberField]);
                            }
                        }
                    }
                }
                unset($item);
            }
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $saveEndpoint = str_replace('bulk-save.do', 'save.do', $endpoint);

        foreach ($data as $index => $item) {
            $isUpdate = isset($item['_isUpdate']) && $item['_isUpdate'] === true;
            $cleanedItem = $this->dataCleaner->cleanDataItem($item, $endpoint, $isUpdate);

            try {
                $response = $client->post($saveEndpoint, $cleanedItem);
                $result = $response->json();
                $results[] = $result;

                if (isset($result['s']) && $result['s'] === true) {
                    $successCount++;

                    if ($module && $accurateDatabaseId) {
                        $numberField = $this->moduleFieldProvider->getNumberFieldForModule($module, $item);

                        if ($numberField === 'id' && isset($item['_sourceId'])) {
                            $sourceIdentifier = $item['_sourceId'];
                        } else {
                            $sourceIdentifier = $item[$numberField] ?? null;
                        }

                        $accurateId = $result['r']['id'] ?? $result['d']['id'] ?? null;
                        $accurateNumber = $sourceIdentifier;

                        if ($sourceIdentifier && $accurateId) {
                            $wasUpdate = isset($item['_isUpdate']) && $item['_isUpdate'] === true;
                            
                            \App\Models\AccurateEntityMapping::storeMapping(
                                $accurateDatabaseId,
                                $module,
                                $sourceIdentifier,
                                $accurateId,
                                $accurateNumber,
                                [
                                    'synced_at' => now()->toIso8601String(),
                                    'endpoint' => $saveEndpoint,
                                    'operation' => $wasUpdate ? 'update' : 'create'
                                ]
                            );

                            $this->entityMappingManager->updateTransactionStatus(
                                $sourceIdentifier,
                                $module,
                                $accurateDatabaseId,
                                $wasUpdate ? \App\Models\Transaction::STATUS_PUSHED_UPDATE : \App\Models\Transaction::STATUS_PUSHED_CREATE
                            );
                        }
                    }
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
}
