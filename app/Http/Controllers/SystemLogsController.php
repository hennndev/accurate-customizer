<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;

class SystemLogsController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemLog::with(['user', 'transaction'])->orderBy('created_at', 'desc');

        // Filter by search (message or event_type)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('event_type', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        // Filter by event type
        if ($request->filled('event_type') && $request->event_type !== 'All Types') {
            $query->where('event_type', strtolower($request->event_type));
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', strtolower($request->status));
        }

        $logs = $query->get();

        // Calculate statistics
        $totalEvents = SystemLog::count();
        $successCount = SystemLog::where('status', 'success')->count();
        $failedCount = SystemLog::where('status', 'failed')->count();
        $infoCount = SystemLog::where('status', 'info')->count();
        $warningCount = SystemLog::where('status', 'warning')->count();
        
        $successRate = $totalEvents > 0 ? number_format(($successCount / $totalEvents) * 100, 1) : 0;

        return view('system-logs.index', compact(
            'logs',
            'totalEvents',
            'successCount',
            'failedCount',
            'infoCount',
            'warningCount',
            'successRate'
        ));
    }
}
