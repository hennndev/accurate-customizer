<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SystemLogsController extends Controller
{
    private function resolveStaleQueueTracker(SystemLog $log): SystemLog
    {
        if (!in_array($log->event_type, ['capture_queue', 'migrate_queue', 'transaction_number_mapping_queue'], true)) {
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
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%");
            });
        }

        if ($request->filled('queue_status') && $request->queue_status !== 'all') {
            $query->where('status', $request->queue_status);
        }

        // Filter by event type
        if ($request->filled('event_type') && $request->event_type !== 'All Types') {
            $query->where('event_type', strtolower($request->event_type));
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', strtolower($request->status));
        }

        $logs = $query->paginate(20)->withQueryString();

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
        ])->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function active(Request $request)
    {
        $eventTypeInput = $request->input('event_type');
        $eventTypes = $eventTypeInput ? explode(',', $eventTypeInput) : [];

        SystemLog::query()
            ->whereIn('event_type', ['capture_queue', 'migrate_queue', 'transaction_number_mapping_queue'])
            ->whereIn('status', ['queued', 'running'])
            ->where('user_id', Auth::id())
            ->when(!empty($eventTypes), function ($q) use ($eventTypes) {
                $q->whereIn('event_type', $eventTypes);
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

        if (!empty($eventTypes)) {
            $query->whereIn('event_type', $eventTypes);
        }

        $log = $query->first();

        if (!$log) {
            return response()->json([
                'success' => true,
                'active' => false,
                'log' => null,
            ])->withHeaders([
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
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
        ])->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function queue(Request $request)
    {
        $query = SystemLog::query()
            ->whereIn('event_type', ['capture_queue', 'migrate_queue', 'transaction_number_mapping_queue'])
            ->orderByDesc('created_at');

        if ($request->filled('queue_status') && $request->queue_status !== 'all') {
            $query->where('status', $request->queue_status);
        }

        $logs = $query->get();

        return view('system-logs.queue', compact('logs'));
    }

    public function destroyMultiple(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->values()->all();

        if (empty($ids)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'No items selected'], 422);
            }

            return redirect()->back()->with('error', 'No items selected');
        }

        $logs = SystemLog::query()
            ->whereIn('id', $ids)
            ->whereIn('event_type', ['capture_queue', 'migrate_queue', 'transaction_number_mapping_queue'])
            ->where('user_id', Auth::id())
            ->get();

        $trackerIds = $logs->pluck('id')->all();

        foreach ($logs as $log) {
            if (in_array($log->status, ['queued', 'running'], true)) {
                cache()->put('capture-cancel:' . $log->id, true, now()->addHours(24));
            }
        }

        $deletedJobs = $this->deleteQueuedJobsByTrackerIds($trackerIds);

        foreach ($logs as $log) {
            cache()->forget('capture-cancel:' . $log->id);
        }

        SystemLog::query()->whereIn('id', $trackerIds)->delete();

        $message = 'Selected queue logs deleted';
        if ($deletedJobs > 0) {
            $message .= ' and ' . $deletedJobs . ' queued job(s) stopped';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    private function deleteQueuedJobsByTrackerIds(array $trackerIds): int
    {
        if (empty($trackerIds)) {
            return 0;
        }

        $jobs = DB::table('jobs')
            ->whereIn('queue', ['capture', 'migrate'])
            ->get(['id', 'payload']);

        $jobIdsToDelete = [];

        foreach ($jobs as $job) {
            $payload = json_decode($job->payload, true);
            $command = (string) data_get($payload, 'data.command', '');

            foreach ($trackerIds as $trackerId) {
                if (str_contains($command, 'trackerLogId";i:' . $trackerId . ';')) {
                    $jobIdsToDelete[] = $job->id;
                    break;
                }
            }
        }

        if (empty($jobIdsToDelete)) {
            return 0;
        }

        return DB::table('jobs')->whereIn('id', $jobIdsToDelete)->delete();
    }

    public function cancel(SystemLog $log)
    {
        if (!in_array($log->event_type, ['capture_queue', 'migrate_queue', 'transaction_number_mapping_queue'], true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported queue type'], 422);
        }

        if ((int) $log->user_id !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $payload = is_array($log->payload) ? $log->payload : [];
        $cancelToken = 'capture-cancel:' . $log->id;
        cache()->put($cancelToken, true, now()->addHours(24));

        $payload['cancelled'] = true;
        $payload['cancelled_at'] = now()->toDateTimeString();

        $log->update([
            'status' => 'failed',
            'message' => 'Capture dibatalkan oleh user.',
            'payload' => $payload,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Capture dibatalkan',
        ]);
    }

    public function clearAllLogs()
    {
        try {
            \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
            \App\Models\SystemLog::truncate();
            \Illuminate\Support\Facades\DB::table("jobs")->truncate();
            \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
            return response()->json([
                "success" => true,
                "message" => "All system logs and job queues have been permanently deleted."
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
            return response()->json([
                "success" => false,
                "message" => "Error deleting logs: " . $e->getMessage()
            ], 500);
        }
    }
}