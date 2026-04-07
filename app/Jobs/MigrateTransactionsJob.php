<?php

namespace App\Jobs;

use App\Models\SystemLog;
use App\Models\Transaction;
use App\Services\AccurateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MigrateTransactionsJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public int $timeout = 0;

	public function __construct(
		public array $transactionIds,
		public int $targetDatabaseId,
		public string $targetDatabaseName,
		public array $targetDbInfo,
		public ?int $userId,
		public int $trackerLogId,
		public ?string $accessToken
	) {
	}

	public function handle(AccurateService $accurateService): void
	{
		if ($this->accessToken) {
			session(['accurate_access_token' => $this->accessToken]);
		}

		$this->updateTracker('running', 'Migration started', [
			'progress' => 0,
			'total_selected' => count($this->transactionIds),
			'success_count' => 0,
			'failed_count' => 0,
		]);

		$transactions = Transaction::with(['module', 'accurateDatabase'])
			->whereIn('id', $this->transactionIds)
			->get();

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
					$result = $accurateService->bulkSaveToAccurate($endpoint, $chunkData, $this->targetDbInfo, $this->accessToken);
					$isOverallSuccess = isset($result['s']) && $result['s'] === true;
					$itemResults = $result['d'] ?? [];
					if (!is_array($itemResults)) {
						$itemResults = [];
					}

					foreach ($chunkTransactions[$chunkIndex] as $idx => $transaction) {
						$itemResult = $itemResults[$idx] ?? null;
						$isSuccess = ($isOverallSuccess && empty($itemResults)) || ($itemResult && isset($itemResult['s']) && $itemResult['s'] === true);

						if ($isSuccess) {
							$transaction->refresh();
							$isFirstPush = (int) $transaction->push_count === 0;

							$transaction->update([
								'status' => 'success',
								'migrated_at' => now(),
								'push_status' => $isFirstPush ? 'pushed_create' : 'pushed_update',
								'last_pushed_at' => now(),
								'push_count' => ((int) $transaction->push_count) + 1,
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
								'push_status' => 'failed',
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

					foreach ($chunkTransactions[$chunkIndex] as $transaction) {
						$transaction->update([
							'status' => 'failed',
							'push_status' => 'failed',
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
}
