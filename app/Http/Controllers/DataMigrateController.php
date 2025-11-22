<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

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
        $transaction->delete();
        return redirect()->route('migrate.index')->with('success', 'Transaction deleted successfully.');
    }

    public function destroyMultiple(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:transactions,id'
        ]);
        
        $ids = $request->input('ids', []);
        Transaction::whereIn('id', $ids)->delete();
        
        return redirect()->route('migrate.index')->with('success', count($ids) . ' transaction(s) deleted successfully.');
    }
}
