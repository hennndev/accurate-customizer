<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\SystemLog;
use App\Models\AccurateDatabase;
use App\Models\Module;
use App\Services\AccurateService;
use App\Modules\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DataMigrateController extends Controller
{
  protected $accurateService;

  public function __construct(AccurateService $accurateService)
  {
    $this->accurateService = $accurateService;
  }


  // HALAMAN INDEX MIGRATE DATA
  public function index(Request $request)
  {
    $databases = $this->accurateService->getDatabaseList();
    $current_database_name = session('database_name');
    $current_database_id = session('database_id');
    $query = Transaction::with(['accurateDatabase', 'module']);

    if ($request->filled('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->where('transaction_no', 'like', "%{$search}%")
          ->orWhere('description', 'like', "%{$search}%");
      });
    }

    if ($request->filled('source_db') && $request->source_db !== 'All Database') {
      $accurateDb = AccurateDatabase::where('db_name', $request->source_db)->first();
      if ($accurateDb) {
        $query->where('accurate_database_id', $accurateDb->id);
      }
    }

    if ($request->filled('module') && $request->module !== 'All Modules') {
      $moduleIds = Module::where('name', $request->module)->pluck('id');
      if ($moduleIds->isNotEmpty()) {
        $query->whereIn('module_id', $moduleIds);
      }
    }

    if ($request->filled('status') && $request->status !== 'All Status') {
      $query->where('status', strtolower($request->status));
    }

    // Paginate transactions - 100 per page
    $transactions = $query->paginate(100)->appends($request->except('page'));

    $filter_databases = AccurateDatabase::pluck('db_name');
    $modules = Module::pluck('name')->unique();
    return view('migrate.index', compact('transactions', 'databases', 'filter_databases', 'modules', 'current_database_name'));
  }


  // DELETE SINGLE TRANSACTION
  public function destroy(Transaction $transaction)
  {
    $transactionNo = $transaction->transaction_no;
    $module = $transaction->module ? $transaction->module->name : 'N/A';
    $transaction->delete();

    SystemLog::create([
      'event_type' => 'delete',
      'module' => $module,
      'status' => 'success',
      'payload' => [
        'transaction_no' => $transactionNo,
        'deleted_at' => now()->toDateTimeString(),
      ],
      'message' => "Transaction {$transactionNo} from module {$module} deleted successfully",
      'user_id' => Auth::id(),
    ]);

    return redirect()->route('migrate.index')->with('success', 'Transaction deleted successfully.');
  }

  // DELETE MULTIPLE TRANSACTIONS
  public function destroyMultiple(Request $request)
  {
    $request->validate([
      'ids' => 'required|array',
      'ids.*' => 'required|integer|exists:transactions,id'
    ]);

    $ids = $request->input('ids', []);

    $transactions = Transaction::with('module')->whereIn('id', $ids)->get();
    $transactionNumbers = $transactions->pluck('transaction_no')->toArray();
    $modules = $transactions->map(fn($t) => $t->module?->name ?? 'N/A')->unique()->toArray();

    Transaction::whereIn('id', $ids)->delete();

    SystemLog::create([
      'event_type' => 'mass delete',
      'module' => implode(', ', $modules),
      'transaction_id' => null,
      'status' => 'success',
      'payload' => [
        'transaction_ids' => $ids,
        'transaction_numbers' => $transactionNumbers,
        'total_deleted' => count($ids),
        'deleted_at' => now()->toDateTimeString(),
      ],
      'message' => count($ids) . " transaction(s) deleted successfully: " . implode(', ', $transactionNumbers),
      'user_id' => Auth::id(),
    ]);

    return redirect()->route('migrate.index')->with('success', count($ids) . ' transaction(s) deleted successfully.');
  }


  // MIGRATE KE ACCURATE
  public function migrateToAccurate(Request $request)
  {
    // Remove execution time limit for large data migration
    set_time_limit(0);
    ini_set('max_execution_time', 0);

    $request->validate([
      'ids' => 'required|array',
      'ids.*' => 'required|integer|exists:transactions,id'
    ]);

    $targetDbId = session('database_id');
    $targetDbName = session('database_name');

    if (!$targetDbId || !$targetDbName) {
      return redirect()->route('migrate.index')->with('error', 'Please select a target database first.');
    }

    // Re-open database to ensure fresh session and valid host
    try {
      $dbInfo = $this->accurateService->openDatabaseById($targetDbId);
      if (!$dbInfo) {
        Log::error('MIGRATION_FAILED_TO_REOPEN_DATABASE', [
          'user_id' => Auth::id(),
          'target_database_id' => $targetDbId,
        ]);
        return redirect()->route('migrate.index')->with('error', 'Failed to connect to target database. Please try again.');
      }

      // Update session with fresh database info
      session([
        'accurate_database' => $dbInfo,
        'database_id' => $targetDbId,
        'database_name' => $targetDbName,
      ]);
    } catch (\Exception $e) {
      Log::error('MIGRATION_DATABASE_REOPEN_ERROR', [
        'error' => $e->getMessage(),
        'database_id' => $targetDbId,
      ]);
      return redirect()->route('migrate.index')->with('error', 'Failed to connect to database: ' . $e->getMessage());
    }

    try {
      $ids = $request->input('ids', []);
      $transactions = Transaction::with(['module', 'accurateDatabase'])
        ->whereIn('id', $ids)
        ->get();

      if ($transactions->isEmpty()) {
        return redirect()->route('migrate.index')->with('error', 'No transactions selected for migration.');
      }

      $groupedByModule = $transactions->groupBy('module.slug');
      $successCount = 0;
      $failedCount = 0;
      $skippedCount = 0;
      $errors = [];
      $moduleResults = []; // Track per-module results


      foreach ($groupedByModule as $moduleSlug => $moduleTransactions) {
        $module = $moduleTransactions->first()->module;
        if (!$module) {
          $failedCount += $moduleTransactions->count();
          $errors[] = "Module not found for some transactions";
          Log::error('MIGRATION_MODULE_NOT_FOUND', [
            'module_slug' => $moduleSlug,
            'transaction_count' => $moduleTransactions->count(),
          ]);
          continue;
        }

        // Initialize module tracking
        if (!isset($moduleResults[$module->name])) {
          $moduleResults[$module->name] = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
          ];
        }
        $bulkData = [];
        foreach ($moduleTransactions as $transaction) {
          $data = json_decode($transaction->data, true);
          if ($data) {
            $bulkData[] = $data;
          }
        }

        if (empty($bulkData)) {
          $skippedCount += $moduleTransactions->count();
          continue;
        }

        try {
          // Push to Accurate using bulk-save endpoint
          $endpoint = str_replace('/list.do', '/bulk-save.do', $module->accurate_endpoint);
          $chunks = array_chunk($bulkData, 100);
          $chunkTransactions = array_chunk($moduleTransactions->all(), 100);

          foreach ($chunks as $chunkIndex => $chunkData) {
            $result = $this->accurateService->bulkSaveToAccurate($endpoint, $chunkData);
            $isOverallSuccess = isset($result['s']) && $result['s'] === true;
            $itemResults = $result['d'] ?? [];

            if (!is_array($itemResults)) {
              $itemResults = [];
            }
            if ($isOverallSuccess && empty($itemResults)) {
              foreach ($chunkTransactions[$chunkIndex] as $idx => $transaction) {
                $transaction->update([
                  'status' => 'success',
                  'migrated_at' => now(),
                ]);
                $successCount++;
                $moduleResults[$module->name]['success']++;
              }
            } else {
              // Process individual item results
              foreach ($chunkTransactions[$chunkIndex] as $idx => $transaction) {
                $itemResult = $itemResults[$idx] ?? null;

                if ($itemResult && isset($itemResult['s']) && $itemResult['s'] === true) {
                  $transaction->update([
                    'status' => 'success',
                    'migrated_at' => now(),
                  ]);
                  $successCount++;
                  $moduleResults[$module->name]['success']++;
                } else {
                  $errorData = $itemResult['d'] ?? ['Unknown error'];

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

                  // Store unique errors per module
                  if (!in_array($errorText, $moduleResults[$module->name]['errors'])) {
                    $moduleResults[$module->name]['errors'][] = $errorText;
                  }
                }
              }
            }
          }

          $moduleSuccessCount = $moduleTransactions->filter(function ($t) {
            return $t->fresh()->status === 'success';
          })->count();

          $moduleFailedCount = $moduleTransactions->filter(function ($t) {
            return $t->fresh()->status === 'failed';
          })->count();
          if ($moduleSuccessCount > 0) {
            SystemLog::create([
              'event_type' => 'migrate',
              'module' => $module->name,
              'transaction_id' => null,
              'status' => $moduleFailedCount > 0 ? 'partial' : 'success',
              'payload' => [
                'module' => $module->name,
                'target_database' => $targetDbName,
                'total_items' => count($bulkData),
                'success_items' => $moduleSuccessCount,
                'failed_items' => $moduleFailedCount,
                'endpoint' => $endpoint,
                'transaction_ids' => $moduleTransactions->pluck('id')->toArray(),
                'errors' => $moduleFailedCount > 0 ? $moduleResults[$module->name]['errors'] : [],
              ],
              'message' => "Migrated {$moduleSuccessCount} of {$moduleTransactions->count()} {$module->name} transaction(s) to {$targetDbName}",
              'user_id' => Auth::id(),
            ]);
          }

          if ($moduleFailedCount > 0 && $moduleSuccessCount === 0) {
            SystemLog::create([
              'event_type' => 'migrate',
              'module' => $module->name,
              'transaction_id' => null,
              'status' => 'failed',
              'payload' => [
                'module' => $module->name,
                'target_database' => $targetDbName,
                'total_items' => count($bulkData),
                'failed_items' => $moduleFailedCount,
                'endpoint' => $endpoint,
                'transaction_ids' => $moduleTransactions->pluck('id')->toArray(),
                'errors' => $moduleResults[$module->name]['errors'],
              ],
              'message' => "Failed to migrate {$module->name} transaction(s) to {$targetDbName}. Errors: " . implode('; ', array_slice($errors, -5)),
              'user_id' => Auth::id(),
            ]);
          }
        } catch (\Exception $e) {
          Log::error('MIGRATION_MODULE_FAILED', [
            'module' => $module->name,
            'endpoint' => $endpoint ?? 'N/A',
            'error' => $e->getMessage(),
            'transaction_count' => $moduleTransactions->count(),
            'trace' => $e->getTraceAsString(),
          ]);

          foreach ($moduleTransactions as $transaction) {
            $transaction->update([
              'status' => 'failed',
            ]);
            $failedCount++;
            $moduleResults[$module->name]['failed']++;
          }

          // Store module exception error
          if (!in_array($e->getMessage(), $moduleResults[$module->name]['errors'])) {
            $moduleResults[$module->name]['errors'][] = $e->getMessage();
          }

          SystemLog::create([
            'event_type' => 'migrate',
            'module' => $module->name,
            'transaction_id' => null,
            'status' => 'failed',
            'payload' => [
              'module' => $module->name,
              'target_database' => $targetDbName,
              'error' => $e->getMessage(),
              'transaction_ids' => $moduleTransactions->pluck('id')->toArray(),
            ],
            'message' => "Failed to migrate {$module->name}: {$e->getMessage()}",
            'user_id' => Auth::id(),
          ]);

          Log::error('MIGRATION_ERROR', [
            'module' => $module->name,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
          ]);
        }
      }

      // Prepare response message
      $message = "Migration completed: {$successCount} succeeded, {$failedCount} failed";
      if ($skippedCount > 0) {
        $message .= ", {$skippedCount} skipped";
      }

      // Build grouped error message per module
      if (!empty($moduleResults)) {
        $moduleDetails = [];
        foreach ($moduleResults as $moduleName => $result) {
          $moduleDetail = "{$moduleName} (Success: {$result['success']}, Failed: {$result['failed']})";
          if (!empty($result['errors'])) {
            $moduleDetail .= " - Errors: " . implode(', ', array_slice($result['errors'], 0, 3));
          }
          $moduleDetails[] = $moduleDetail;
        }

        if (!empty($moduleDetails)) {
          $message .= ". Details: " . implode('; ', $moduleDetails);
        }
      }

      $status = $failedCount > 0 ? 'error' : 'success';

      return redirect()->route('migrate.index')->with($status, $message);
    } catch (\Exception $e) {
      Log::error('MIGRATION_PROCESS_ERROR', [
        'user_id' => Auth::id(),
        'target_database' => $targetDbName ?? 'N/A',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return redirect()->route('migrate.index')->with('error', 'Migration failed: ' . $e->getMessage());
    }
  }
}
