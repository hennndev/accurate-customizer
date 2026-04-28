<?php

namespace App\Jobs;

use App\Models\CaptureListItemId;
use App\Models\Module;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Modules\ModuleManager;
use App\Services\Accurate\EndpointFieldProvider;
use App\Services\AccurateService;
use Illuminate\Database\QueryException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        public ?array $sourceDbInfo,
        public ?string $cancelToken = null,
        public string $captureMode = 'list_and_detail',
        public bool $useListIdCache = true,
        public bool $refreshListIdCache = false,
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

        $existingTracker = SystemLog::find($this->trackerLogId);
        $existingPayload = is_array($existingTracker?->payload) ? $existingTracker->payload : [];

        $savedCount = (int) ($existingPayload['saved_count'] ?? 0);
        $failedCount = (int) ($existingPayload['failed_count'] ?? 0);
        $skippedDuplicateCount = (int) ($existingPayload['skipped_duplicate_count'] ?? 0);
        $processedPages = (int) ($existingPayload['processed_pages'] ?? 0);
        $processedItems = (int) ($existingPayload['processed_items'] ?? 0);

        $this->updateTracker('running', 'Capture started', [
            'progress' => (int) ($existingPayload['progress'] ?? 0),
            'saved_count' => $savedCount,
            'failed_count' => $failedCount,
            'skipped_duplicate_count' => $skippedDuplicateCount,
            'processed_pages' => $processedPages,
            'processed_items' => $processedItems,
        ]);

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

        $detailOnlyMode = $this->captureMode === 'detail_only';
        $listOnlyCaptureMode = $this->captureMode === 'list_only';
        $listAndDetailMode = $this->captureMode === 'list_and_detail';
        $shouldRunDetailCapture = $listAndDetailMode || $detailOnlyMode;
        $handler = ModuleManager::forSlug($this->module);
        $sharedContext = [];

        if ($this->isCancelled()) {
            $this->updateTracker('failed', 'Capture dibatalkan sebelum mulai', [
                'progress' => 100,
                'cancelled' => true,
            ]);
            return;
        }

        if (!$listOnlyCaptureMode && !$detailOnlyMode) {
            $handler->preCapture($accurate, $sharedContext);
        }

        $currentPage = $this->startPage;
        $pageFetchFailures = 0;
        $batchSize = (int) env('ACCURATE_CAPTURE_INSERT_BATCH', 20);
        if ($batchSize < 1) {
            $batchSize = 1;
        }
        if ($batchSize > 100) {
            $batchSize = 100;
        }
        $transactionsToInsert = [];
        $detailCandidates = [];

        $isGoneAwayError = static function (\Throwable $exception): bool {
            $message = strtolower($exception->getMessage());
            return str_contains($message, 'server has gone away') || str_contains($message, '2006');
        };

        $flushInsertBatch = function () use (&$transactionsToInsert, &$savedCount, &$skippedDuplicateCount, &$savedTransactionNumbers, $moduleRecord, $isGoneAwayError): void {
            if (empty($transactionsToInsert)) {
                return;
            }

            $dedupedByNumber = [];
            foreach ($transactionsToInsert as $row) {
                $number = $row['transaction_no'] ?? null;
                if ($number && !isset($dedupedByNumber[$number])) {
                    $dedupedByNumber[$number] = $row;
                }
            }

            $candidateRows = array_values($dedupedByNumber);
            $candidateNumbers = array_column($candidateRows, 'transaction_no');
            if (empty($candidateNumbers)) {
                $transactionsToInsert = [];
                return;
            }

            try {
                $existingNumbers = Transaction::query()
                    ->whereIn('transaction_no', $candidateNumbers)
                    ->pluck('transaction_no')
                    ->all();
            } catch (QueryException $exception) {
                if (!$isGoneAwayError($exception)) {
                    throw $exception;
                }

                DB::purge();
                DB::reconnect();

                $existingNumbers = Transaction::query()
                    ->whereIn('transaction_no', $candidateNumbers)
                    ->pluck('transaction_no')
                    ->all();
            }

            $existingMap = array_flip($existingNumbers);
            $rowsToInsert = [];

            foreach ($candidateRows as $row) {
                $number = $row['transaction_no'];
                if (isset($existingMap[$number])) {
                    $skippedDuplicateCount++;
                    continue;
                }
                $rowsToInsert[] = $row;
            }

            if (!empty($rowsToInsert)) {
                try {
                    $insertedCount = Transaction::query()->insertOrIgnore($rowsToInsert);
                } catch (QueryException $exception) {
                    if (!$isGoneAwayError($exception)) {
                        throw $exception;
                    }

                    DB::purge();
                    DB::reconnect();
                    $insertedCount = Transaction::query()->insertOrIgnore($rowsToInsert);
                }

                $savedCount += (int) $insertedCount;
                $skippedDuplicateCount += max(0, count($rowsToInsert) - (int) $insertedCount);

                if ((int) $insertedCount > 0) {
                    $savedTransactionNumbers = array_merge($savedTransactionNumbers, array_column($rowsToInsert, 'transaction_no'));
                }
            }

            $transactionsToInsert = [];
        };

        $listParams = $this->params;

        if (in_array($this->module, ['sales-invoice', 'purchase-invoice'], true)) {
            $listParams['filter.invoiceDp'] = false;
        }

        if ($shouldRunDetailCapture) {
            $listParams['fields'] = 'id';
            $listParams['sp.fields'] = 'id';
        }

        $globalUseListIdCache = filter_var(
            (string) env('ACCURATE_CAPTURE_USE_LIST_ID_CACHE', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );
        $cacheFeatureEnabled = $this->useListIdCache && $globalUseListIdCache;
        $useListIdCache = (!$listOnlyCaptureMode || $detailOnlyMode) && $cacheFeatureEnabled;
        $shouldLoadFromCache = $detailOnlyMode;
        $shouldPersistListIds = $cacheFeatureEnabled && !$detailOnlyMode;
        $loadedCandidatesFromCache = false;
        $listParamsHash = null;
        $listIdCacheRows = [];

        $flushListIdCacheBatch = function () use (&$listIdCacheRows, $shouldPersistListIds, &$listParamsHash): void {
            if (!$shouldPersistListIds || empty($listIdCacheRows) || !$listParamsHash) {
                return;
            }

            CaptureListItemId::upsert(
                $listIdCacheRows,
                ['accurate_database_id', 'module_slug', 'params_hash', 'list_item_id'],
                ['fallback_number', 'captured_from_list_at', 'updated_at']
            );

            $listIdCacheRows = [];
        };

        if ($cacheFeatureEnabled) {
            $hashParams = $listParams;
            unset($hashParams['fields'], $hashParams['sp.fields']);
            $listParamsHash = $this->buildParamsHash($hashParams);

            if ($this->refreshListIdCache && !$detailOnlyMode) {
                CaptureListItemId::query()
                    ->where('accurate_database_id', $this->databaseId)
                    ->where('module_slug', $this->module)
                    ->where('params_hash', $listParamsHash)
                    ->delete();
            }

            if ($shouldLoadFromCache) {
                $cachedRows = CaptureListItemId::query()
                    ->where('accurate_database_id', $this->databaseId)
                    ->where('module_slug', $this->module)
                    ->where('params_hash', $listParamsHash)
                    ->orderBy('id')
                    ->get(['list_item_id', 'fallback_number']);

                if ($cachedRows->isNotEmpty()) {
                    $detailCandidates = $cachedRows
                        ->map(static fn (CaptureListItemId $item): array => [
                            'id' => $item->list_item_id,
                            'fallback_number' => $item->fallback_number,
                        ])
                        ->all();

                    $loadedCandidatesFromCache = true;

                    $this->updateTracker('running', 'Capture memakai cache list ID', [
                        'progress' => 15,
                        'saved_count' => $savedCount,
                        'failed_count' => $failedCount,
                        'skipped_duplicate_count' => $skippedDuplicateCount,
                        'processed_pages' => $processedPages,
                        'processed_items' => $processedItems,
                        'list_total_ids' => count($detailCandidates),
                        'used_table_list_ids' => true,
                    ]);
                } else {
                    $this->updateTracker('failed', 'Data list belum tersedia di tabel. Jalankan Capture List terlebih dahulu.', [
                        'progress' => 100,
                        'capture_mode' => $this->captureMode,
                        'used_table_list_ids' => false,
                    ]);
                    return;
                }
            }
        }

        while (!$loadedCandidatesFromCache && !$detailOnlyMode) {
            if ($this->isCancelled()) {
                $this->updateTracker('failed', 'Capture dibatalkan', [
                    'progress' => 100,
                    'cancelled' => true,
                    'next_page' => $currentPage,
                ]);
                return;
            }

            try {
                $pageResult = $accurate->fetchModuleDataPage(
                    $this->moduleInfo['list_endpoint'],
                    $listParams,
                    $currentPage,
                    $this->pageSize,
                    $this->sourceDbInfo,
                    $this->accessToken
                );
            } catch (\Throwable $exception) {
                $pageFetchFailures++;
                Log::error('Capture page failed', [
                    'module' => $this->module,
                    'page' => $currentPage,
                    'error' => $exception->getMessage(),
                ]);

                $failedCount++;
                $this->updateTracker('running', 'Capture page failed, continuing', [
                    'progress' => min(95, 10 + ($processedPages * 5)),
                    'saved_count' => $savedCount,
                    'failed_count' => $failedCount,
                    'skipped_duplicate_count' => $skippedDuplicateCount,
                    'processed_pages' => $processedPages,
                    'processed_items' => $processedItems,
                    'next_page' => $currentPage + 1,
                    'page_fetch_failures' => $pageFetchFailures,
                ]);

                $currentPage++;
                continue;
            }

            $pageData = $pageResult['data'] ?? [];
            if (empty($pageData)) {
                break;
            }

            $processedPages++;
            $processedItems += count($pageData);
            $moduleRecord->is_active = true;
            $moduleRecord->save();

            foreach ($pageData as $item) {
                if (!$shouldRunDetailCapture) {
                    $itemId = $item['id'] ?? null;
                    $identifierField = $this->moduleInfo['identifier_field'] ?? 'number';

                    if ($shouldPersistListIds && $itemId !== null && $listParamsHash) {
                        $listIdCacheRows[] = [
                            'accurate_database_id' => $this->databaseId,
                            'module_slug' => $this->module,
                            'params_hash' => $listParamsHash,
                            'list_item_id' => $itemId,
                            'fallback_number' => $item[$identifierField] ?? null,
                            'captured_from_list_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        if (count($listIdCacheRows) >= 200) {
                            $flushListIdCacheBatch();
                        }
                    }

                    continue;
                }

                $itemId = $item['id'] ?? null;
                if ($itemId === null) {
                    $failedCount++;
                    continue;
                }

                $identifierField = $this->moduleInfo['identifier_field'] ?? 'number';
                $detailCandidates[] = [
                    'id' => $itemId,
                    'fallback_number' => $item[$identifierField] ?? null,
                ];

                if ($shouldPersistListIds && $listParamsHash) {
                    $listIdCacheRows[] = [
                        'accurate_database_id' => $this->databaseId,
                        'module_slug' => $this->module,
                        'params_hash' => $listParamsHash,
                        'list_item_id' => $itemId,
                        'fallback_number' => $item[$identifierField] ?? null,
                        'captured_from_list_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($listIdCacheRows) >= 200) {
                        $flushListIdCacheBatch();
                    }
                }
            }

            $nextPage = $currentPage + 1;

            $this->updateTracker('running', $shouldRunDetailCapture ? 'Capture list in progress' : 'Capture in progress', [
                'progress' => min(95, 10 + ($processedPages * 5)),
                'saved_count' => $savedCount,
                'failed_count' => $failedCount,
                'skipped_duplicate_count' => $skippedDuplicateCount,
                'processed_pages' => $processedPages,
                'processed_items' => $processedItems,
                'next_page' => $nextPage,
                'page_fetch_failures' => $pageFetchFailures,
                'list_total_ids' => count($detailCandidates),
            ]);

            if (count($pageData) < $this->pageSize) {
                $currentPage = $nextPage;
                break;
            }

            $currentPage = $nextPage;
        }

        $flushListIdCacheBatch();

        if ($listOnlyCaptureMode) {
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
                    'capture_mode' => $this->captureMode,
                    'list_only_mode' => true,
                    'list_endpoint' => $this->moduleInfo['list_endpoint'],
                    'detail_endpoint' => $this->moduleInfo['detail_endpoint'],
                    'start_page' => $this->startPage,
                    'total_pages_processed' => $processedPages,
                    'total_items' => $processedItems,
                    'saved_count' => $savedCount,
                    'failed_count' => $failedCount,
                    'skipped_duplicate_count' => $skippedDuplicateCount,
                    'next_page' => $currentPage,
                    'transaction_numbers' => $savedTransactionNumbers,
                    'list_id_cache_enabled' => $useListIdCache,
                    'used_cached_list_ids' => $loadedCandidatesFromCache,
                    'list_params_hash' => $listParamsHash,
                ],
                'message' => "Capture {$this->moduleInfo['name']}: {$savedCount} saved" . ($failedCount > 0 ? ", {$failedCount} failed" : ''),
                'user_id' => $this->userId,
            ]);

            $this->updateTracker($finalStatus, 'Capture completed', [
                'progress' => 100,
                'saved_count' => $savedCount,
                'failed_count' => $failedCount,
                'skipped_duplicate_count' => $skippedDuplicateCount,
                'processed_pages' => $processedPages,
                'processed_items' => $processedItems,
                'next_page' => $currentPage,
            ]);

            return;
        }

        if (!empty($detailCandidates)) {
            $detailProcessed = 0;

            foreach ($detailCandidates as $index => $candidate) {
                if ($this->isCancelled()) {
                    $this->updateTracker('failed', 'Capture dibatalkan', [
                        'progress' => 100,
                        'cancelled' => true,
                        'detail_processed' => $detailProcessed,
                        'detail_total' => count($detailCandidates),
                    ]);
                    return;
                }

                $itemId = $candidate['id'];

                try {
                    Log::info('Capture detail fetch started', [
                        'module' => $this->module,
                        'index' => $index,
                        'item_id' => $itemId,
                        'endpoint' => $this->moduleInfo['detail_endpoint'],
                    ]);

                    $detailDataRaw = $accurate->fetchModuleData(
                        $this->moduleInfo['detail_endpoint'],
                        ['id' => $itemId],
                        $this->sourceDbInfo,
                        $this->accessToken
                    );

                    $detailData = (is_array($detailDataRaw) && isset($detailDataRaw[0]) && is_array($detailDataRaw[0]))
                        ? $detailDataRaw[0]
                        : $detailDataRaw;

                    Log::info('Capture detail fetch completed', [
                        'module' => $this->module,
                        'index' => $index,
                        'item_id' => $itemId,
                    ]);

                    if (empty($detailData) || !is_array($detailData)) {
                        $failedCount++;
                        $detailProcessed++;
                        continue;
                    }

                    $identifierField = $this->moduleInfo['identifier_field'] ?? 'number';
                    $transactionNo = $detailData[$identifierField]
                        ?? $candidate['fallback_number']
                        ?? "ID-{$itemId}";

                    $handler->transformDetail($detailData, $sharedContext, ['itemId' => $itemId, 'module' => $this->module]);

                    $transactionsToInsert[] = [
                        'transaction_no' => $transactionNo,
                        'capture_log_id' => $this->trackerLogId,
                        'accurate_database_id' => $this->databaseId,
                        'module_id' => $moduleRecord->id,
                        'data' => json_encode($detailData),
                        'description' => $this->moduleInfo['name'],
                        'captured_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($transactionsToInsert) >= $batchSize) {
                        $flushInsertBatch();
                    }
                } catch (\Exception $exception) {
                    Log::error('Capture item failed', [
                        'module' => $this->module,
                        'index' => $index,
                        'item_id' => $itemId,
                        'error' => $exception->getMessage(),
                    ]);
                    $failedCount++;
                }

                $detailProcessed++;

                if ($detailProcessed % 20 === 0 || $detailProcessed === count($detailCandidates)) {
                    $listProgress = min(60, 10 + ($processedPages * 5));
                    $detailProgressPortion = (int) floor(($detailProcessed / max(1, count($detailCandidates))) * 35);

                    $this->updateTracker('running', 'Capture detail in progress', [
                        'progress' => min(95, $listProgress + $detailProgressPortion),
                        'saved_count' => $savedCount,
                        'failed_count' => $failedCount,
                        'skipped_duplicate_count' => $skippedDuplicateCount,
                        'processed_pages' => $processedPages,
                        'processed_items' => $processedItems,
                        'detail_processed' => $detailProcessed,
                        'detail_total' => count($detailCandidates),
                        'page_fetch_failures' => $pageFetchFailures,
                    ]);
                }
            }
        }

        $flushInsertBatch();

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
                'capture_mode' => $this->captureMode,
                'list_only_mode' => $listOnlyCaptureMode,
                'list_endpoint' => $this->moduleInfo['list_endpoint'],
                'detail_endpoint' => $this->moduleInfo['detail_endpoint'],
                'start_page' => $this->startPage,
                'total_pages_processed' => $processedPages,
                'total_items' => $processedItems,
                'saved_count' => $savedCount,
                'failed_count' => $failedCount,
                'skipped_duplicate_count' => $skippedDuplicateCount,
                'next_page' => $currentPage,
                'transaction_numbers' => $savedTransactionNumbers,
                'list_id_cache_enabled' => $useListIdCache,
                'used_cached_list_ids' => $loadedCandidatesFromCache,
                'list_params_hash' => $listParamsHash,
            ],
            'message' => "Capture {$this->moduleInfo['name']}: {$savedCount} saved" . ($failedCount > 0 ? ", {$failedCount} failed" : ''),
            'user_id' => $this->userId,
        ]);

        $this->updateTracker($finalStatus, 'Capture completed', [
            'progress' => 100,
            'saved_count' => $savedCount,
            'failed_count' => $failedCount,
            'skipped_duplicate_count' => $skippedDuplicateCount,
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

    private function buildParamsHash(array $params): string
    {
        $normalized = $this->normalizeArray($params);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalizeArray(array $input): array
    {
        ksort($input);

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->normalizeArray($value);
            }
        }

        return $input;
    }

    private function isCancelled(): bool
    {
        if (!$this->cancelToken) {
            return $this->cancelToken ? cache()->has($this->cancelToken) : false;
        }

        return cache()->has($this->cancelToken);
    }
}
