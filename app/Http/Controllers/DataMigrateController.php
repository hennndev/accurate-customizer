<?php

namespace App\Http\Controllers;

use App\Jobs\MigrateTransactionsJob;
use App\Models\Transaction;
use App\Models\SystemLog;
use App\Models\AccurateDatabase;
use App\Models\Module;
use App\Models\Setting;
use App\Services\AccurateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    try {
      $databases = $this->accurateService->getDatabaseList();
    } catch (\Exception $e) {
      Auth::guard('web')->logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();
      $request->session()->forget([
        'accurate_access_token',
        'accurate_refresh_token',
        'accurate_database',
        'accurate_database_list_cache',
        'database_id',
        'database_name',
        'accurate_host',
      ]);

      return redirect()->route('login')->with('info', 'Sesi Accurate Anda telah berakhir. Silakan login ulang.');
    }
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

    $perPageOptions = [100, 200, 300, 400, 500];

    $setting = Setting::first();
    if (!$setting) {
      $setting = Setting::create([
        'retention_days' => 7,
        'migrate_per_page' => 100,
      ]);
    }

    $requestedPerPage = (int) $request->input('per_page', 0);

    if (in_array($requestedPerPage, $perPageOptions, true)) {
      $currentPerPage = $requestedPerPage;
      if ((int) $setting->migrate_per_page !== $currentPerPage) {
        $setting->migrate_per_page = $currentPerPage;
        $setting->save();
      }
    } else {
      $storedPerPage = (int) ($setting->migrate_per_page ?? 100);
      $currentPerPage = in_array($storedPerPage, $perPageOptions, true) ? $storedPerPage : 100;
    }

    $transactions = $query->paginate($currentPerPage)->appends($request->except('page'));

    $filter_databases = AccurateDatabase::pluck('db_name');
    $modules = Module::pluck('name')->unique();
    return view('migrate.index', compact(
      'transactions',
      'databases',
      'filter_databases',
      'modules',
      'current_database_name',
      'currentPerPage',
      'perPageOptions'
    ));
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

    return redirect()->route('migrate.index')->with('delete_success', "Transaction {$transactionNo} berhasil dihapus.");
  }

  // UPDATE TRANSACTION DATA (JSON)
  public function update(Request $request, Transaction $transaction)
  {
    $request->validate([
      'data' => 'required|json'
    ]);

    try {
      // Parse JSON to validate it's valid JSON
      $jsonData = json_decode($request->data, true);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        return redirect()->route('migrate.index')
          ->with('error', 'Invalid JSON format: ' . json_last_error_msg());
      }

      $oldData = $transaction->data;
      $transaction->data = json_encode($jsonData);
      $transaction->save();

      SystemLog::create([
        'event_type' => 'update',
        'module' => $transaction->module ? $transaction->module->name : 'N/A',
        'status' => 'success',
        'payload' => [
          'transaction_no' => $transaction->transaction_no,
          'transaction_id' => $transaction->id,
          'old_data' => $oldData,
          'new_data' => $transaction->data,
          'updated_at' => now()->toDateTimeString(),
        ],
        'message' => "Transaction {$transaction->transaction_no} data updated successfully",
        'user_id' => Auth::id(),
      ]);

      return redirect()->route('migrate.index')
        ->with('edit_success', "Data transaksi {$transaction->transaction_no} berhasil diperbarui.");
        
    } catch (\Exception $e) {
      return redirect()->route('migrate.index')
        ->with('error', 'Failed to update transaction: ' . $e->getMessage());
    }
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

    return redirect()->route('migrate.index')->with('delete_success', count($ids) . ' transaksi berhasil dihapus: ' . implode(', ', $transactionNumbers) . '.');
  }


  // MIGRATE KE ACCURATE
  public function migrateToAccurate(Request $request)
  {
    $request->validate([
      'ids' => 'required|array',
      'ids.*' => 'required|integer|exists:transactions,id',
      'target_database_id' => 'required|integer'
    ]);

    $targetDbId = $request->input('target_database_id');
    
    // Get database info from Accurate API and open fresh session for target database
    try {
      $dbInfo = $this->accurateService->openDatabaseById($targetDbId);
      if (!$dbInfo) {
        if ($request->expectsJson() || $request->ajax()) {
          return response()->json([
            'success' => false,
            'message' => 'Failed to connect to target database. Please try again.',
          ], 422);
        }
        return redirect()->route('migrate.index')->with('error', 'Failed to connect to target database. Please try again.');
      }
      $targetDbName = $dbInfo['name'] ?? $dbInfo['alias'] ?? 'Unknown Database';

      // Inject the local AccurateDatabase.id so getAccurateDatabaseId always
      // uses a stable local PK (instead of session-based fallback which may
      // return the SOURCE database id or the Accurate API's raw db_id).
      $targetLocalDb = AccurateDatabase::firstOrCreate(
        ['db_id' => $targetDbId],
        ['db_name' => $targetDbName]
      );
      $dbInfo['_local_db_id'] = $targetLocalDb->id;
    } catch (\Exception $e) {
      if ($request->expectsJson() || $request->ajax()) {
        return response()->json([
          'success' => false,
          'message' => 'Failed to connect to database: ' . $e->getMessage(),
        ], 500);
      }
      return redirect()->route('migrate.index')->with('error', 'Failed to connect to database: ' . $e->getMessage());
    }

    $ids = $request->input('ids', []);

    $tracker = SystemLog::create([
      'event_type' => 'migrate_queue',
      'module' => 'Multiple Modules',
      'transaction_id' => null,
      'status' => 'queued',
      'payload' => [
        'target_database_id' => $targetDbId,
        'target_database_name' => $targetDbName,
        'transaction_ids' => $ids,
        'total_selected' => count($ids),
        'progress' => 0,
      ],
      'message' => 'Migration queued',
      'user_id' => Auth::id(),
    ]);

    MigrateTransactionsJob::dispatch(
      transactionIds: $ids,
      targetDatabaseId: $targetDbId,
      targetDatabaseName: $targetDbName,
      targetDbInfo: $dbInfo,
      userId: Auth::id(),
      trackerLogId: $tracker->id,
      accessToken: session('accurate_access_token'),
    )->onQueue('migrate');

    if ($request->expectsJson() || $request->ajax()) {
      return response()->json([
        'success' => true,
        'queued' => true,
        'message' => 'Migration queued',
        'monitor_id' => $tracker->id,
      ]);
    }

    return redirect()->route('migrate.index')->with(
      'success',
      "Migration queued. Monitor ID: {$tracker->id}. Cek progress di System Logs."
    );
  }
}
