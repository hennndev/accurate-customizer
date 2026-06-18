<?php

namespace App\Http\Controllers;

use App\Jobs\MigrateTransactionsJob;
use App\Models\SystemLog;
use App\Services\AccurateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SystemLogsController extends Controller
{
    protected $accurateService;

    public function __construct(AccurateService $accurateService)
    {
        $this->accurateService = $accurateService;
    }

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

    public function resume(SystemLog $log)
    {
        if (!in_array($log->event_type, ['migrate_queue', 'capture_queue'], true)) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only migrate_queue and capture_queue can be resumed.'], 400);
            }
            return back()->with('error', 'Only migrate_queue and capture_queue can be resumed.');
        }

        if (!in_array($log->status, ['failed', 'warning', 'partial'], true)) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only failed, warning, or partial jobs can be resumed.'], 400);
            }
            return back()->with('error', 'Only failed, warning, or partial jobs can be resumed.');
        }

        if ((int) $log->user_id !== (int) Auth::id()) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
            return back()->with('error', 'Forbidden.');
        }

        $payload = is_array($log->payload) ? $log->payload : [];
        $targetDbId = $payload['target_database_id'] ?? $payload['database_id'] ?? null;
        $targetDbName = $payload['target_database_name'] ?? $payload['database_name'] ?? 'Unknown Database';

        if (!$targetDbId) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid payload data for resume.'], 400);
            }
            return back()->with('error', 'Invalid payload data for resume.');
        }

        try {
            $dbInfo = $this->accurateService->openDatabaseById($targetDbId);
            if (!$dbInfo) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to connect to target database. Please try again.',
                    ], 422);
                }
                return back()->with('error', 'Failed to connect to target database. Please try again.');
            }
            
            $targetLocalDb = \App\Models\AccurateDatabase::firstOrCreate(
                ['db_id' => $targetDbId],
                ['db_name' => $targetDbName]
            );
            $dbInfo['_local_db_id'] = $targetLocalDb->id;
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to database: ' . $e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Failed to connect to database: ' . $e->getMessage());
        }

        $payload['resumed_at'] = now()->toDateTimeString();

        if ($log->event_type === 'migrate_queue') {
            $transactionIds = $payload['transaction_ids'] ?? [];
            $forceCreate = $payload['force_create'] ?? false;

            if (empty($transactionIds)) {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Invalid payload data for resume.'], 400);
                }
                return back()->with('error', 'Invalid payload data for resume.');
            }

            $log->update([
                'status' => 'queued',
                'message' => 'Migration resumed',
                'payload' => $payload,
            ]);

            \App\Jobs\MigrateTransactionsJob::dispatch(
                transactionIds: $transactionIds,
                targetDatabaseId: $targetDbId,
                targetDatabaseName: $targetDbName,
                targetDbInfo: $dbInfo,
                userId: Auth::id(),
                trackerLogId: $log->id,
                accessToken: session('accurate_access_token'),
                forceCreate: $forceCreate,
            )->onQueue('migrate');
        } elseif ($log->event_type === 'capture_queue') {
            $moduleSlug = $payload['module_slug'] ?? null;
            $startPage = $payload['next_page'] ?? $payload['start_page'] ?? 1; // Resumes from the last known next_page!
            $pageSize = $payload['page_size'] ?? 20;
            $captureMode = $payload['capture_mode'] ?? 'list_and_detail';
            $useListIdCache = $payload['use_list_id_cache'] ?? true;
            $refreshListIdCache = $payload['refresh_list_id_cache'] ?? false;
            $params = $payload['params'] ?? [];
            $moduleInfo = $payload['module_info'] ?? [];

            if (!$moduleSlug || empty($moduleInfo)) {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Missing module details in payload. Resume not possible for this older job.'], 400);
                }
                return back()->with('error', 'Missing module details in payload. Resume not possible for this older job.');
            }

            $cancelToken = 'capture-cancel:' . $log->id;
            cache()->forget($cancelToken);

            $log->update([
                'status' => 'queued',
                'message' => 'Capture resumed from page ' . $startPage,
                'payload' => $payload,
            ]);

            \App\Jobs\CaptureModuleJob::dispatch(
                module: $moduleSlug,
                moduleInfo: $moduleInfo,
                params: $params,
                pageSize: $pageSize,
                startPage: $startPage,
                databaseId: $targetLocalDb->id,
                databaseName: $targetDbName,
                userId: Auth::id(),
                trackerLogId: $log->id,
                accessToken: session('accurate_access_token'),
                sourceDbInfo: $dbInfo,
                cancelToken: $cancelToken,
                captureMode: $captureMode,
                useListIdCache: $useListIdCache,
                refreshListIdCache: $refreshListIdCache,
            )->onQueue('capture');
        }

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Job resumed successfully.',
            ]);
        }

        return back()->with('success', 'Job resumed successfully.');
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