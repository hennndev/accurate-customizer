<?php

namespace App\Jobs;

use App\Models\SystemLog;
use App\Models\SalesInvoiceMapping;
use App\Models\Transaction;
use App\Services\Accurate\NumberMappingManager;
use App\Services\AccurateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MigrateTransactionsJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public int $timeout = 1800; // 30 minutes max per job
	public int $tries = 1;

	public function __construct(
		public array $transactionIds,
		public int $targetDatabaseId,
		public string $targetDatabaseName,
		public array $targetDbInfo,
		public ?int $userId,
		public int $trackerLogId,
		public ?string $accessToken,
		public bool $forceCreate = false,
	) {
	}

	public function handle(AccurateService $accurateService, NumberMappingManager $numberMappingManager): void
	{
		if ($this->accessToken) {
			session(['accurate_access_token' => $this->accessToken]);
		}

		if (!empty($this->targetDbInfo)) {
			session(['accurate_database' => $this->targetDbInfo]);
		}

		if (!empty($this->targetDbInfo['id'])) {
			session(['database_id' => $this->targetDbInfo['id']]);
		}

		$this->updateTracker('running', 'Migration started', [
			'progress' => 0,
			'total_selected' => count($this->transactionIds),
			'success_count' => 0,
			'failed_count' => 0,
			'force_create' => $this->forceCreate,
		]);

		$transactionsQuery = Transaction::with(['module', 'accurateDatabase'])
			->whereIn('id', $this->transactionIds);

		if (!$this->forceCreate) {
			$transactionsQuery->where('status', '!=', 'success');
		}

		$transactions = $transactionsQuery->get();

		if ($transactions->isEmpty()) {
			$this->updateTracker('failed', 'No transactions selected for migration', [
				'progress' => 100,
				'success_count' => 0,
				'failed_count' => 0,
			]);
			return;
		}

		$groupedByModule = $transactions->groupBy('module.slug');
		$totalModules = max(1, $groupedByModule->count());
		$moduleIndex = 0;

		$successCount = 0;
		$failedCount = 0;
		$moduleResults = [];

		foreach ($groupedByModule as $moduleSlug => $moduleTransactions) {
			$moduleIndex++;
			$module = $moduleTransactions->first()->module;

			if (!$module) {
				$failedCount += $moduleTransactions->count();
				continue;
			}

			if (!isset($moduleResults[$module->name])) {
				$moduleResults[$module->name] = [
					'success' => 0,
					'failed' => 0,
					'errors' => [],
				];
			}

			$bulkData = [];
			$payloadTransactions = [];

			$salesInvoiceOldNumberMap = [];
			if ($moduleSlug === 'sales-invoice') {
				$candidateNumbers = $moduleTransactions
					->pluck('transaction_no')
					->filter(fn ($number) => filled($number))
					->values()
					->all();

				if (!empty($candidateNumbers)) {
					$mappedOldNumbers = SalesInvoiceMapping::query()
						->where('db_name', $this->targetDatabaseName)
						->whereIn('old_number', $candidateNumbers)
						->pluck('old_number')
						->all();

					$salesInvoiceOldNumberMap = array_flip($mappedOldNumbers);
				}
			}

			foreach ($moduleTransactions as $transaction) {
				if ($moduleSlug === 'sales-invoice' && isset($salesInvoiceOldNumberMap[$transaction->transaction_no])) {
					$transaction->update([
						'status' => 'success',
						'migrated_at' => now(),
						'error_message' => null,
					]);

					$successCount++;
					$moduleResults[$module->name]['success']++;
					continue;
				}

				$data = json_decode($transaction->data, true);
				if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data)) {
					$transaction->update([
						'status' => 'failed',
						'error_message' => 'Invalid or empty transaction data JSON',
						'push_status' => 'failed',
					]);

					$failedCount++;
					$moduleResults[$module->name]['failed']++;
					continue;
				}

				if ($this->isDownPaymentModule($moduleSlug)) {
					$data['invoiceDp'] = true;
				}

				$bulkData[] = $data;
				$payloadTransactions[] = $transaction;
			}

			if (empty($bulkData)) {
				continue;
			}

			$endpoint = str_replace('/list.do', '/bulk-save.do', $module->accurate_endpoint);
			$chunks = array_chunk($bulkData, 100);
			$chunkTransactions = array_chunk($payloadTransactions, 100);

			foreach ($chunks as $chunkIndex => $chunkData) {
				try {
          Log::info('MIGRATION_CHUNK_START', [
            'module' => $moduleSlug,
            'chunk_index' => $chunkIndex,
            'items_count' => count($chunkData),
          ]);
					$result = $accurateService->bulkSaveToAccurate($endpoint, $chunkData, $this->targetDbInfo, $this->accessToken, $this->forceCreate);
					$isOverallSuccess = isset($result['s']) && $result['s'] === true;
					$itemResults = $result['d'] ?? [];
					if (!is_array($itemResults)) {
						$itemResults = [];
					}

					// Store number mappings for this chunk (old_number → new_number in Accurate)
					try {
						$localDatabaseId = $this->targetDbInfo['_local_db_id'] ?? null;
						if ($localDatabaseId) {
							$numberMappingManager->storeNumberMappings(
								$endpoint,
								$chunkData,
								$result,
								$localDatabaseId
							);
						}
					} catch (\Throwable $mappingException) {
						Log::warning('NUMBER_MAPPING_STORE_ERROR', [
							'module' => $moduleSlug,
							'error' => $mappingException->getMessage(),
						]);
					}

					foreach ($chunkTransactions[$chunkIndex] as $idx => $transaction) {
						$itemResult = $itemResults[$idx] ?? null;
						$isSuccess = ($isOverallSuccess && empty($itemResults)) || ($itemResult && isset($itemResult['s']) && $itemResult['s'] === true);

						if ($isSuccess) {
							$transaction->update([
								'status' => 'success',
								'migrated_at' => now(),
							]);

							$successCount++;
							$moduleResults[$module->name]['success']++;
						} else {
							$errorData = $itemResult['d']
								?? $itemResult['e']
								?? $result['d']
								?? $result['e']
								?? ['Unknown error'];

							if (is_array($errorData)) {
								$flattenedErrors = [];
								array_walk_recursive($errorData, function ($item) use (&$flattenedErrors) {
									if (is_string($item)) {
										$flattenedErrors[] = $item;
									}
								});
								$errorText = implode('; ', $flattenedErrors ?: ['Unknown error']);
							} else {
								$errorText = (string) $errorData;
							}

							$transaction->update([
								'status' => 'failed',
								'error_message' => $errorText,
							]);

							$failedCount++;
							$moduleResults[$module->name]['failed']++;
							if (!in_array($errorText, $moduleResults[$module->name]['errors'])) {
								$moduleResults[$module->name]['errors'][] = $errorText;
							}
						}
					}
				} catch (\Exception $exception) {
					Log::error('MIGRATION_CHUNK_EXCEPTION', [
						'module' => $module->slug,
						'chunk_index' => $chunkIndex,
						'error' => $exception->getMessage(),
					]);

					if ($this->isAccurateTokenInvalid($exception)) {
						$this->updateTracker('failed', 'Sesi Accurate habis atau token tidak valid. Silakan login Accurate ulang.', [
							'progress' => 100,
							'error' => $exception->getMessage(),
							'token_invalid' => true,
						]);
						return;
					}

					foreach ($chunkTransactions[$chunkIndex] as $transaction) {
						$transaction->update([
							'status' => 'failed',
							'error_message' => $exception->getMessage(),
						]);
						$failedCount++;
						$moduleResults[$module->name]['failed']++;
					}

					if (!in_array($exception->getMessage(), $moduleResults[$module->name]['errors'])) {
						$moduleResults[$module->name]['errors'][] = $exception->getMessage();
					}
				}

				$progress = min(95, (int) (($moduleIndex / $totalModules) * 100));
				$this->updateTracker('running', 'Migration in progress', [
					'progress' => $progress,
					'success_count' => $successCount,
					'failed_count' => $failedCount,
					'module_results' => $moduleResults,
				]);
			}

			SystemLog::create([
				'event_type' => 'migrate',
				'module' => $module->name,
				'transaction_id' => null,
				'status' => $moduleResults[$module->name]['failed'] > 0
					? ($moduleResults[$module->name]['success'] > 0 ? 'partial' : 'failed')
					: 'success',
				'payload' => [
					'module' => $module->name,
					'target_database' => $this->targetDatabaseName,
					'total_items' => count($bulkData),
					'success_items' => $moduleResults[$module->name]['success'],
					'failed_items' => $moduleResults[$module->name]['failed'],
					'endpoint' => $endpoint,
					'transaction_ids' => $moduleTransactions->pluck('id')->toArray(),
					'errors' => $moduleResults[$module->name]['errors'],
				],
				'message' => "Migrated {$moduleResults[$module->name]['success']} of {$moduleTransactions->count()} {$module->name} transaction(s) to {$this->targetDatabaseName}",
				'user_id' => $this->userId,
			]);
		}

		$finalStatus = $failedCount > 0 ? ($successCount > 0 ? 'warning' : 'failed') : 'success';

		$this->updateTracker($finalStatus, 'Migration completed', [
			'progress' => 100,
			'success_count' => $successCount,
			'failed_count' => $failedCount,
			'module_results' => $moduleResults,
		]);
	}

	public function middleware(): array
	{
		return [
			(new WithoutOverlapping('migrate-tracker-' . $this->trackerLogId))
				->expireAfter(3600),
		];
	}

	public function failed(\Throwable $exception): void
	{
		$this->updateTracker('failed', 'Migration failed: ' . $exception->getMessage(), [
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

	private function isDownPaymentModule(string $moduleSlug): bool
	{
		return in_array($moduleSlug, [
			'down-payment-sales-invoice',
			'down-payment-purchase-invoice',
		], true);
	}

	private function isAccurateTokenInvalid(\Throwable $exception): bool
	{
		$message = strtolower($exception->getMessage());

		return str_contains($message, 'accurate_token_invalid')
			|| str_contains($message, 'invalid_token')
			|| str_contains($message, 'token tidak valid')
			|| str_contains($message, 'sesi accurate habis');
	}
}
