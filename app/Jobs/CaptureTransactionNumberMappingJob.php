<?php

namespace App\Jobs;

use App\Models\SystemLog;
use App\Services\AccurateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CaptureTransactionNumberMappingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;
    public int $tries = 1;

    public function __construct(
        public string $moduleSlug,
        public string $moduleName,
        public string $listEndpoint,
        public array $params,
        public int $pageSize,
        public int $startPage,
        public string $databaseName,
        public string $mappingModelClass,
        public int $trackerLogId,
        public ?int $userId,
        public ?string $accessToken,
        public ?array $sourceDbInfo,
        public ?string $cancelToken = null,
    ) {
    }

    public function handle(AccurateService $accurate): void
    {
        $mappingModelClass = $this->mappingModelClass;

        if ($this->accessToken && $this->sourceDbInfo) {
            session([
                'accurate_access_token' => $this->accessToken,
                'accurate_database' => $this->sourceDbInfo,
                'database_id' => $this->sourceDbInfo['id'] ?? null,
            ]);
        }

        $savedCount = 0;
        $updatedCount = 0;
        $deletedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $processedPages = 0;
        $processedItems = 0;
        $currentPage = max(1, $this->startPage);
        $capturedByNewNumber = [];

        $this->updateTracker('running', 'Capture number mapping started', [
            'progress' => 1,
            'capture_mode' => 'list_only',
            'saved_count' => 0,
            'updated_count' => 0,
            'deleted_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'processed_pages' => 0,
            'processed_items' => 0,
        ]);

        while (true) {
            if ($this->isCancelled()) {
                $this->updateTracker('failed', 'Capture dibatalkan', [
                    'progress' => 100,
                    'cancelled' => true,
                    'saved_count' => $savedCount,
                    'updated_count' => $updatedCount,
                    'deleted_count' => $deletedCount,
                    'skipped_count' => $skippedCount,
                    'failed_count' => $failedCount,
                    'processed_pages' => $processedPages,
                    'processed_items' => $processedItems,
                ]);

                return;
            }

            try {
                $result = $accurate->fetchModuleDataPage(
                    endpoint: $this->listEndpoint,
                    params: $this->params,
                    pageNumber: $currentPage,
                    pageSize: $this->pageSize,
                    targetDbInfo: $this->sourceDbInfo,
                    accessToken: $this->accessToken,
                );
            } catch (\Throwable $exception) {
                $failedCount++;

                $this->updateTracker('failed', 'Capture gagal mengambil data list', [
                    'progress' => 100,
                    'error' => $exception->getMessage(),
                    'saved_count' => $savedCount,
                    'updated_count' => $updatedCount,
                    'deleted_count' => $deletedCount,
                    'skipped_count' => $skippedCount,
                    'failed_count' => $failedCount,
                    'processed_pages' => $processedPages,
                    'processed_items' => $processedItems,
                ]);

                return;
            }

            $rows = $result['data'] ?? [];
            if (empty($rows)) {
                break;
            }

            $processedPages++;
            $processedItems += count($rows);

            foreach ($rows as $row) {
                $number = trim((string) ($row['number'] ?? ''));
                $charField = trim((string) ($row['charField'] ?? ''));
                $charField1 = trim((string) ($row['charField1'] ?? ''));

                $oldNumber = $charField !== '' ? $charField : ($charField1 !== '' ? $charField1 : null);
                $newNumber = $number;

                if ($newNumber !== '') {
                    $capturedByNewNumber[$newNumber] = $oldNumber;
                }
            }

            $progress = min(95, 5 + ($processedPages * 5));
            $this->updateTracker('running', 'Capture number mapping sedang diproses', [
                'progress' => $progress,
                'saved_count' => $savedCount,
                'updated_count' => $updatedCount,
                'deleted_count' => $deletedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'processed_pages' => $processedPages,
                'processed_items' => $processedItems,
                'next_page' => $currentPage + 1,
            ]);

            $currentPage++;
        }

        $deletedCount = $mappingModelClass::query()
            ->where('db_name', $this->databaseName)
            ->delete();

        if (!empty($capturedByNewNumber)) {
            $rowsToInsert = [];
            foreach ($capturedByNewNumber as $newNumber => $oldNumber) {
                $rowsToInsert[] = [
                    'db_name' => $this->databaseName,
                    'old_number' => $oldNumber,
                    'new_number' => $newNumber,
                ];
            }

            try {
                $mappingModelClass::query()->insertOrIgnore($rowsToInsert);
                $savedCount = count($rowsToInsert);
            } catch (\Throwable $exception) {
                $savedCount = 0;
                $failedInserts = [];

                foreach ($rowsToInsert as $row) {
                    try {
                        $mappingModelClass::query()->insertOrIgnore([$row]);
                        $savedCount++;
                    } catch (\Throwable $e) {
                        $failedInserts[] = $row;
                    }
                }

                if (!empty($failedInserts)) {
                    Log::warning('Failed to insert some sales invoice mappings', [
                        'database_name' => $this->databaseName,
                        'failed_count' => count($failedInserts),
                        'failed_inserts' => $failedInserts,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $this->updateTracker('success', 'Capture number mapping selesai', [
            'progress' => 100,
            'capture_mode' => 'list_only',
            'saved_count' => $savedCount,
            'updated_count' => $updatedCount,
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'processed_pages' => $processedPages,
            'processed_items' => $processedItems,
        ]);
    }

    private function updateTracker(string $status, string $message, array $payload): void
    {
        SystemLog::query()
            ->whereKey($this->trackerLogId)
            ->update([
                'status' => $status,
                'message' => $message,
                'payload' => $payload,
            ]);
    }

    private function isCancelled(): bool
    {
        if (!$this->cancelToken) {
            return false;
        }

        return (bool) cache()->get($this->cancelToken, false);
    }
}
