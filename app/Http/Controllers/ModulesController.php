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
                ->withCount(['transactions' => function($query) use ($databaseId) {
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
        // Mapping module slug ke Accurate API endpoint
        $moduleMapping = [
            'sales-order' => [
                'name' => 'Sales Order',
                'list_endpoint' => '/api/sales-order/list.do',
                'detail_endpoint' => '/api/sales-order/detail.do',
                'identifier_field' => 'number', // field untuk unique identifier
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
            'customer' => [
                'name' => 'Customer',
                'list_endpoint' => '/api/customer/list.do',
                'detail_endpoint' => '/api/customer/detail.do',
                'identifier_field' => 'customerNo', // Customer pakai customerNo
                'type' => 'master',
            ],
            'item' => [
                'name' => 'Item',
                'list_endpoint' => '/api/item/list.do',
                'detail_endpoint' => '/api/item/detail.do',
                'identifier_field' => 'itemNo', // Item pakai itemNo
                'type' => 'master',
            ],
            'branch' => [
                'name' => 'Branch',
                'list_endpoint' => '/api/branch/list.do',
                'detail_endpoint' => '/api/branch/detail.do',
                'identifier_field' => 'name', // Branch pakai name
                'type' => 'master',
            ],
            'department' => [
                'name' => 'Department',
                'list_endpoint' => '/api/department/list.do',
                'detail_endpoint' => '/api/department/detail.do',
                'identifier_field' => 'name', // Department pakai name
                'type' => 'master',
            ],
            'employee' => [
                'name' => 'Employee',
                'list_endpoint' => '/api/employee/list.do',
                'detail_endpoint' => '/api/employee/detail.do',
                'identifier_field' => 'employeeNo', // Employee pakai employeeNo
                'type' => 'master',
            ],
            'fixed-asset' => [
                'name' => 'Fixed Asset',
                'list_endpoint' => '/api/fixed-asset/list.do',
                'detail_endpoint' => '/api/fixed-asset/detail.do',
                'identifier_field' => 'name', // Fixed Asset pakai name
                'type' => 'master',
            ],
            'warehouse' => [
                'name' => 'Warehouse',
                'list_endpoint' => '/api/warehouse/list.do',
                'detail_endpoint' => '/api/warehouse/detail.do',
                'identifier_field' => 'name', // Warehouse pakai name
                'type' => 'master',
            ],
            'vendor' => [
                'name' => 'Vendor',
                'list_endpoint' => '/api/vendor/list.do',
                'detail_endpoint' => '/api/vendor/detail.do',
                'identifier_field' => 'vendorNo', // Vendor pakai vendorNo
                'type' => 'master',
            ],
            'project' => [
                'name' => 'Project',
                'list_endpoint' => '/api/project/list.do',
                'detail_endpoint' => '/api/project/detail.do',
                'identifier_field' => 'name', // Project pakai name
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
            
            // Get database ID dari session (ini adalah db_id dari Accurate, bukan primary key)
            $accurateDbId = session()->get('database_id');
            
            if (!$accurateDbId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No database selected'
                ], 400);
            }

            // Cari record AccurateDatabase berdasarkan db_id untuk dapatkan primary key ID
            $accurateDatabase = \App\Models\AccurateDatabase::where('db_id', $accurateDbId)->first();
            
            if (!$accurateDatabase) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database not found in records'
                ], 404);
            }
            
            $databaseId = $accurateDatabase->id; // Ini primary key yang benar

            // Fetch list data dari Accurate
            $listData = $accurate->fetchModuleData($moduleInfo['list_endpoint']);            
            Log::info('ACCURATE_MODULE_LIST', [
                'module' => $module,
                'total_items' => count($listData),
            ]);

            // Cek apakah ada data atau tidak
            $hasData = count($listData) > 0;
            
            // Create atau update Module berdasarkan database_id dan slug
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

            // Update status jika module sudah ada
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
            
            // Hanya loop jika ada data
            if ($hasData) {
                foreach ($listData as $item) {
                    try {
                        $itemId = $item['id'] ?? null;
                        
                        if (!$itemId) {
                            Log::warning('ACCURATE_ITEM_NO_ID', ['item' => $item]);
                            continue;
                        }

                        // Fetch detail dari detail.do dengan id
                        $detailData = $accurate->fetchModuleData($moduleInfo['detail_endpoint'], [
                            'id' => $itemId
                        ]);

                        // Get identifier berdasarkan module type
                        $identifierField = $moduleInfo['identifier_field'] ?? 'number';
                        $transactionNo = $detailData[$identifierField] ?? $item[$identifierField] ?? "ID-{$itemId}";

                        // Cek apakah transaction_no sudah ada untuk module ini
                        $exists = Transaction::where('transaction_no', $transactionNo)
                            ->where('module_id', $moduleRecord->id)
                            ->where('accurate_database_id', $databaseId)
                            ->exists();

                        if ($exists) {
                            $skippedCount++;
                            Log::info('ACCURATE_ITEM_SKIPPED', [
                                'module' => $module,
                                'number' => $transactionNo,
                                'reason' => 'already_exists'
                            ]);
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

                        Log::info('ACCURATE_ITEM_SAVED', [
                            'module' => $module,
                            'id' => $itemId,
                            'identifier' => $transactionNo,
                            'identifier_field' => $identifierField,
                        ]);

                    } catch (\Exception $e) {
                        $failedCount++;
                        Log::error('ACCURATE_ITEM_ERROR', [
                            'module' => $module,
                            'item_id' => $itemId ?? null,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
            }

            // Create system log untuk capture event
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
