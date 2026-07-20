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
use Illuminate\Support\Facades\Cache;

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
      $request->session()->forget([
        'accurate_access_token',
        'accurate_refresh_token',
        'accurate_database',
        'accurate_database_list_cache',
        'database_id',
        'database_name',
        'accurate_host',
      ]);

      return redirect()->route('accurate.auth')->with('info', 'Sesi Accurate Anda telah berakhir. Silakan login Accurate ulang.');
    }
    $current_database_name = session('database_name');
    $current_database_id = session('database_id');
    $query = Transaction::query()
      ->with([
        'accurateDatabase:id,db_name',
        'module:id,name,slug',
      ])
      ->select([
        'id',
        'transaction_no',
        'accurate_database_id',
        'module_id',
        'data',
        'description',
        'status',
        'error_message',
        'migrated_at',
        'created_at',
      ])
      ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.transDate')) as trans_date_raw")
      ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.id')) as accurate_id_raw");
    $customerNameExpression = "customer_name_virtual";
    $programInjekExpression = "program_injek_virtual";
    $customerProgramExpression = "customer_program_virtual";
    $transDateExpression = "trans_date_virtual";

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

    if ($request->filled('customer_name')) {
      $query->whereRaw("{$customerNameExpression} = ?", [$request->input('customer_name')]);
    }

    if ($request->filled('program_value')) {
      $programValue = $request->input('program_value');
      $query->where(function ($q) use ($programInjekExpression, $customerProgramExpression, $programValue) {
        $q->whereRaw("{$programInjekExpression} = ?", [$programValue])
          ->orWhereRaw("{$customerProgramExpression} = ?", [$programValue]);
      });
    }

    if ($request->filled('jenis_transaksi')) {
      $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.transactionTypeName')) = ?", [$request->input('jenis_transaksi')]);
    }

    if ($request->filled('trans_date_start')) {
      $query->whereRaw("{$transDateExpression} >= ?", [$request->input('trans_date_start')]);
    }

    if ($request->filled('trans_date_end')) {
      $query->whereRaw("{$transDateExpression} <= ?", [$request->input('trans_date_end')]);
    }

    if ($request->boolean('only_duplicates')) {
      $query->whereIn('transaction_no', function ($sub) {
        $sub->select('transaction_no')
          ->from('transactions')
          ->whereNotNull('transaction_no')
          ->where('transaction_no', '!=', '')
          ->groupBy('transaction_no')
          ->havingRaw('COUNT(*) > 1');
      });
    }

    // Module-specific detail field search (JSON array traversal)
    // Config: module name → list of JSON paths to search in
    $moduleDetailSearchConfig = [
      'Sales Receipt' => ['$.detailInvoice[*].invoice.number', '$.detailInvoice[*].invoiceNo'],
      'Purchase Payment' => ['$.detailInvoice[*].invoice.number', '$.detailInvoice[*].invoiceNo'],
      'Sales Return' => ['$.invoiceNo', '$.salesInvoiceNo'],
      'Sales Invoice' => ['$.number'],
    ];

    if ($request->filled('detail_field_search') && $request->filled('module') && $request->module !== 'All Modules') {
      $detailSearch = $request->input('detail_field_search');
      $searchModule = $request->input('module');

      if (isset($moduleDetailSearchConfig[$searchModule])) {
        $jsonPaths = $moduleDetailSearchConfig[$searchModule];
        $query->where(function ($q) use ($detailSearch, $jsonPaths) {
          foreach ($jsonPaths as $jsonPath) {
            $q->orWhereRaw(
              "JSON_SEARCH(data, 'all', ?, NULL, ?) IS NOT NULL",
              ["%{$detailSearch}%", $jsonPath]
            );
          }
        });
      }
    }

    $sortDateDirection = strtolower((string) $request->input('sort_date', 'desc')) === 'asc' ? 'asc' : 'desc';
    $query->orderByRaw("{$transDateExpression} {$sortDateDirection}");
    $query->orderByDesc('id');

    $perPageOptions = [100, 200, 300, 400, 500, 1000, 2000];

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

    $transactionNos = $transactions->pluck('transaction_no')->filter()->unique()->toArray();
    $dbIds = $transactions->pluck('accurate_database_id')->filter()->unique()->toArray();

    if (!empty($transactionNos)) {
      $mappings = \App\Models\TransactionNumberMapping::whereIn('old_number', $transactionNos)
        ->get()
        ->groupBy(function ($item) {
          return $item->module_slug . '_' . $item->old_number;
        });

      foreach ($transactions as $transaction) {
        if ($transaction->module && $transaction->transaction_no) {
          $key = $transaction->module->slug . '_' . $transaction->transaction_no;
          $transaction->new_number = isset($mappings[$key]) ? $mappings[$key]->first()->new_number : null;
        }
      }
    }

    // Highlight duplicate numbers only within current page to avoid expensive full-table scan.
    $duplicateTransactionNos = $transactions
      ->pluck('transaction_no')
      ->filter(fn($number) => filled($number))
      ->countBy()
      ->filter(fn($count) => $count > 1)
      ->keys()
      ->flip()
      ->all();

    $filter_databases = Cache::remember('migrate:filter_databases:v1', now()->addMinutes(10), function () {
      return AccurateDatabase::pluck('db_name');
    });

    $modules = Cache::remember('migrate:modules:v1', now()->addMinutes(10), function () {
      return Module::pluck('name')->unique()->values();
    });

    $customerNames = Cache::remember('migrate:customer_names:v1', now()->addMinutes(5), function () use ($customerNameExpression) {
      return Transaction::query()
        ->selectRaw("DISTINCT {$customerNameExpression} AS value")
        ->whereRaw("{$customerNameExpression} IS NOT NULL")
        ->whereRaw("{$customerNameExpression} != ''")
        ->orderBy('value')
        ->pluck('value');
    });

    $programOptions = Cache::remember('migrate:program_options:v1', now()->addMinutes(5), function () use ($programInjekExpression, $customerProgramExpression) {
      $programInjekOptions = Transaction::query()
        ->selectRaw("DISTINCT {$programInjekExpression} AS value")
        ->whereRaw("{$programInjekExpression} IS NOT NULL")
        ->whereRaw("{$programInjekExpression} != ''")
        ->orderBy('value')
        ->pluck('value');

      $customerProgramOptions = Transaction::query()
        ->selectRaw("DISTINCT {$customerProgramExpression} AS value")
        ->whereRaw("{$customerProgramExpression} IS NOT NULL")
        ->whereRaw("{$customerProgramExpression} != ''")
        ->orderBy('value')
        ->pluck('value');

      return $programInjekOptions
        ->merge($customerProgramOptions)
        ->filter(fn($value) => filled($value))
        ->unique()
        ->sort()
        ->values();
    });

    $transactionTypeOptions = Cache::remember('migrate:transaction_types:v1', now()->addMinutes(5), function () {
      $options = Transaction::query()
        ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.transactionTypeName')) AS value")
        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.transactionTypeName')) IS NOT NULL")
        ->pluck('value')
        ->filter(fn($value) => filled($value) && strtolower($value) !== 'null')
        ->unique()
        ->sort()
        ->values();

      return $options->isEmpty() ? collect(['Jurnal Umum']) : $options;
    });

    return view('migrate.index', compact(
      'transactions',
      'databases',
      'filter_databases',
      'modules',
      'customerNames',
      'programOptions',
      'transactionTypeOptions',
      'current_database_name',
      'currentPerPage',
      'perPageOptions',
      'duplicateTransactionNos',
      'moduleDetailSearchConfig'
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

    return back()->with('delete_success', "Transaction {$transactionNo} berhasil dihapus.");
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
        return back()
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

      return back()
        ->with('edit_success', "Data transaksi {$transaction->transaction_no} berhasil diperbarui.");

    } catch (\Exception $e) {
      return back()
        ->with('error', 'Failed to update transaction: ' . $e->getMessage());
    }
  }

  public function transactionData(Transaction $transaction)
  {
    $decoded = is_array($transaction->data)
      ? $transaction->data
      : json_decode($transaction->data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
      return response()->json([
        'success' => true,
        'data' => [],
      ]);
    }

    return response()->json([
      'success' => true,
      'data' => $decoded,
    ]);
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

    return back()->with('delete_success', count($ids) . ' transaksi berhasil dihapus: ' . implode(', ', $transactionNumbers) . '.');
  }


  // MIGRATE KE ACCURATE
  public function migrateToAccurate(Request $request)
  {
    $request->validate([
      'ids' => 'required|array',
      'ids.*' => 'required|numeric|exists:transactions,id',
      'target_database_id' => 'required|numeric',
      'force_create' => 'nullable|boolean',
      'add_ju_suffix' => 'nullable|boolean',
      'target_numbers' => 'nullable|array',
      'numbering_mode' => 'nullable|string|in:accurate,original,custom',
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
        return back()->with('error', 'Failed to connect to target database. Please try again.');
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
      return back()->with('error', 'Failed to connect to database: ' . $e->getMessage());
    }

    $ids = $request->input('ids', []);
    $forceCreate = $request->boolean('force_create', false);
    $addJuSuffix = $request->boolean('add_ju_suffix', false);
    $targetNumbers = $request->input('target_numbers', []);
    $numberingMode = $request->input('numbering_mode', 'accurate');

    $tracker = SystemLog::create([
      'event_type' => 'migrate_queue',
      'module' => 'System',
      'status' => 'queued',
      'payload' => [
        'target_database_id' => $targetDbId,
        'target_database_name' => $targetDbName,
        'transaction_ids' => $ids,
        'force_create' => $forceCreate,
        'add_ju_suffix' => $addJuSuffix,
        'target_numbers' => $targetNumbers,
        'numbering_mode' => $numberingMode,
        'total_selected' => count($ids),
        'progress' => 0,
      ],
      'message' => 'Migration queued',
      'user_id' => Auth::id(),
    ]);

    \Illuminate\Support\Facades\Log::info('MIGRATE_REQUEST_QUEUED', [
      'target_database_id' => $targetDbId,
      'target_database_name' => $targetDbName,
      'total_transactions' => count($ids),
      'force_create' => $forceCreate,
      'add_ju_suffix' => $addJuSuffix,
      'target_numbers' => $targetNumbers,
      'numbering_mode' => $numberingMode,
      'tracker_id' => $tracker->id,
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
      forceCreate: $forceCreate,
      addJuSuffix: $addJuSuffix,
      targetNumbers: $targetNumbers,
      numberingMode: $numberingMode
    )->onQueue('migrate');

    if ($request->expectsJson() || $request->ajax()) {
      return response()->json([
        'success' => true,
        'queued' => true,
        'message' => 'Migration queued',
        'monitor_id' => $tracker->id,
      ]);
    }

    return back()->with(
      'success',
      "Migration queued. Monitor ID: {$tracker->id}. Cek progress di System Logs."
    );
  }
  public function previewCustomNumbering(Request $request)
  {
    $request->validate([
      'ids' => 'required|array',
      'ids.*' => 'required|numeric|exists:transactions,id',
      'target_database_id' => 'required|numeric',
    ]);

    $ids = $request->input('ids');
    $targetDbId = $request->input('target_database_id');

    // Mengurutkan sesuai dengan urutan array $ids
    $idOrder = array_flip($ids);
    $transactions = Transaction::with('module')->whereIn('id', $ids)->get()->sortBy(function ($t) use ($idOrder) {
      return $idOrder[$t->id] ?? 999999;
    })->values();

    $prefixes = [
      'sales-invoice' => 'SI',
      'purchase-invoice' => 'PI',
      'sales-receipt' => 'SR',
      'purchase-payment' => 'PP',
      'journal-voucher' => 'JV',
      'receive-item' => 'RI',
      'item-adjustment' => 'IA',
      'delivery-order' => 'DO',
      'sales-order' => 'SO',
      'purchase-order' => 'PO',
      'sales-quotation' => 'SQ',
      'sales-return' => 'SRT',
      'purchase-return' => 'PRT',
      'purchase-requisition' => 'PRQ',
      'other-deposit' => 'OD',
      'other-payment' => 'OP',
    ];

    $grouped = $transactions->groupBy(function ($t) {
      $data = is_string($t->data) ? json_decode($t->data, true) : (array) $t->data;
      $date = $data['transDate'] ?? null;
      if ($date) {
        try {
          $date = \Carbon\Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        } catch (\Exception $e) {
          try {
            $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
          } catch (\Exception $e) {
          }
        }
      }
      return ($t->module->slug ?? '') . '|' . $date;
    });

    $previewData = [];

    foreach ($grouped as $key => $groupTransactions) {
      $parts = explode('|', $key);
      $moduleSlug = $parts[0];
      $dateStr = $parts[1] ?? '';

      if (!$dateStr) {
        foreach ($groupTransactions as $t) {
          $data = is_string($t->data) ? json_decode($t->data, true) : (array) $t->data;
          $previewData[] = [
            'id' => $t->id,
            'old_number' => $t->transaction_no,
            'module_name' => $t->module->name ?? 'Unknown',
            'trans_date' => $data['transDate'] ?? '-',
            'generated_number' => ''
          ];
        }
        continue;
      }

      $prefix = $prefixes[$moduleSlug] ?? strtoupper(substr($moduleSlug, 0, 2));
      $dateFormatted = \Carbon\Carbon::parse($dateStr)->format('Y.m.d');
      $baseFormat = "{$prefix}.{$dateFormatted}.";

      $maxMapping = \App\Models\TransactionNumberMapping::where('accurate_database_id', $targetDbId)
        ->where('module_slug', $moduleSlug)
        ->where('new_number', 'LIKE', $baseFormat . '%')
        ->orderByRaw('CAST(SUBSTRING_INDEX(new_number, ".", -1) AS UNSIGNED) DESC')
        ->first();

      $currentSequence = 0;
      if ($maxMapping && preg_match('/\.(\d+)$/', $maxMapping->new_number, $matches)) {
        $currentSequence = (int) $matches[1];
      }

      $sortedTransactions = $groupTransactions->sortBy('id');

      foreach ($sortedTransactions as $t) {
        $existingMapping = \App\Models\TransactionNumberMapping::where('accurate_database_id', $targetDbId)
          ->where('module_slug', $moduleSlug)
          ->where('old_number', $t->transaction_no)
          ->first();

        $data = is_string($t->data) ? json_decode($t->data, true) : (array) $t->data;
        $generated = '';

        $isMaster = ($t->module?->type === 'master') || in_array($moduleSlug, [
          'customer', 'vendor', 'item', 'branch', 'department', 'employee', 'warehouse', 'project',
          'customer-category', 'vendor-category', 'item-category', 'price-category', 'data-classification',
          'vendor-price', 'glaccount', 'currency', 'tax', 'unit', 'fob', 'bill-of-material'
        ], true);

        if ($isMaster) {
          $generated = $data['no'] ?? $data['vendorNo'] ?? $data['customerNo'] ?? $t->transaction_no;
        } elseif ($existingMapping && $existingMapping->new_number) {
          $generated = $existingMapping->new_number;
        } else {
          $currentSequence++;
          $generated = $baseFormat . str_pad($currentSequence, 3, '0', STR_PAD_LEFT);
        }

        $oldNumberDisplay = ($moduleSlug === 'glaccount' && !empty($data['no'])) ? $data['no'] : $t->transaction_no;

        $previewData[] = [
          'id' => $t->id,
          'old_number' => $oldNumberDisplay,
          'module_name' => $t->module->name ?? 'Unknown',
          'trans_date' => $data['transDate'] ?? '-',
          'generated_number' => $generated
        ];
      }
    }

    // Sort previewData to match the original $ids order requested by UI
    usort($previewData, function ($a, $b) use ($idOrder) {
      $orderA = $idOrder[$a['id']] ?? 999999;
      $orderB = $idOrder[$b['id']] ?? 999999;
      return $orderA <=> $orderB;
    });

    return response()->json(['success' => true, 'data' => $previewData]);
  }

  public function clearAllTransactions()
  {
    try {
      \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
      \App\Models\Transaction::truncate();
      \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
      return response()->json([
        "success" => true,
        "message" => "All transaction data has been permanently deleted."
      ]);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
      return response()->json([
        "success" => false,
        "message" => "Error deleting transactions: " . $e->getMessage()
      ], 500);
    }
  }
}