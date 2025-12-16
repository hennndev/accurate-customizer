<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Transaction;
use App\Models\SystemLog;
use App\Services\AccurateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ModulesController extends Controller
{
  public function index(AccurateService $accurate, Request $request)
  {
    $dbList = $accurate->getDatabaseList();
    $currentDatabase = session()->get("database_name");

    // Get accurate database ID dari session (db_id dari Accurate)
    $accurateDbId = session()->get("database_id");

    // Cari primary key ID dari tabel accurate_databases
    $accurateDatabase = \App\Models\AccurateDatabase::where('db_id', $accurateDbId)->first();
    $databaseId = $accurateDatabase ? $accurateDatabase->id : null;

    $totalTransactions = $databaseId
      ? Transaction::where("accurate_database_id", $databaseId)->count()
      : 0;

    // Get all modules untuk database ini dengan transaction count
    $modules = $databaseId
      ? Module::where('accurate_database_id', $databaseId)
      ->withCount(['transactions' => function ($query) use ($databaseId) {
        $query->where('accurate_database_id', $databaseId);
      }])
      ->get()
      : collect([]);

    return view('modules.index', [
      'databases' => $dbList,
      'current_database_name' => $currentDatabase,
      'total_transactions' => $totalTransactions,
      'modules' => $modules,
    ]);
  }

  public function captureData(Request $request, AccurateService $accurate, $module)
  {
    // Set execution time limit to 5 minutes for large data capture
    set_time_limit(3000);

    // Mapping module slug ke Accurate API endpoint
    $moduleMapping = [
      // Transaction Modules
      'sales-order' => [
        'name' => 'Sales Order',
        'list_endpoint' => '/api/sales-order/list.do',
        'detail_endpoint' => '/api/sales-order/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'purchase-order' => [
        'name' => 'Purchase Order',
        'list_endpoint' => '/api/purchase-order/list.do',
        'detail_endpoint' => '/api/purchase-order/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'sales-invoice' => [
        'name' => 'Sales Invoice',
        'list_endpoint' => '/api/sales-invoice/list.do',
        'detail_endpoint' => '/api/sales-invoice/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'purchase-invoice' => [
        'name' => 'Purchase Invoice',
        'list_endpoint' => '/api/purchase-invoice/list.do',
        'detail_endpoint' => '/api/purchase-invoice/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'delivery-order' => [
        'name' => 'Delivery Order',
        'list_endpoint' => '/api/delivery-order/list.do',
        'detail_endpoint' => '/api/delivery-order/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'receive-item' => [
        'name' => 'Receive Item',
        'list_endpoint' => '/api/receive-item/list.do',
        'detail_endpoint' => '/api/receive-item/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],

      // Sales Related
      'sales-quotation' => [
        'name' => 'Sales Quotation',
        'list_endpoint' => '/api/sales-quotation/list.do',
        'detail_endpoint' => '/api/sales-quotation/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'sales-return' => [
        'name' => 'Sales Return',
        'list_endpoint' => '/api/sales-return/list.do',
        'detail_endpoint' => '/api/sales-return/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'sales-receipt' => [
        'name' => 'Sales Receipt',
        'list_endpoint' => '/api/sales-receipt/list.do',
        'detail_endpoint' => '/api/sales-receipt/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],

      // Purchase Related
      'purchase-return' => [
        'name' => 'Purchase Return',
        'list_endpoint' => '/api/purchase-return/list.do',
        'detail_endpoint' => '/api/purchase-return/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'purchase-payment' => [
        'name' => 'Purchase Payment',
        'list_endpoint' => '/api/purchase-payment/list.do',
        'detail_endpoint' => '/api/purchase-payment/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'purchase-requisition' => [
        'name' => 'Purchase Requisition',
        'list_endpoint' => '/api/purchase-requisition/list.do',
        'detail_endpoint' => '/api/purchase-requisition/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],

      // Inventory & Production
      'item-transfer' => [
        'name' => 'Item Transfer',
        'list_endpoint' => '/api/item-transfer/list.do',
        'detail_endpoint' => '/api/item-transfer/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'item-adjustment' => [
        'name' => 'Item Adjustment',
        'list_endpoint' => '/api/item-adjustment/list.do',
        'detail_endpoint' => '/api/item-adjustment/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'stock-opname-order' => [
        'name' => 'Stock Opname Order',
        'list_endpoint' => '/api/stock-opname-order/list.do',
        'detail_endpoint' => '/api/stock-opname-order/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'stock-opname-result' => [
        'name' => 'Stock Opname Result',
        'list_endpoint' => '/api/stock-opname-result/list.do',
        'detail_endpoint' => '/api/stock-opname-result/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'work-order' => [
        'name' => 'Work Order',
        'list_endpoint' => '/api/work-order/list.do',
        'detail_endpoint' => '/api/work-order/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'job-order' => [
        'name' => 'Job Order',
        'list_endpoint' => '/api/job-order/list.do',
        'detail_endpoint' => '/api/job-order/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'bill-of-material' => [
        'name' => 'Bill of Material',
        'list_endpoint' => '/api/bill-of-material/list.do',
        'detail_endpoint' => '/api/bill-of-material/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'material-adjustment' => [
        'name' => 'Material Adjustment',
        'list_endpoint' => '/api/material-adjustment/list.do',
        'detail_endpoint' => '/api/material-adjustment/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],

      // Financial
      'journal-voucher' => [
        'name' => 'Journal Voucher',
        'list_endpoint' => '/api/journal-voucher/list.do',
        'detail_endpoint' => '/api/journal-voucher/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'bank-transfer' => [
        'name' => 'Bank Transfer',
        'list_endpoint' => '/api/bank-transfer/list.do',
        'detail_endpoint' => '/api/bank-transfer/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'exchange-invoice' => [
        'name' => 'Exchange Invoice',
        'list_endpoint' => '/api/exchange-invoice/list.do',
        'detail_endpoint' => '/api/exchange-invoice/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'expense-accrual' => [
        'name' => 'Expense Accrual',
        'list_endpoint' => '/api/expense/list.do',
        'detail_endpoint' => '/api/expense/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'roll-over' => [
        'name' => 'Roll Over',
        'list_endpoint' => '/api/roll-over/list.do',
        'detail_endpoint' => '/api/roll-over/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],

      // Master Data
      'customer' => [
        'name' => 'Customer',
        'list_endpoint' => '/api/customer/list.do',
        'detail_endpoint' => '/api/customer/detail.do',
        'identifier_field' => 'customerNo',
        'type' => 'master',
      ],
      'vendor' => [
        'name' => 'Vendor',
        'list_endpoint' => '/api/vendor/list.do',
        'detail_endpoint' => '/api/vendor/detail.do',
        'identifier_field' => 'vendorNo',
        'type' => 'master',
      ],
      'item' => [
        'name' => 'Item',
        'list_endpoint' => '/api/item/list.do',
        'detail_endpoint' => '/api/item/detail.do',
        'identifier_field' => 'itemNo',
        'type' => 'master',
      ],
      'branch' => [
        'name' => 'Branch',
        'list_endpoint' => '/api/branch/list.do',
        'detail_endpoint' => '/api/branch/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'department' => [
        'name' => 'Department',
        'list_endpoint' => '/api/department/list.do',
        'detail_endpoint' => '/api/department/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'employee' => [
        'name' => 'Employee',
        'list_endpoint' => '/api/employee/list.do',
        'detail_endpoint' => '/api/employee/detail.do',
        'identifier_field' => 'employeeNo',
        'type' => 'master',
      ],
      'warehouse' => [
        'name' => 'Warehouse',
        'list_endpoint' => '/api/warehouse/list.do',
        'detail_endpoint' => '/api/warehouse/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'project' => [
        'name' => 'Project',
        'list_endpoint' => '/api/project/list.do',
        'detail_endpoint' => '/api/project/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],

      // Categories & Classifications
      'customer-category' => [
        'name' => 'Customer Category',
        'list_endpoint' => '/api/customer-category/list.do',
        'detail_endpoint' => '/api/customer-category/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'vendor-category' => [
        'name' => 'Vendor Category',
        'list_endpoint' => '/api/vendor-category/list.do',
        'detail_endpoint' => '/api/vendor-category/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'item-category' => [
        'name' => 'Item Category',
        'list_endpoint' => '/api/item-category/list.do',
        'detail_endpoint' => '/api/item-category/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'price-category' => [
        'name' => 'Price Category',
        'list_endpoint' => '/api/price-category/list.do',
        'detail_endpoint' => '/api/price-category/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'data-classification' => [
        'name' => 'Data Classification',
        'list_endpoint' => '/api/data-classification/list.do',
        'detail_endpoint' => '/api/data-classification/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],

      // Claims & Additional
      'customer-claim' => [
        'name' => 'Customer Claim',
        'list_endpoint' => '/api/customer-claim/list.do',
        'detail_endpoint' => '/api/customer-claim/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'vendor-claim' => [
        'name' => 'Vendor Claim',
        'list_endpoint' => '/api/vendor-claim/list.do',
        'detail_endpoint' => '/api/vendor-claim/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],
      'vendor-price' => [
        'name' => 'Vendor Price',
        'list_endpoint' => '/api/vendor-price/list.do',
        'detail_endpoint' => '/api/vendor-price/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'shipment' => [
        'name' => 'Shipment',
        'list_endpoint' => '/api/shipment/list.do',
        'detail_endpoint' => '/api/shipment/detail.do',
        'identifier_field' => 'number',
        'type' => 'transaction',
      ],

      // Configuration
      'glaccount' => [
        'name' => 'GL Account',
        'list_endpoint' => '/api/glaccount/list.do',
        'detail_endpoint' => '/api/glaccount/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'currency' => [
        'name' => 'Currency',
        'list_endpoint' => '/api/currency/list.do',
        'detail_endpoint' => '/api/currency/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'tax' => [
        'name' => 'Tax',
        'list_endpoint' => '/api/tax/list.do',
        'detail_endpoint' => '/api/tax/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'unit' => [
        'name' => 'Unit',
        'list_endpoint' => '/api/unit/list.do',
        'detail_endpoint' => '/api/unit/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
      'fob' => [
        'name' => 'FOB',
        'list_endpoint' => '/api/fob/list.do',
        'detail_endpoint' => '/api/fob/detail.do',
        'identifier_field' => 'name',
        'type' => 'master',
      ],
    ];

    if (!isset($moduleMapping[$module])) {
      return response()->json([
        'success' => false,
        'message' => 'Module not found'
      ], 404);
    }

    try {
      $moduleInfo = $moduleMapping[$module];
      $accurateDbId = session()->get('database_id');

      if (!$accurateDbId) {
        return response()->json([
          'success' => false,
          'message' => 'No database selected'
        ], 400);
      }

      $accurateDatabase = \App\Models\AccurateDatabase::where('db_id', $accurateDbId)->first();
      if (!$accurateDatabase) {
        return response()->json([
          'success' => false,
          'message' => 'Database not found in records'
        ], 404);
      }

      $databaseId = $accurateDatabase->id;
      $listData = $accurate->fetchModuleData($moduleInfo['list_endpoint']);
      // Log::info('LIST_RESULT_CAPTURE_DATA', [
      //   "resut" => $listData,
      // ]);
      // Log::info('ACCURATE_MODULE_LIST', [
      //   'module' => $module,
      //   'total_items' => count($listData),
      // ]);
      $hasData = count($listData) > 0;
      $moduleRecord = Module::firstOrCreate(
        [
          'accurate_database_id' => $databaseId,
          'slug' => $module,
        ],
        [
          'name' => $moduleInfo['name'],
          'icon' => 'heroicon-o-document-text', // default icon
          'description' => $moduleInfo['name'],
          'accurate_endpoint' => $moduleInfo['list_endpoint'],
          'is_active' => $hasData, // active jika ada data
          'order' => 0,
        ]
      );

      if (!$moduleRecord->wasRecentlyCreated) {
        $moduleRecord->update([
          'is_active' => $hasData,
        ]);
      }

      Log::info('MODULE_CREATED_OR_UPDATED', [
        'module' => $module,
        'database_id' => $databaseId,
        'has_data' => $hasData,
        'is_active' => $moduleRecord->is_active,
        'was_created' => $moduleRecord->wasRecentlyCreated,
      ]);

      $savedCount = 0;
      $skippedCount = 0;
      $failedCount = 0;
      $savedTransactionNumbers = [];

      if ($hasData) {
        foreach ($listData as $item) {
          try {
            $itemId = $item['id'] ?? null;

            if (!$itemId) {
              Log::warning('ACCURATE_ITEM_NO_ID', ['item' => $item]);
              continue;
            }

            $detailData = $accurate->fetchModuleData($moduleInfo['detail_endpoint'], [
              'id' => $itemId
            ]);

            if ($module === 'journal-voucher') {

              if (isset($detailData['branchId'])) {
                $rootBranchId = $detailData['branchId'];
                $foundBranchName = null;
                
                if (isset($detailData['detailJournalVoucher']) && is_array($detailData['detailJournalVoucher'])) {
                  foreach ($detailData['detailJournalVoucher'] as $detail) {
                    if (isset($detail['branch']['id']) && $detail['branch']['id'] == $rootBranchId) {
                      if (isset($detail['branch']['name'])) {
                        $foundBranchName = $detail['branch']['name'];
                        break; // Found match, stop searching
                      }
                    }
                  }
                }
                
                if ($foundBranchName !== null) {
                  unset($detailData['branchId']);
                  $detailData['branchName'] = $foundBranchName;
                  
                  Log::info('JOURNAL_VOUCHER_BRANCH_TRANSFORMED', [
                    'item_id' => $itemId,
                    'old_branch_id' => $rootBranchId,
                    'new_branch_name' => $foundBranchName
                  ]);
                } else {
                  Log::warning('JOURNAL_VOUCHER_BRANCH_NOT_FOUND', [
                    'item_id' => $itemId,
                    'root_branch_id' => $rootBranchId,
                    'detail_count' => count($detailData['detailJournalVoucher'] ?? [])
                  ]);
                }
              }
            }

            $identifierField = $moduleInfo['identifier_field'] ?? 'number';
            $transactionNo = $detailData[$identifierField] ?? $item[$identifierField] ?? "ID-{$itemId}";

            $exists = Transaction::where('transaction_no', $transactionNo)
              ->where('module_id', $moduleRecord->id)
              ->where('accurate_database_id', $databaseId)
              ->exists();

            if ($exists) {
              $skippedCount++;
              continue;
            }

            // Save detail lengkap ke transactions table
            $transaction = Transaction::create([
              'transaction_no' => $transactionNo,
              'accurate_database_id' => $databaseId,
              'module_id' => $moduleRecord->id,
              'data' => json_encode($detailData),
              'description' => $moduleInfo['name'],
              'captured_at' => now(),
            ]);

            $savedCount++;
            $savedTransactionNumbers[] = $transaction->transaction_no;

          } catch (\Exception $e) {
            $failedCount++;
            continue;
          }
        }
      }


      $logStatus = $failedCount > 0 ? 'warning' : ($hasData ? 'success' : 'info');
      $logMessage = $hasData
        ? "Capture {$moduleInfo['name']}: {$savedCount} new, {$skippedCount} skipped" . ($failedCount > 0 ? ", {$failedCount} failed" : "")
        : "Module {$moduleInfo['name']} checked but no data available";

      SystemLog::create([
        'event_type' => 'capture',
        'module' => $moduleInfo['name'],
        'transaction_id' => null,
        'status' => $logStatus,
        'payload' => [
          'module_slug' => $module,
          'database_id' => $databaseId,
          'database_name' => $accurateDatabase->db_name,
          'list_endpoint' => $moduleInfo['list_endpoint'],
          'detail_endpoint' => $moduleInfo['detail_endpoint'],
          'total_items' => count($listData),
          'saved_count' => $savedCount,
          'skipped_count' => $skippedCount,
          'failed_count' => $failedCount,
          'transaction_numbers' => $savedTransactionNumbers,
          'module_active' => $moduleRecord->is_active,
          'was_created' => $moduleRecord->wasRecentlyCreated,
        ],
        'message' => $logMessage,
        'user_id' => Auth::id(),
      ]);

      return response()->json([
        'success' => true,
        'message' => $hasData
          ? "Successfully captured {$savedCount} new records" . ($skippedCount > 0 ? ", {$skippedCount} already exist" : "") . ($failedCount > 0 ? ", {$failedCount} failed" : "")
          : "Module {$moduleInfo['name']} created but no data available",
        'module' => $moduleInfo['name'],
        'module_status' => $moduleRecord->is_active ? 'active' : 'inactive',
        'total_records' => count($listData),
        'saved_records' => $savedCount,
        'skipped_records' => $skippedCount,
        'failed_records' => $failedCount,
      ]);
    } catch (\Exception $e) {
      Log::error('MODULE_CAPTURE_ERROR', [
        'module' => $module,
        'error' => $e->getMessage()
      ]);

      // Create system log untuk error
      SystemLog::create([
        'event_type' => 'capture',
        'module' => $moduleMapping[$module]['name'] ?? $module,
        'transaction_id' => null,
        'status' => 'failed',
        'payload' => [
          'module_slug' => $module,
          'error' => $e->getMessage(),
          'trace' => $e->getTraceAsString(),
        ],
        'message' => "Failed to capture {$module}: {$e->getMessage()}",
        'user_id' => Auth::id(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to capture data: ' . $e->getMessage()
      ], 500);
    }
  }
}
