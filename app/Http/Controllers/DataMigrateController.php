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

    public function index(Request $request)
    {
        // Get databases from Accurate API (same as ModulesController)
        $databases = $this->accurateService->getDatabaseList();
        $current_database_name = session('database_name');
        $current_database_id = session('database_id');

        // Build query (don't filter by session database - session is only for showing active database)
        $query = Transaction::with(['accurateDatabase', 'module']);

        // Filter by search (transaction_no or description)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('transaction_no', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by source database (from dropdown filter)
        if ($request->filled('source_db') && $request->source_db !== 'All Database') {
            $accurateDb = AccurateDatabase::where('db_name', $request->source_db)->first();
            if ($accurateDb) {
                $query->where('accurate_database_id', $accurateDb->id);
            }
        }

        // Filter by module
        if ($request->filled('module') && $request->module !== 'All Modules') {
            $moduleRecord = Module::where('name', $request->module)->first();
            if ($moduleRecord) {
                $query->where('module_id', $moduleRecord->id);
            }
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', strtolower($request->status));
        }

        $transactions = $query->get();
        
        // Get unique databases and modules for filter dropdowns
        $filter_databases = AccurateDatabase::pluck('db_name');
        $modules = Module::pluck('name')->unique();

        return view('migrate.index', compact('transactions', 'databases', 'filter_databases', 'modules', 'current_database_name'));
    }

    public function destroy(Transaction $transaction)
    {
        // Store transaction info before delete
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

    public function destroyMultiple(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:transactions,id'
        ]);
        
        $ids = $request->input('ids', []);
        
        // Get transactions info before delete
        $transactions = Transaction::with('module')->whereIn('id', $ids)->get();
        $transactionNumbers = $transactions->pluck('transaction_no')->toArray();
        $modules = $transactions->map(fn($t) => $t->module?->name ?? 'N/A')->unique()->toArray();
        
        // Delete transactions
        Transaction::whereIn('id', $ids)->delete();
        
        // Create system log
        SystemLog::create([
            'event_type' => 'mass delete',
            'module' => implode(', ', $modules),
            'transaction_id' => null, // Null for bulk delete
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
}
