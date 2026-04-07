<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Modules\ModuleManager;
use App\Services\Accurate\EndpointFieldProvider;
use App\Services\AccurateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CaptureModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public string $module,
        public array $moduleInfo,
        public array $params,
        public int $pageSize,
        public int $startPage,
        public int $databaseId,
        public string $databaseName,
        public ?int $userId,
        public int $trackerLogId,
        public ?string $accessToken,
        public ?array $sourceDbInfo
    ) {
    }

    public function handle(AccurateService $accurate, EndpointFieldProvider $endpointFieldProvider): void
    {
        if ($this->accessToken && $this->sourceDbInfo) {
            session([
                'accurate_access_token' => $this->accessToken,
                'accurate_database' => $this->sourceDbInfo,
                'database_id' => $this->sourceDbInfo['id'] ?? null,
            ]);
        }

        $this->updateTracker('running', 'Capture started', [
            'progress' => 0,
            'saved_count' => 0,
            'failed_count' => 0,
            'processed_pages' => 0,
            'processed_items' => 0,
        ]);

        $savedCount = 0;
        $failedCount = 0;
        $processedPages = 0;
        $processedItems = 0;
        $savedTransactionNumbers = [];

        $moduleRecord = Module::firstOrCreate(
            [
                'accurate_database_id' => $this->databaseId,
                'slug' => $this->module,
            ],
            [
                'name' => $this->moduleInfo['name'],
                'icon' => 'heroicon-o-document-text',
                'description' => $this->moduleInfo['name'],
                'accurate_endpoint' => $this->moduleInfo['list_endpoint'],
                'is_active' => false,
                'order' => 0,
            ]
        );

        $listOnlyMode = $endpointFieldProvider->getFieldsForEndpoint($this->moduleInfo['list_endpoint']) !== null;
        $handler = ModuleManager::forSlug($this->module);
        $sharedContext = [];

        if (!$listOnlyMode) {
            $handler->preCapture($accurate, $sharedContext);
        }

        $currentPage = $this->startPage;
        $batchSize = 100;
        $transactionsToInsert = [];

        while (true) {
            $pageResult = $accurate->fetchModuleDataPage(
                $this->moduleInfo['list_endpoint'],
                $this->params,
                $currentPage,
                $this->pageSize,
                $this->sourceDbInfo,
                $this->accessToken
            );

            $pageData = $pageResult['data'] ?? [];
            if (empty($pageData)) {
                break;
            }

            $processedPages++;
            $processedItems += count($pageData);
            $moduleRecord->is_active = true;
            $moduleRecord->save();

            foreach ($pageData as $index => $item) {
                try {
                    $itemId = $item['id'] ?? null;
                    $detailData = $item;

                    if (!$listOnlyMode) {
                        $detailParams = ['id' => $itemId];
                        $detailDataRaw = $accurate->fetchModuleData(
                            $this->moduleInfo['detail_endpoint'],
                            $detailParams,
                            $this->sourceDbInfo,
                            $this->accessToken
                        );

                        if (is_array($detailDataRaw) && isset($detailDataRaw[0]) && is_array($detailDataRaw[0])) {
                            $detailData = $detailDataRaw[0];
                        } else {
                            $detailData = $detailDataRaw;
                        }
                    }

                    if (empty($detailData) || !is_array($detailData)) {
                        $failedCount++;
                        continue;
                    }

                    $identifierField = $this->moduleInfo['identifier_field'] ?? 'number';
                    $transactionNo = $detailData[$identifierField] ?? $item[$identifierField] ?? "ID-{$itemId}";

                    if (!$listOnlyMode) {
                        $handler->transformDetail($detailData, $sharedContext, ['itemId' => $itemId, 'module' => $this->module]);
                    }

                    $transactionsToInsert[] = [
                        'transaction_no' => $transactionNo,
                        'accurate_database_id' => $this->databaseId,
                        'module_id' => $moduleRecord->id,
                        'data' => json_encode($detailData),
                        'description' => $this->moduleInfo['name'],
                        'captured_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $savedTransactionNumbers[] = $transactionNo;

                    if (count($transactionsToInsert) >= $batchSize) {
                        Transaction::insert($transactionsToInsert);
                        $savedCount += count($transactionsToInsert);
                        $transactionsToInsert = [];
                    }
                } catch (\Exception $exception) {
                    Log::error('Capture item failed', [
                        'module' => $this->module,
                        'page' => $currentPage,
                        'index' => $index,
                        'item_id' => $item['id'] ?? null,
                        'error' => $exception->getMessage(),
                    ]);
                    $failedCount++;
                }
            }

            $currentPage++;

            $this->updateTracker('running', 'Capture in progress', [
                'progress' => min(95, 10 + ($processedPages * 5)),
                'saved_count' => $savedCount,
                'failed_count' => $failedCount,
                'processed_pages' => $processedPages,
                'processed_items' => $processedItems,
                'next_page' => $currentPage,
            ]);

            if (count($pageData) < $this->pageSize) {
                break;
            }
        }

        if (!empty($transactionsToInsert)) {
            Transaction::insert($transactionsToInsert);
            $savedCount += count($transactionsToInsert);
        }

        $finalStatus = $failedCount > 0 ? 'warning' : ($savedCount > 0 ? 'success' : 'info');

        SystemLog::create([
            'event_type' => 'capture',
            'module' => $this->moduleInfo['name'],
            'transaction_id' => null,
            'status' => $finalStatus,
            'payload' => [
                'module_slug' => $this->module,
                'database_id' => $this->databaseId,
                'database_name' => $this->databaseName,
                'list_only_mode' => $listOnlyMode,
                'list_endpoint' => $this->moduleInfo['list_endpoint'],
                'detail_endpoint' => $this->moduleInfo['detail_endpoint'],
                'start_page' => $this->startPage,
                'total_pages_processed' => $processedPages,
                'total_items' => $processedItems,
                'saved_count' => $savedCount,
                'failed_count' => $failedCount,
                'next_page' => $currentPage,
                'transaction_numbers' => $savedTransactionNumbers,
            ],
            'message' => "Capture {$this->moduleInfo['name']}: {$savedCount} saved" . ($failedCount > 0 ? ", {$failedCount} failed" : ''),
            'user_id' => $this->userId,
        ]);

        $this->updateTracker($finalStatus, 'Capture completed', [
            'progress' => 100,
            'saved_count' => $savedCount,
            'failed_count' => $failedCount,
            'processed_pages' => $processedPages,
            'processed_items' => $processedItems,
            'next_page' => $currentPage,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateTracker('failed', 'Capture failed: ' . $exception->getMessage(), [
            'progress' => 100,
            'error' => $exception->getMessage(),
        ]);
    }

    private function updateTracker(string $status, string $message, array $payload): void
    {
        $log = SystemLog::find($this->trackerLogId);
        if (!$log) {
            return;
        }

        $existingPayload = is_array($log->payload) ? $log->payload : [];

        $log->update([
            'status' => $status,
            'message' => $message,
            'payload' => array_merge($existingPayload, $payload),
        ]);
    }
}
