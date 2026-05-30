<?php

namespace App\Http\Controllers;

use App\Jobs\CaptureTransactionNumberMappingJob;
use App\Models\DownPaymentPurchaseInvoiceMapping;
use App\Models\PurchaseInvoiceMapping;
use App\Models\SalesInvoiceMapping;
use App\Models\ReceiveItemMapping;
use App\Models\SystemLog;
use App\Services\AccurateService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TransactionNumberMappingController extends Controller
{
    public function index(Request $request, AccurateService $accurate)
    {
        $moduleMappings = $this->moduleMappings();
        $moduleOptions = collect($moduleMappings)
            ->mapWithKeys(fn (array $item, string $slug) => [$slug => $item['name']])
            ->all();

        $selectedModule = (string) $request->input('module', 'sales-invoice');
        if (!isset($moduleMappings[$selectedModule])) {
            $selectedModule = 'sales-invoice';
        }

        $mappingModelClass = $moduleMappings[$selectedModule]['mapping_model'];
        $query = $mappingModelClass::query();

        if ($request->filled('db_name')) {
            $query->where('db_name', $request->string('db_name'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('old_number', 'like', "%{$search}%")
                    ->orWhere('new_number', 'like', "%{$search}%");
            });
        }

        $mappings = $query
            ->orderBy('old_number')
            ->paginate(20)
            ->withQueryString();

        $dbOptions = $mappingModelClass::query()
            ->select('db_name')
            ->whereNotNull('db_name')
            ->where('db_name', '!=', '')
            ->distinct()
            ->orderBy('db_name')
            ->pluck('db_name')
            ->values();

        $captureDatabaseOptions = [];
        try {
            $databaseList = $accurate->getDatabaseList();
            foreach ($databaseList as $databaseItem) {
                if (!isset($databaseItem['id'], $databaseItem['alias'])) {
                    continue;
                }

                $captureDatabaseOptions[] = [
                    'id' => (int) $databaseItem['id'],
                    'name' => (string) $databaseItem['alias'],
                ];
            }
        } catch (\Throwable $exception) {
            $captureDatabaseOptions = [];
        }

        $selectedCaptureDatabaseId = (int) ($request->input('capture_database_id') ?: session()->get('database_id'));
        if ($selectedCaptureDatabaseId === 0 && !empty($captureDatabaseOptions)) {
            $selectedCaptureDatabaseId = (int) $captureDatabaseOptions[0]['id'];
        }

        return view('transaction-number-mappings.index', [
            'mappings' => $mappings,
            'moduleOptions' => $moduleOptions,
            'selectedModule' => $selectedModule,
            'dbOptions' => $dbOptions,
            'captureDatabaseOptions' => $captureDatabaseOptions,
            'selectedCaptureDatabaseId' => $selectedCaptureDatabaseId,
        ]);
    }

    public function capture(Request $request, AccurateService $accurate)
    {
        $module = (string) $request->input('module', 'sales-invoice');
        $moduleMapping = $this->moduleMappings();

        if (!isset($moduleMapping[$module])) {
            return response()->json([
                'success' => false,
                'message' => 'Module not supported',
            ], 422);
        }

        $accurateDbId = (int) $request->input('capture_database_id', session()->get('database_id'));
        if (!$accurateDbId) {
            return response()->json([
                'success' => false,
                'message' => 'No database selected',
            ], 400);
        }

        try {
            $databaseList = $accurate->getDatabaseList();
        } catch (\Throwable $exception) {
            $databaseList = [];
        }

        $selectedDatabase = collect($databaseList)
            ->first(function (array $databaseItem) use ($accurateDbId) {
                return (int) ($databaseItem['id'] ?? 0) === $accurateDbId;
            });

        if (!$selectedDatabase) {
            return response()->json([
                'success' => false,
                'message' => 'Database capture tidak ditemukan pada list database Accurate',
            ], 422);
        }

        $databaseName = (string) ($selectedDatabase['alias'] ?? '');

        $accessToken = session('accurate_access_token');

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session Accurate tidak ditemukan. Silakan login ulang dan pilih database lagi.',
            ], 401);
        }

        $sessionDbInfo = session('accurate_database');
        $sessionDbId = (int) ($sessionDbInfo['id'] ?? 0);
        $sourceDbInfo = $sessionDbId === $accurateDbId ? $sessionDbInfo : null;

        if (!$sourceDbInfo) {
            try {
                $sourceDbInfo = $accurate->openDatabaseById($accurateDbId);
            } catch (\Throwable $exception) {
                $sourceDbInfo = null;
            }
        }

        if (!$sourceDbInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuka database capture yang dipilih.',
            ], 422);
        }

        $params = $this->buildDateParams($request);
        $params['filter.invoiceDp'] = $module === 'down-payment-purchase-invoice';
        $params['fields'] = $moduleMapping[$module]['fields'];
        $params['sp.fields'] = $moduleMapping[$module]['fields'];

        $pageSize = (int) env('ACCURATE_CAPTURE_PAGE_SIZE', 20);
        if ($pageSize < 10) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $tracker = SystemLog::create([
            'event_type' => 'transaction_number_mapping_queue',
            'module' => $moduleMapping[$module]['name'],
            'transaction_id' => null,
            'status' => 'queued',
            'payload' => [
                'module_slug' => $module,
                'database_name' => $databaseName,
                'capture_mode' => 'list_only',
                'page_size' => $pageSize,
                'progress' => 0,
            ],
            'message' => "Queue capture number mapping {$moduleMapping[$module]['name']} created",
            'user_id' => Auth::id(),
        ]);

        $cancelToken = 'capture-cancel:' . $tracker->id;
        cache()->forget($cancelToken);

        CaptureTransactionNumberMappingJob::dispatch(
            moduleSlug: $module,
            moduleName: $moduleMapping[$module]['name'],
            listEndpoint: $moduleMapping[$module]['list_endpoint'],
            params: $params,
            pageSize: $pageSize,
            startPage: 1,
            databaseName: $databaseName,
            mappingModelClass: $moduleMapping[$module]['mapping_model'],
            trackerLogId: $tracker->id,
            userId: Auth::id(),
            accessToken: $accessToken,
            sourceDbInfo: $sourceDbInfo,
            cancelToken: $cancelToken,
        )->onQueue('capture');

        return response()->json([
            'success' => true,
            'queued' => true,
            'message' => "Capture {$moduleMapping[$module]['name']} dimasukkan ke queue",
            'monitor_id' => $tracker->id,
        ]);
    }

    private function buildDateParams(Request $request): array
    {
        $params = [];
        $filterType = (string) $request->input('filter_type', 'range');

        if ($filterType === 'equal') {
            if ($request->filled('start_date')) {
                $params['filter.transDate.op'] = 'EQUAL';
                $params['filter.transDate.val'] = Carbon::parse($request->start_date)->format('d/m/Y');
            }

            return $params;
        }

        if ($filterType === 'last_update_equal') {
            if ($request->filled('start_date')) {
                $startTime = $request->input('start_time', '00:00');
                $params['filter.lastUpdate.op'] = 'BETWEEN';
                $params['filter.lastUpdate.val[0]'] = Carbon::parse($request->start_date . ' ' . $startTime)->format('d/m/Y H:i:s');
                $params['filter.lastUpdate.val[1]'] = Carbon::parse($request->start_date . ' 23:59:59')->format('d/m/Y H:i:s');
            }

            return $params;
        }

        if ($filterType === 'last_update') {
            $hasStartDate = $request->filled('start_date');
            $hasEndDate = $request->filled('end_date');
            $startTime = $request->input('start_time', '00:00');
            $endTime = $request->input('end_time', '23:59');

            if ($hasStartDate && $hasEndDate) {
                $params['filter.lastUpdate.op'] = 'BETWEEN';
                $params['filter.lastUpdate.val[0]'] = Carbon::parse($request->start_date . ' ' . $startTime)->format('d/m/Y H:i:s');
                $params['filter.lastUpdate.val[1]'] = Carbon::parse($request->end_date . ' ' . $endTime)->format('d/m/Y H:i:s');
            } elseif ($hasStartDate) {
                $params['filter.lastUpdate.op'] = 'GREATER_EQUAL_THAN';
                $params['filter.lastUpdate.val'] = Carbon::parse($request->start_date . ' ' . $startTime)->format('d/m/Y H:i:s');
            } elseif ($hasEndDate) {
                $params['filter.lastUpdate.op'] = 'LESS_EQUAL_THAN';
                $params['filter.lastUpdate.val'] = Carbon::parse($request->end_date . ' ' . $endTime)->format('d/m/Y H:i:s');
            }

            return $params;
        }

        $hasStartDate = $request->filled('start_date');
        $hasEndDate = $request->filled('end_date');

        if ($hasStartDate && $hasEndDate) {
            $params['filter.transDate.op'] = 'BETWEEN';
            $params['filter.transDate.val[0]'] = Carbon::parse($request->start_date)->format('d/m/Y');
            $params['filter.transDate.val[1]'] = Carbon::parse($request->end_date)->format('d/m/Y');
        } elseif ($hasStartDate) {
            $params['filter.transDate.op'] = 'GREATER_EQUAL_THAN';
            $params['filter.transDate.val'] = Carbon::parse($request->start_date)->format('d/m/Y');
        } elseif ($hasEndDate) {
            $params['filter.transDate.op'] = 'LESS_EQUAL_THAN';
            $params['filter.transDate.val'] = Carbon::parse($request->end_date)->format('d/m/Y');
        }

        return $params;
    }

    private function moduleMappings(): array
    {
        return [
            'sales-invoice' => [
                'name' => 'Sales Invoice',
                'list_endpoint' => '/api/sales-invoice/list.do',
                'fields' => 'number,charField1',
                'mapping_model' => SalesInvoiceMapping::class,
            ],
            'purchase-invoice' => [
                'name' => 'Purchase Invoice',
                'list_endpoint' => '/api/purchase-invoice/list.do',
                'fields' => 'number,charField1',
                'mapping_model' => PurchaseInvoiceMapping::class,
            ],
            'down-payment-purchase-invoice' => [
                'name' => 'Down Payment Purchase Invoice',
                'list_endpoint' => '/api/purchase-invoice/list.do',
                'fields' => 'number,charField1,invoiceDp',
                'mapping_model' => DownPaymentPurchaseInvoiceMapping::class,
            ],
            'receive-item' => [
                'name' => 'Receive Item',
                'list_endpoint' => '/api/receive-item/list.do',
                'fields' => 'number,receiveNumber,charField1',
                'mapping_model' => ReceiveItemMapping::class,
            ],
        ];
    }
}
