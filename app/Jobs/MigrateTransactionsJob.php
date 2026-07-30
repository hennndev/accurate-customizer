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
	public int $tries = 5;
	public array $backoff = [60, 120, 300];

	public function __construct(
		public array $transactionIds,
		public int $targetDatabaseId,
		public string $targetDatabaseName,
		public array $targetDbInfo,
		public ?int $userId,
		public int $trackerLogId,
		public ?string $accessToken,
		public bool $forceCreate = false,
		public bool $addJuSuffix = false,
		public array $targetNumbers = [],
		public string $numberingMode = 'accurate',
		public array $customInvoiceMappings = [],
	) {
	}

	public function handle(AccurateService $accurateService, NumberMappingManager $numberMappingManager): void
	{
		if ($this->isCancelled()) {
			return;
		}

		if ($this->accessToken) {
			session(['accurate_access_token' => $this->accessToken]);
		}

		if (!empty($this->targetDbInfo)) {
			session(['accurate_database' => $this->targetDbInfo]);
		}

		if (!empty($this->targetDbInfo['id'])) {
			session(['database_id' => $this->targetDbInfo['id']]);
		}

		$totalSelected = count($this->transactionIds);
		
		$successCount = \App\Models\Transaction::whereIn('id', $this->transactionIds)
			->where('status', 'success')
			->count();
			
		$failedCount = 0;
		$processedTransactions = $successCount;
		$progress = $totalSelected > 0 ? min(99, (int) round(($processedTransactions / $totalSelected) * 100)) : 0;

		$this->updateTracker('running', 'Migration started', [
			'progress' => $progress,
			'total_selected' => $totalSelected,
			'success_count' => $successCount,
			'failed_count' => $failedCount,
			'force_create' => $this->forceCreate,
			'add_ju_suffix' => $this->addJuSuffix,
		]);

		$transactions = Transaction::with(['module', 'accurateDatabase'])
			->whereIn('id', $this->transactionIds)
			->get();

		// Mengurutkan ulang collection agar persis sesuai dengan urutan array $this->transactionIds dari UI
		$idOrder = array_flip($this->transactionIds);
		$transactions = $transactions->sortBy(function ($transaction) use ($idOrder) {
			return $idOrder[$transaction->id] ?? 999999;
		})->values();

		if ($transactions->isEmpty()) {
			$this->updateTracker('failed', 'No transactions selected for migration', [
				'progress' => 100,
				'success_count' => $successCount,
				'failed_count' => $failedCount,
			]);
			return;
		}

		$numberingMode = $this->numberingMode ?? 'accurate';

		$isMasterModule = function ($t) {
			$slug = $t->module?->slug;
			$masterModules = [
				'customer', 'vendor', 'item', 'branch', 'department', 'employee', 'warehouse', 'project',
				'customer-category', 'vendor-category', 'item-category', 'price-category', 'data-classification',
				'vendor-price', 'glaccount', 'currency', 'tax', 'unit', 'fob', 'bill-of-material'
			];
			return in_array($slug, $masterModules, true) || ($t->module?->type === 'master');
		};

		if ($numberingMode === 'original') {
			foreach ($transactions as $t) {
				$data = is_string($t->data) ? json_decode($t->data, true) : (array)$t->data;
				$originalNo = $t->transaction_no;
				// For GL Account, if raw JSON already has the account number 'no', prefer it over transaction_no
				if ($t->module?->slug === 'glaccount' && !empty($data['no'])) {
					$originalNo = $data['no'];
				}
				if ($originalNo) {
					$this->targetNumbers[$t->id] = $originalNo;
					if (!isset($data['_sourceNumber'])) {
						$data['_sourceNumber'] = $data['number'] ?? $data['no'] ?? null;
					}
					if (!$isMasterModule($t)) {
						$data['number'] = $originalNo;
						$data['no'] = $originalNo;
						$data['_custom_number'] = true;
					} else {
						// Master data modules keep their original code/number field
						if ($t->module?->slug === 'glaccount') {
							$data['no'] = $originalNo;
						}
					}
					$t->data = json_encode($data);
				}
			}
		} elseif ($numberingMode === 'custom' && !empty($this->targetNumbers)) {
			foreach ($transactions as $t) {
				$data = is_string($t->data) ? json_decode($t->data, true) : (array)$t->data;
				if (!isset($data['_sourceNumber'])) {
					$data['_sourceNumber'] = $data['number'] ?? $data['no'] ?? null;
				}
				if (!$isMasterModule($t) && !empty($this->targetNumbers[$t->id])) {
					$data['number'] = $this->targetNumbers[$t->id];
					$data['no'] = $this->targetNumbers[$t->id];
					$data['_custom_number'] = true;
				} elseif ($t->module?->slug === 'glaccount' && !empty($data['no'])) {
					// Ensure GL Account retains its account number no
					$data['no'] = $data['no'];
				}
				$t->data = json_encode($data);
			}
		}

		$groupedByModule = $transactions->groupBy('module.slug');
		$totalModules = max(1, $groupedByModule->count());
		$moduleIndex = 0;

		$moduleResults = [];
		$totalTransactions = $totalSelected;

		foreach ($groupedByModule as $moduleSlug => $moduleTransactions) {
			if ($this->isCancelled()) {
				return;
			}
			$moduleIndex++;
			$module = $moduleTransactions->first()->module;

			if (!$module) {
				$failedCount += $moduleTransactions->count();
				continue;
			}

			if (!isset($moduleResults[$module->name])) {
				$moduleResults[$module->name] = [
					'success' => \App\Models\Transaction::whereIn('id', $this->transactionIds)
						->where('module_id', $module->id)
						->where('status', 'success')
						->count(),
					'failed' => 0,
					'errors' => [],
				];
			}

			$bulkData = [];
			$payloadTransactions = [];

			foreach ($moduleTransactions as $transaction) {

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

				// Apply -JU suffix if module is journal-voucher, addJuSuffix is true, and transaction was previously failed
				if ($moduleSlug === 'journal-voucher' && $this->addJuSuffix && $transaction->status === 'failed') {
					if (isset($data['number'])) {
						$data['number'] = $data['number'] . '-JU';
					} elseif (isset($data['no'])) {
						$data['no'] = $data['no'] . '-JU';
					}
				}

				if ($this->isDownPaymentModule($moduleSlug)) {
					$data['invoiceDp'] = true;
				}

				if (!empty($this->customInvoiceMappings[$transaction->id]) && is_array($this->customInvoiceMappings[$transaction->id])) {
					$data['_custom_invoice_mappings'] = $this->customInvoiceMappings[$transaction->id];
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
				if ($this->isCancelled()) {
					return;
				}
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

					$processedTransactions += count($chunkData);
					$progress = min(99, round(($processedTransactions / $totalTransactions) * 100));

					$this->updateTracker('running', "Migrating {$module->name}...", [
						'progress' => $progress,
						'success_count' => $successCount,
						'failed_count' => $failedCount,
						'module_results' => $moduleResults,
					]);

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

					// Network error, let Laravel automatically retry it
					$this->updateTracker('warning', 'Terjadi kesalahan server/jaringan. Menunggu auto-resume otomatis...', [
						'error' => $exception->getMessage(),
						'last_real_error' => $exception->getMessage(),
					]);
					
					throw $exception;
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

			// Track skipped transactions that didn't go through the chunk loop
			$processedTransactions += ($moduleTransactions->count() - count($bulkData));
			$progress = min(99, round(($processedTransactions / $totalTransactions) * 100));

			$this->updateTracker('running', "Completed {$module->name}", [
				'progress' => $progress,
				'success_count' => $successCount,
				'failed_count' => $failedCount,
				'module_results' => $moduleResults,
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
		$errorMessage = $exception->getMessage();

		if (str_contains($errorMessage, 'has been attempted too many times')) {
			$log = SystemLog::find($this->trackerLogId);
			$existingPayload = is_array($log?->payload) ? $log->payload : [];
			if (!empty($existingPayload['last_real_error'])) {
				$errorMessage = "Max retries reached. Last error: " . $existingPayload['last_real_error'];
			}
		}

		$this->updateTracker('failed', 'Migration failed: ' . $errorMessage, [
			'progress' => 100,
			'error' => $errorMessage,
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

	private function isCancelled(): bool
	{
		$log = SystemLog::find($this->trackerLogId);
		return $log && ($log->status === 'failed' || ($log->payload['cancelled'] ?? false));
	}
}
