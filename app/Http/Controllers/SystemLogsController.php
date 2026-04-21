<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemLogsController extends Controller
{
    private function resolveStaleQueueTracker(SystemLog $log): SystemLog
    {
        if (!in_array($log->event_type, ['capture_queue', 'migrate_queue'], true)) {
            return $log;
        }

        if (!in_array($log->status, ['queued', 'running'], true)) {
            return $log;
        }

        $staleAfterSeconds = (int) env('QUEUE_MONITOR_STALE_SECONDS', 180);
        if ($staleAfterSeconds < 30) {
            $staleAfterSeconds = 30;
        }

        $updatedAt = $log->updated_at ? Carbon::parse($log->updated_at) : null;
        if (!$updatedAt) {
            return $log;
        }

        if ($updatedAt->lt(now()->subSeconds($staleAfterSeconds))) {
            $payload = is_array($log->payload) ? $log->payload : [];
            $payload['stale_timeout_seconds'] = $staleAfterSeconds;
            $payload['stale_marked_at'] = now()->toDateTimeString();

            $log->update([
                'status' => 'failed',
                'message' => 'Queue monitor dihentikan otomatis karena tidak ada update dari worker/job.',
                'payload' => $payload,
            ]);

            $log->refresh();
        }

        return $log;
    }

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

    public function status(SystemLog $log)
    {
        $log = $this->resolveStaleQueueTracker($log);

        return response()->json([
            'success' => true,
            'id' => $log->id,
            'event_type' => $log->event_type,
            'module' => $log->module,
            'status' => $log->status,
            'message' => $log->message,
            'payload' => $log->payload,
            'updated_at' => $log->updated_at,
        ]);
    }

    public function active(Request $request)
    {
        $eventType = $request->input('event_type');

        SystemLog::query()
            ->whereIn('event_type', ['capture_queue', 'migrate_queue'])
            ->whereIn('status', ['queued', 'running'])
            ->where('user_id', Auth::id())
            ->when($eventType, function ($q) use ($eventType) {
                $q->where('event_type', $eventType);
            })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->each(function (SystemLog $item) {
                $this->resolveStaleQueueTracker($item);
            });

        $query = SystemLog::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at');

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $log = $query->first();

        if (!$log) {
            return response()->json([
                'success' => true,
                'active' => false,
                'log' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'active' => true,
            'log' => [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'module' => $log->module,
                'status' => $log->status,
                'message' => $log->message,
                'payload' => $log->payload,
                'updated_at' => $log->updated_at,
            ],
        ]);
    }
}
