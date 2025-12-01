<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\SystemLog;
use App\Models\AccurateDatabase;
use App\Models\Module;
use App\Services\AccurateService;
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
            $query->where(function($q) use ($search) {
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
            $moduleRecord = Module::where('name', $request->module)->first();
            if ($moduleRecord) {
                $query->where('module_id', $moduleRecord->id);
            }
        }

        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', strtolower($request->status));
        }
        $transactions = $query->get();        
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
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:transactions,id'
        ]);

        $targetDbId = session('database_id');
        $targetDbName = session('database_name');

        Log::info('MIGRATION_STARTED', [
            'user_id' => Auth::id(),
            'target_database_id' => $targetDbId,
            'target_database_name' => $targetDbName,
            'total_transactions' => count($request->input('ids', [])),
        ]);

        if (!$targetDbId || !$targetDbName) {
            Log::warning('MIGRATION_NO_TARGET_DATABASE', [
                'user_id' => Auth::id(),
            ]);
            return redirect()->route('migrate.index')->with('error', 'Please select a target database first.');
        }
        try {
            $ids = $request->input('ids', []);
            $transactions = Transaction::with(['module', 'accurateDatabase'])
                ->whereIn('id', $ids)
                ->get();

            if ($transactions->isEmpty()) {
                Log::warning('MIGRATION_NO_TRANSACTIONS', [
                    'user_id' => Auth::id(),
                    'requested_ids' => $ids,
                ]);
                return redirect()->route('migrate.index')->with('error', 'No transactions selected for migration.');
            }

            Log::info('MIGRATION_TRANSACTIONS_LOADED', [
                'total_loaded' => $transactions->count(),
                'modules' => $transactions->pluck('module.name')->unique()->values(),
            ]);
            $groupedByModule = $transactions->groupBy('module.slug');
            $successCount = 0;
            $failedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($groupedByModule as $moduleSlug => $moduleTransactions) {
                $module = $moduleTransactions->first()->module;       
                Log::info('MIGRATION_PROCESSING_MODULE', [
                    'module_slug' => $moduleSlug,
                    'module_name' => $module?->name,
                    'transaction_count' => $moduleTransactions->count(),
                ]);
                if (!$module) {
                    $failedCount += $moduleTransactions->count();
                    $errors[] = "Module not found for some transactions";
                    Log::error('MIGRATION_MODULE_NOT_FOUND', [
                        'module_slug' => $moduleSlug,
                        'transaction_count' => $moduleTransactions->count(),
                    ]);
                    continue;
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
                    Log::warning('MIGRATION_NO_DATA_TO_MIGRATE', [
                        'module' => $module->name,
                        'transaction_count' => $moduleTransactions->count(),
                    ]);
                    continue;
                }

                try {
                    // Push to Accurate using bulk-save endpoint
                    $endpoint = str_replace('/list.do', '/bulk-save.do', $module->accurate_endpoint);
                    $chunks = array_chunk($bulkData, 100);
                    $chunkTransactions = array_chunk($moduleTransactions->all(), 100);
                    
                    Log::info('MIGRATION_CALLING_ACCURATE_API', [
                        'module' => $module->name,
                        'endpoint' => $endpoint,
                        'total_data_count' => count($bulkData),
                        'chunks_count' => count($chunks),
                        'target_database' => $targetDbName,
                    ]);

                    foreach ($chunks as $chunkIndex => $chunkData) {
                        Log::info('MIGRATION_PROCESSING_CHUNK', [
                            'module' => $module->name,
                            'chunk_index' => $chunkIndex + 1,
                            'chunk_size' => count($chunkData),
                            'total_chunks' => count($chunks),
                        ]);

                        $result = $this->accurateService->bulkSaveToAccurate($endpoint, $chunkData);
                        $itemResults = $result['d'] ?? [];
                        
                        if (!is_array($itemResults)) {
                            $itemResults = [$result];
                        }

                        foreach ($chunkTransactions[$chunkIndex] as $idx => $transaction) {
                            $itemResult = $itemResults[$idx] ?? null;
                            
                            if ($itemResult && isset($itemResult['s']) && $itemResult['s'] === true) {
                                $transaction->update([
                                    'status' => 'success',
                                    'migrated_at' => now(),
                                ]);
                                $successCount++;
                                
                                Log::info('MIGRATION_ITEM_SUCCESS', [
                                    'module' => $module->name,
                                    'chunk_index' => $chunkIndex + 1,
                                    'item_index' => $idx,
                                    'transaction_id' => $transaction->id,
                                    'message' => $itemResult['d'] ?? 'Success',
                                ]);
                            } else {
                                $errorData = $itemResult['d'] ?? ['Unknown error'];
                                
                                if (is_array($errorData)) {
                                    $flattenedErrors = [];
                                    array_walk_recursive($errorData, function($item) use (&$flattenedErrors) {
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
                                
                                Log::error('MIGRATION_ITEM_FAILED', [
                                    'module' => $module->name,
                                    'chunk_index' => $chunkIndex + 1,
                                    'item_index' => $idx,
                                    'transaction_id' => $transaction->id,
                                    'error' => $errorText,
                                ]);
                                
                                $errors[] = "{$module->name} - Transaction {$transaction->transaction_no}: {$errorText}";
                            }
                        }

                        Log::info('MIGRATION_CHUNK_PROCESSED', [
                            'module' => $module->name,
                            'chunk_index' => $chunkIndex + 1,
                            'total_items' => count($chunkTransactions[$chunkIndex]),
                            'current_success_count' => $successCount,
                            'current_failed_count' => $failedCount,
                        ]);
                    }

                    $moduleSuccessCount = $moduleTransactions->filter(function($t) {
                        return $t->fresh()->status === 'success';
                    })->count();

                    $moduleFailedCount = $moduleTransactions->filter(function($t) {
                        return $t->fresh()->status === 'failed';
                    })->count();

                    Log::info('MIGRATION_MODULE_COMPLETED', [
                        'module' => $module->name,
                        'success_count' => $moduleSuccessCount,
                        'failed_count' => $moduleFailedCount,
                        'total_success_so_far' => $successCount,
                        'total_failed_so_far' => $failedCount,
                    ]);
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
                    }

                    $errors[] = "{$module->name}: {$e->getMessage()}";
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
            $message = "Migration completed: {$successCount} succeeded";
            if ($failedCount > 0) {
                $message .= ", {$failedCount} failed";
            }
            if ($skippedCount > 0) {
                $message .= ", {$skippedCount} skipped";
            }

            if (!empty($errors)) {
                $message .= ". Errors: " . implode('; ', $errors);
            }

            $status = $failedCount > 0 ? 'error' : 'success';

            Log::info('MIGRATION_COMPLETED', [
                'user_id' => Auth::id(),
                'target_database' => $targetDbName,
                'total_transactions' => count($ids),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'skipped_count' => $skippedCount,
                'status' => $status,
                'errors' => $errors,
            ]);

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
