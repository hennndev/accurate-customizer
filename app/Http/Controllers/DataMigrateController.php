<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataMigrateController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::query();

        // Filter by search (transaction_no or description)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('transaction_no', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by source database
        if ($request->filled('source_db') && $request->source_db !== 'All Database') {
            $query->where('source_db', $request->source_db);
        }

        // Filter by module
        if ($request->filled('module') && $request->module !== 'All Modules') {
            $query->where('module', $request->module);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', strtolower($request->status));
        }

        $transactions = $query->get();
        
        // Get unique databases and modules for dropdowns
        $databases = Transaction::select('source_db')->distinct()->pluck('source_db');
        $modules = Transaction::select('module')->distinct()->pluck('module');

        return view('migrate.index', compact('transactions', 'databases', 'modules'));
    }

    public function destroy(Transaction $transaction)
    {
        // Store transaction info before delete
        $transactionNo = $transaction->transaction_no;
        $module = $transaction->module;
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
        $transactions = Transaction::whereIn('id', $ids)->get();
        $transactionNumbers = $transactions->pluck('transaction_no')->toArray();
        $modules = $transactions->pluck('module')->unique()->toArray();
        
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
