<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Support\Facades\Log;

class AccurateService
{
  // ===GET DATABASE LIST===
  public function getDatabaseList(): array
  {
    if (!session()->has('accurate_access_token')) {
      throw new Exception('Tidak bisa mengambil daftar database tanpa Access Token.');
    }
    if (session()->has('accurate_database_list_cache')) {
      $cache = session('accurate_database_list_cache');
      // Cache valid for 30 minutes
      if (isset($cache['timestamp']) && (time() - $cache['timestamp']) < 1800) {
        return $cache['data'];
      }
    }

    try {
      $response = Http::withToken(session('accurate_access_token'))
        ->timeout(120) // Set timeout to 2 minutes for slow API connections
        ->connectTimeout(60) // Set connection timeout to 60 seconds
        ->get(env('ACCURATE_API_URL') . '/api/db-list.do');

      if ($response->failed()) {
        throw new Exception("Gagal mendapatkan daftar database dari Accurate.");
      }

      $databases = $response->json()['d'] ?? [];
      session([
        'accurate_database_list_cache' => [
          'data' => $databases,
          'timestamp' => time()
        ]
      ]);
      return $databases;
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
      session()->forget([
        'accurate_access_token',
        'accurate_database',
        'accurate_database_list_cache',
        'database_id',
        'accurate_host'
      ]);

      throw new Exception('Koneksi ke Accurate gagal. Kemungkinan server sedang maintenance. Silakan login kembali.');
    }
  }

  // ===GET DATABASE HOST===
  public function getDatabaseHost()
  {
    $response = $this->client()->post('/api/api-token.do');
    if ($response->failed() || !isset($response->json()['d']['database']['host'])) {
      throw new Exception("Gagal mendapatkan host database dari Accurate.");
    }
    $host = $response->json()['d']['database']['host'];
    session(['accurate_host' => $host]);
    return $host;
  }

  // ===BULK SAVE TO ACCURATE===
  public function bulkSaveToAccurate(string $endpoint, array $data, ?array $targetDbInfo = null)
  {
    // Execution time 10 minutes untuk large data (prevent something worse)
    set_time_limit(1800);
    if (
      str_contains($endpoint, 'warehouse') ||
      str_contains($endpoint, 'price-category') ||
      str_contains($endpoint, 'work-order') ||
      str_contains($endpoint, 'bill-of-material')
    ) {
      return $this->saveOneByOne($endpoint, $data, $targetDbInfo);
    }

    // Extract module from endpoint
    preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
    $module = $matches[1] ?? null;

    // Get accurate database ID
    $accurateDatabaseId = $this->getAccurateDatabaseId($targetDbInfo);

    // Check for existing entities and add IDs for update instead of create
    if ($module && $accurateDatabaseId) {
      $numberField = $this->getNumberFieldForModule($module, $data[0] ?? []);

      if ($numberField) {
        foreach ($data as &$item) {
          $sourceIdentifier = $item[$numberField] ?? null;

          if ($sourceIdentifier) {
            $existingId = \App\Models\AccurateEntityMapping::getAccurateId(
              $accurateDatabaseId,
              $module,
              $sourceIdentifier
            );

            if ($existingId) {
              // When the number field IS 'id', preserve the original captured id
              // so storeEntityMappings can still use it as the source identifier.
              if ($numberField === 'id') {
                $item['_sourceId'] = $item['id'];
              }

              // Add ID to trigger update instead of create
              $item['id'] = $existingId;

              // Add marker to identify this as update operation
              $item['_isUpdate'] = true;

              // IMPORTANT: Remove identifier field untuk update
              // Accurate tidak mengizinkan perubahan no/number/vendorNo saat update.
              // Exception: do NOT unset 'id' — it has already been replaced above.
              if ($numberField !== 'id') {
                unset($item[$numberField]);
              }

              Log::info("Entity already exists, will update", [
                'module' => $module,
                'identifier' => $sourceIdentifier,
                'accurate_id' => $existingId,
                'removed_field' => $numberField !== 'id' ? $numberField : '(none — id replaced)',
              ]);
            }
          }
        }
        unset($item); // Break reference
      }
    }

    // Determine which client to use: target database or session database
    $client = $targetDbInfo ? $this->dataClientForDatabase($targetDbInfo) : $this->dataClient();

    if (str_contains($endpoint, '/tax/')) {
      $data = array_map(function ($item) use ($client) {
        $salesTaxGlAccountId = $item['salesTaxGlAccountId'] ?? null;
        $purchaseTaxGlAccountId = $item['purchaseTaxGlAccountId'] ?? null;

        unset($item['salesTaxGlAccountId']);
        unset($item['purchaseTaxGlAccountId']);

        $taxType = $item['taxType'] ?? '';
        $salesAccountNo = null;
        $purchaseAccountNo = null;

        if ($salesTaxGlAccountId !== null) {
          try {
            $response = $client->get('/api/glaccount/detail.do', [
              'id' => $salesTaxGlAccountId
            ]);
            if ($response->successful() && isset($response->json()['d']['no'])) {
              $salesAccountNo = $response->json()['d']['no'];
            }
          } catch (\Exception $e) {
          }
        }

        if ($purchaseTaxGlAccountId !== null) {
          try {
            $response = $client->get('/api/glaccount/detail.do', [
              'id' => $purchaseTaxGlAccountId
            ]);
            if ($response->successful() && isset($response->json()['d']['no'])) {
              $purchaseAccountNo = $response->json()['d']['no'];
            }
          } catch (\Exception $e) {
          }
        }
        $item['salesTaxGlAccountNo'] = $salesAccountNo;
        $item['purchaseTaxGlAccountNo'] = $purchaseAccountNo;
        return $item;
      }, $data);
    }
    $cleanedData = array_map(function ($item) use ($endpoint) {
      // Check for explicit update marker (set when ID from mapping is added)
      $isUpdate = isset($item['_isUpdate']) && $item['_isUpdate'] === true;
      return $this->cleanDataItem($item, $endpoint, $isUpdate);
    }, $data);

    $requestBody = [
      'data' => $cleanedData
    ];
    Log::info("body", ["body" => $requestBody]);
    $response = $client->post($endpoint, $requestBody);
    $responseData = $response->json();
    Log::info("Received response from Accurate", [
      'endpoint' => $endpoint,
      'response' => $responseData
    ]);

    // Store entity mappings after successful save
    if (isset($responseData['s']) && $responseData['s'] === true && $module && $accurateDatabaseId) {
      $this->storeEntityMappings($module, $data, $responseData, $accurateDatabaseId);
    }

    $this->storeNumberMappings($endpoint, $data, $responseData, $targetDbInfo);
    return $responseData;
  }

  // ===STORE NUMBER MAPPINGS===
  protected function storeNumberMappings(string $endpoint, array $originalData, array $responseData, ?array $targetDbInfo = null): void
  {
    if (!isset($responseData['s']) || $responseData['s'] !== true) {
      return;
    }

    // If targetDbInfo is provided, use its database ID, otherwise use session
    if ($targetDbInfo && isset($targetDbInfo['id'])) {
      $accurateDatabaseId = $targetDbInfo['id'];
    } else {
      $accurateDatabaseId = session('accurate_database.id') ?? null;
      if (!$accurateDatabaseId) {
        $dbId = session('database_id');
        if ($dbId) {
          $accurateDb = \App\Models\AccurateDatabase::where('db_id', $dbId)->first();
          $accurateDatabaseId = $accurateDb?->id;
        }
      }
    }

    if (!$accurateDatabaseId) {
      return;
    }
    preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
    $moduleSlug = $matches[1] ?? null;

    if (!$moduleSlug) {
      return;
    }

    $results = $responseData['d'] ?? [];
    foreach ($results as $index => $result) {
      if (!isset($result['s']) || $result['s'] !== true) {
        continue;
      }
      $oldNumber = $originalData[$index]['number'] ?? null;
      if ($oldNumber && isset($result['r'])) {
        \App\Models\TransactionNumberMapping::storeMapping(
          $accurateDatabaseId,
          $moduleSlug,
          $oldNumber,
          $result
        );
      }
    }
  }

  // ===CASE JIKA MODULE HANYA BISA SAVE.DO===
  protected function saveOneByOne(string $endpoint, array $data, ?array $targetDbInfo = null)
  {
    // Execution time 10 minutes untuk large data (prevent something worse)
    set_time_limit(600);

    // Extract module from endpoint
    preg_match('/\/api\/([^\/]+)\//', $endpoint, $matches);
    $module = $matches[1] ?? null;

    // Get accurate database ID
    $accurateDatabaseId = $this->getAccurateDatabaseId($targetDbInfo);

    // Determine which client to use
    $client = $targetDbInfo ? $this->dataClientForDatabase($targetDbInfo) : $this->dataClient();

    // Check for existing entities and add IDs for update
    if ($module && $accurateDatabaseId) {
      $numberField = $this->getNumberFieldForModule($module, $data[0] ?? []);

      if ($numberField) {
        foreach ($data as &$item) {
          $sourceIdentifier = $item[$numberField] ?? null;

          if ($sourceIdentifier) {
            $existingId = \App\Models\AccurateEntityMapping::getAccurateId(
              $accurateDatabaseId,
              $module,
              $sourceIdentifier
            );

            if ($existingId) {
              // When the number field IS 'id', preserve the original captured id
              if ($numberField === 'id') {
                $item['_sourceId'] = $item['id'];
              }

              $item['id'] = $existingId;
              $item['_isUpdate'] = true;

              if ($numberField !== 'id') {
                unset($item[$numberField]);
              }

              Log::info("Entity already exists (saveOneByOne), will update", [
                'module' => $module,
                'identifier' => $sourceIdentifier,
                'accurate_id' => $existingId,
                'removed_field' => $numberField !== 'id' ? $numberField : '(none — id replaced)',
              ]);
            }
          }
        }
        unset($item); // Break reference
      }
    }

    $results = [];
    $successCount = 0;
    $failedCount = 0;
    $saveEndpoint = str_replace('bulk-save.do', 'save.do', $endpoint);

    $moduleName = 'Module';
    if (str_contains($endpoint, 'warehouse')) {
      $moduleName = 'WAREHOUSE';
    } elseif (str_contains($endpoint, 'price-category')) {
      $moduleName = 'PRICE_CATEGORY';
    }

    foreach ($data as $index => $item) {
      // Check for explicit update marker (set when ID from mapping is added)
      $isUpdate = isset($item['_isUpdate']) && $item['_isUpdate'] === true;
      $cleanedItem = $this->cleanDataItem($item, $endpoint, $isUpdate);

      try {
        $response = $client->post($saveEndpoint, $cleanedItem);
        $result = $response->json();
        $results[] = $result;

        if (isset($result['s']) && $result['s'] === true) {
          $successCount++;

          // Store entity mapping for successful save
          if ($module && $accurateDatabaseId) {
            $numberField = $this->getNumberFieldForModule($module, $item);

            // For 'id'-based modules the inject loop replaces $item['id'] with the
            // Accurate entity ID; recover the original captured id from _sourceId.
            if ($numberField === 'id' && isset($item['_sourceId'])) {
              $sourceIdentifier = $item['_sourceId'];
            } else {
              $sourceIdentifier = $item[$numberField] ?? null;
            }

            $accurateId = $result['r']['id'] ?? null;
            // Always use $sourceIdentifier as the mapping key (accurate_number) so
            // getAccurateId() lookup on the next push finds the record correctly.
            // Transaction modules get Accurate-assigned numbers different from source;
            // using the API number would cause a lookup mismatch on second push.
            $accurateNumber = $sourceIdentifier;

            if ($sourceIdentifier && $accurateId) {
              $wasUpdate = isset($item['_isUpdate']) && $item['_isUpdate'] === true;
              
              \App\Models\AccurateEntityMapping::storeMapping(
                $accurateDatabaseId,
                $module,
                $sourceIdentifier,
                $accurateId,
                $accurateNumber,
                [
                  'synced_at' => now()->toIso8601String(),
                  'endpoint' => $saveEndpoint,
                  'operation' => $wasUpdate ? 'update' : 'create'
                ]
              );

              // ===UPDATE TRANSACTION STATUS===
              $this->updateTransactionStatus(
                $sourceIdentifier,
                $module,
                $accurateDatabaseId,
                $wasUpdate ? \App\Models\Transaction::STATUS_PUSHED_UPDATE : \App\Models\Transaction::STATUS_PUSHED_CREATE
              );
            }
          }
        } else {
          $failedCount++;
        }
      } catch (\Exception $e) {
        $results[] = [
          's' => false,
          'd' => $e->getMessage()
        ];
        $failedCount++;
      }
    }
    return [
      's' => $failedCount === 0,
      'd' => $results,
      'total' => count($data),
      'success' => $successCount,
      'failed' => $failedCount
    ];
  }

  // ===GET MAPPED NUMBER===
  protected function getMappedNumber(string $moduleSlug, string $oldNumber): string
  {
    $accurateDatabaseId = session('accurate_database.id') ?? null;

    if (!$accurateDatabaseId) {
      $dbId = session('database_id');
      if ($dbId && ($moduleSlug !== "employee")) {
        $accurateDb = \App\Models\AccurateDatabase::where('db_id', $dbId)->first();
        $accurateDatabaseId = $accurateDb?->id;
      }
    }

    if (!$accurateDatabaseId) {
      return $oldNumber;
    }
    $newNumber = \App\Models\TransactionNumberMapping::getNewNumber(
      $accurateDatabaseId,
      $moduleSlug,
      $oldNumber
    );
    return $newNumber ?? $oldNumber;
  }

  // ===CLEAN DATA ITEM BEFORE SENDING TO ACCURATE===
  protected function cleanDataItem(array $item, string $endpoint = '', bool $isUpdate = false, bool $isSubItem = false): array
  {
    // Only apply handler transform at the top (header) level.
    // Recursive calls for detail sub-items skip this — detail lines are
    // handled by cleanDataItem's own transformation logic below.
    if (!$isSubItem) {
      // Save inject-loop fields before transformDetail overwrites $item.
      // Handlers filter $item to allowedFields, which strips 'id' even though
      // it was set by the inject loop to trigger an UPDATE. We must restore it.
      $injectedId       = $item['id'] ?? null;
      $injectedSourceId = $item['_sourceId'] ?? null;
      $injectedIsUpdate = $item['_isUpdate'] ?? null;

      $handler = \App\Modules\ModuleManager::forEndpoint($endpoint);
      $sharedContext = [];
      $meta = [];
      $handler->transformDetail($item, $sharedContext, $meta);

      // Restore after handler filtering so cleanDataItem can honour them.
      if ($injectedId       !== null) { $item['id']        = $injectedId; }
      if ($injectedSourceId !== null) { $item['_sourceId'] = $injectedSourceId; }
      if ($injectedIsUpdate !== null) { $item['_isUpdate'] = $injectedIsUpdate; }
    }

    $cleaned = [];

    foreach ($item as $key => $value) {
      // ===START SKIP FIELDS===
      // Skip internal marker fields
      if ($key === '_isUpdate' || $key === '_sourceId') {
        continue;
      }

      // Skip 'id' only if it's NOT an update operation
      // On CREATE: skip 'id' from source (Accurate will generate new ID)
      // On UPDATE: keep 'id' from mapping (Accurate needs ID to identify which record to update)
      if ($key === 'id' && !$isUpdate) {
        continue;
      }

      if ($key === 'vendorType') {
        continue;
      }
      // In detail sub-items, strip branchId (header-level concern) and
      // optLock (source-system concurrency field Accurate doesn't need in lines).
      if ($isSubItem && ($key === 'branchId' || $key === 'optLock')) {
        continue;
      }
      if ($key === 'itemId' && str_contains($endpoint, 'bill-of-material')) {
        continue;
      }
      if ($key === 'transactionType' && str_contains($endpoint, 'journal-voucher')) {
        continue;
      }
      if ($key === 'locationId' && str_contains($endpoint, 'warehouse')) {
        continue;
      }
      if ($key === 'branchId' && str_contains($endpoint, 'stock-opname-order')) {
        continue;
      }
      if (str_contains($endpoint, '/tax/') && ($key === 'salesTaxGlAccountId' || $key === 'purchaseTaxGlAccountId')) {
        continue;
      }
      if ($key === 'number' && (
        str_contains($endpoint, 'delivery-order') ||
        str_contains($endpoint, 'purchase-invoice') ||
        str_contains($endpoint, 'purchase-order') ||
        str_contains($endpoint, 'purchase-payment') ||
        str_contains($endpoint, 'purchase-requisition') ||
        str_contains($endpoint, 'purchase-return') ||
        str_contains($endpoint, 'sales-invoice') ||
        str_contains($endpoint, 'sales-order') ||
        str_contains($endpoint, 'sales-quotation') ||
        str_contains($endpoint, 'sales-receipt') ||
        str_contains($endpoint, 'sales-return') ||
        str_contains($endpoint, 'receive-item') ||
        str_contains($endpoint, 'job-order') ||
        str_contains($endpoint, 'item-transfer')
      )) {
        continue;
      }

      if ($key === 'apAccountId') {
        continue;
      }
      if ($key === 'purchaseOrderDetailId') {
        continue;
      }
      if ($key === 'salesOrderDetailId') {
        continue;
      }
      // ===END SKIP FIELDS===





      // ===START TRANSFORM FIELDS===
      if (str_contains($endpoint, 'item')) {
        if ($key == "itemCategory" && is_array($value)) {
          if (isset($value['name'])) {
            $cleaned['itemCategoryName'] = $value['name'];
          }
          continue;
        }
      }
      if (str_contains($endpoint, 'stock-opname-order')) {
        if ($key == "itemCategoryList" && is_array($value)) {
          $cleaned['itemCategoryListName'] = $value;
          continue;
        }
      }
      if (str_contains($endpoint, 'material-slip')) {
        if ($key == "branch" && is_array($value)) {
          if (isset($value['name'])) {
            $cleaned['branchName'] = $value['name'];
          }
          continue;
        }
        if ($key == "workOrder" && is_array($value)) {
          if (isset($value['number'])) {
            $cleaned['workOrderNumber'] = $value['number'];
          }
          continue;
        }
      }
      if ($key === 'vendor' && is_array($value) && (str_contains($endpoint, 'purchase-order') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'purchase-payment') || str_contains($endpoint, 'purchase-return') || str_contains($endpoint, 'receive-item'))) {
        if (isset($value['vendorNo'])) {
          $cleaned['vendorNo'] = $value['vendorNo'];
        }
        continue;
      }
      if ($key === 'customer' && is_array($value) && (str_contains($endpoint, 'sales-order') || str_contains($endpoint, 'sales-invoice') || str_contains($endpoint, 'sales-quotation') || str_contains($endpoint, 'sales-receipt') || str_contains($endpoint, 'sales-return') || str_contains($endpoint, 'delivery-order'))) {
        if (isset($value['customerNo'])) {
          $cleaned['customerNo'] = $value['customerNo'];
        }
        continue;
      }
      if (str_contains($endpoint, 'bank-transfer')) {
        if ($key === 'fromBank' && is_array($value)) {
          if (isset($value['no'])) {
            $cleaned['fromBankNo'] = $value['no'];
          }
          continue;
        }
        if ($key === 'toBank' && is_array($value)) {
          if (isset($value['no'])) {
            $cleaned['toBankNo'] = $value['no'];
          }
          continue;
        }
      }
      if ($key === 'expensePayable' && is_array($value) && str_contains($endpoint, 'expense')) {
        if (isset($value['no'])) {
          $cleaned['expensePayableNo'] = $value['no'];
        }
        continue;
      }
      if ($key === 'bank' && is_array($value) && (str_contains($endpoint, 'sales-receipt') || str_contains($endpoint, 'purchase-payment'))) {
        if (isset($value['no'])) {
          $cleaned['bankNo'] = $value['no'];
        }
        continue;
      }
      if ($key === 'fromItemTransfer' && is_array($value) && str_contains($endpoint, 'item-transfer')) {
        if (isset($value['number'])) {
          $cleaned['fromItemTransferNo'] = $value['number'];
        }
        continue;
      }
      if ($key === 'invoice' && is_array($value) && (str_contains($endpoint, 'purchase-return') || str_contains($endpoint, 'sales-return'))) {
        if (isset($value['number'])) {
          if (str_contains($endpoint, 'purchase-return')) {
            $cleaned['invoiceNumber'] = $this->getMappedNumber(
              'purchase-invoice',
              $value['number']
            );
          } else {
            $cleaned['invoiceNumber'] = $this->getMappedNumber(
              'sales-invoice',
              $value['number']
            );
          }
        }
        continue;
      }
      if ($key === 'order' && is_array($value) && str_contains($endpoint, 'stock-opname-result')) {
        if (isset($value['number'])) {
          $cleaned['orderNumber'] = $value['number'];
        }
        continue;
      }
      if ($key === 'jobOrder' && is_array($value) && str_contains($endpoint, 'roll-over')) {
        if (isset($value['number'])) {
          $cleaned['jobOrderNumber'] = $value['number'];
        }
        continue;
      }
      if ($key === 'billOfMaterial' && is_array($value) && str_contains($endpoint, 'work-order')) {
        if (isset($value['number'])) {
          $cleaned['billOfMaterialNo'] = $value['number'];
        }
        continue;
      }
      if ($key === 'manufactureOrder' && is_array($value) && str_contains($endpoint, 'work-order')) {
        if (isset($value['number'])) {
          $cleaned['manufactureOrderNo'] = $value['number'];
        }
        continue;
      }
      if ($key === 'warehouse' && is_array($value) && str_contains($endpoint, 'stock-opname-order')) {
        if (isset($value['name'])) {
          $cleaned['warehouseName'] = $value['name'];
        }
        continue;
      }
      if ($key === 'item' && is_array($value) && str_contains($endpoint, 'bill-of-material')) {
        if (isset($value['no'])) {
          $cleaned['itemNo'] = $value['no'];
        }
        continue;
      }
      if ($key === 'paymentTerm' && is_array($value)) {
        if (isset($value['name'])) {
          $cleaned['paymentTermName'] = $value['name'];
        }
        continue;
      }
      // ===END TRANSFORM FIELDS===



      if (($key === 'npwpNo' || $key === 'wpNumber') && is_string($value)) {
        $value = preg_replace('/[^0-9]/', '', $value);
        if ($value === '') {
          continue;
        }
        if (strlen($value) < 16) {
          $value = str_pad($value, 16, '0', STR_PAD_RIGHT);
        }
        if (strlen($value) > 16) {
          $value = substr($value, 0, 16);
        }
      }
      if ($value === null) {
        continue;
      }
      if (str_ends_with($key, 'Id') && $value === 0) {
        continue;
      }

      if ($value === '') {
        continue;
      }

      // ===TRANSFORM ARRAY ITEMS===
      if (is_array($value)) {
        if (empty($value)) {
          continue;
        }

        $cleanedArray = [];
        foreach ($value as $subKey => $subValue) {
          if (is_array($subValue)) {
            // Detail sub-items should never carry their source-system `id`.
            // Only the header `id` (set by the inject loop) is needed for UPDATE.
            // Always pass $isUpdate=false so sub-item `id` fields are stripped.
            $cleanedSubItem = $this->cleanDataItem($subValue, $endpoint, false, true);
            if (!empty($cleanedSubItem)) {
              if ($key === 'detailItem' && (
                str_contains($endpoint, 'purchase-order') ||
                str_contains($endpoint, 'purchase-invoice') ||
                str_contains($endpoint, 'purchase-return') ||
                str_contains($endpoint, 'receive-item') ||
                str_contains($endpoint, 'sales-order') ||
                str_contains($endpoint, 'sales-invoice') ||
                str_contains($endpoint, 'job-order') ||
                str_contains($endpoint, 'sales-quotation') ||
                str_contains($endpoint, 'sales-return') ||
                str_contains($endpoint, 'delivery-order') ||
                str_contains($endpoint, 'material-slip') ||
                str_contains($endpoint, 'finished-good-slip') ||
                str_contains($endpoint, 'vendor-price') ||
                str_contains($endpoint, 'purchase-requisition') ||
                str_contains($endpoint, 'stock-opname-result') ||
                str_contains($endpoint, 'item-transfer'))) {
                if (isset($cleanedSubItem['item']['no'])) {
                  $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                  unset($cleanedSubItem['item']);
                }
                if (isset($cleanedSubItem['warehouse']['name'])) {
                  $cleanedSubItem['warehouseName'] = $cleanedSubItem['warehouse']['name'];
                  unset($cleanedSubItem['warehouse']);
                }
                if (isset($cleanedSubItem['itemUnit']['name'])) {
                  $cleanedSubItem['itemUnitName'] = $cleanedSubItem['itemUnit']['name'];
                  unset($cleanedSubItem['itemUnit']);
                  unset($cleanedSubItem['itemUnitId']);
                }
                if (isset($cleanedSubItem['purchaseOrder']['number'])) {
                  $cleanedSubItem['purchaseOrderNumber'] = $this->getMappedNumber(
                    'purchase-order',
                    $cleanedSubItem['purchaseOrder']['number']
                  );
                  unset($cleanedSubItem['purchaseOrder']);
                }
                // Always strip purchaseOrderId — Accurate resolves the PO reference
                // via purchaseOrderNumber, not the source-system integer ID.
                unset($cleanedSubItem['purchaseOrderId']);
                if (isset($cleanedSubItem['salesOrder']['number'])) {
                  $cleanedSubItem['salesOrderNumber'] = $this->getMappedNumber(
                    'sales-order',
                    $cleanedSubItem['salesOrder']['number']
                  );
                  unset($cleanedSubItem['salesOrder']);
                }
                if (isset($cleanedSubItem['salesQuotation']['number'])) {
                  $cleanedSubItem['salesQuotationNumber'] = $this->getMappedNumber(
                    'sales-quotation',
                    $cleanedSubItem['salesQuotation']['number']
                  );
                  unset($cleanedSubItem['salesQuotation']);
                }
                // if (isset($cleanedSubItem['salesQuotation']['number'])) {
                //   $cleanedSubItem['salesQuotationNumber'] = $this->getMappedNumber(
                //     'sales-quotation',
                //     $cleanedSubItem['salesQuotation']['number']
                //   );
                //   unset($cleanedSubItem['salesQuotation']);
                // }
              }


              if ($key === 'detailItem' && str_contains($endpoint, 'item-adjustment')) {
                $adjustmentItem = [];
                if (isset($cleanedSubItem['item']['no'])) {
                  $adjustmentItem['itemNo'] = $cleanedSubItem['item']['no'];
                }
                if (isset($cleanedSubItem['itemAdjustmentType'])) {
                  $adjustmentItem['itemAdjustmentType'] = $cleanedSubItem['itemAdjustmentType'];
                }
                if (isset($cleanedSubItem['unitCost'])) {
                  $adjustmentItem['unitCost'] = $cleanedSubItem['unitCost'];
                }
                if (isset($cleanedSubItem['quantity'])) {
                  $adjustmentItem['quantity'] = $cleanedSubItem['quantity'];
                }
                $cleanedSubItem = $adjustmentItem;
              }

              if ($key === 'detailSerialNumber' && (str_contains($endpoint, '/item/') || str_contains($endpoint, 'job-order') || str_contains($endpoint, 'item-transfer') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'receive-item') || str_contains($endpoint, 'sales-invoice'))) {
                if (isset($cleanedSubItem['serialNumber']['number'])) {
                  $cleanedSubItem['serialNumberNo'] = $cleanedSubItem['serialNumber']['number'];
                  unset($cleanedSubItem['serialNumber']);
                } elseif (isset($cleanedSubItem['serialNumber']['no'])) {
                  $cleanedSubItem['serialNumberNo'] = $cleanedSubItem['serialNumber']['no'];
                  unset($cleanedSubItem['serialNumber']);
                }
              }

              if ($key === 'detailAccount' && str_contains($endpoint, 'expense')) {
                if (isset($cleanedSubItem['account']['no'])) {
                  $cleanedSubItem['accountNo'] = $cleanedSubItem['account']['no'];
                  unset($cleanedSubItem['account']);
                }
              }

              if ($key === 'detailJournalVoucher' && str_contains($endpoint, 'journal-voucher')) {
                $amount = $cleanedSubItem['amount'] ?? 0;
                if ($amount < 1) {
                  continue;
                }
                if (isset($cleanedSubItem['glAccount']['no'])) {
                  $cleanedSubItem['accountNo'] = $cleanedSubItem['glAccount']['no'];
                  unset($cleanedSubItem['glAccount']);
                }
                if (isset($cleanedSubItem['vendor']['vendorNo'])) {
                  $cleanedSubItem['vendorNo'] = $cleanedSubItem['vendor']['vendorNo'];
                  unset($cleanedSubItem['vendor']);
                }
                if (isset($cleanedSubItem['customer']['customerNo'])) {
                  $cleanedSubItem['customerNo'] = $cleanedSubItem['customer']['customerNo'];
                  unset($cleanedSubItem['customer']);
                }
              }

              // ===ITEM===
              if ($key === "detailOpenBalance" && str_contains($endpoint, "item")) {
                if (isset($cleanedSubItem['warehouse']['name'])) {
                  $cleanedSubItem['warehouseName'] = $cleanedSubItem['warehouse']['name'];
                  unset($cleanedSubItem['warehouse']);
                }
              }

              // ===WORK ORDER===
              if ($key === 'detailExpense' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'purchase-order'))) {
                if (isset($cleanedSubItem['item']['no'])) {
                  $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                  unset($cleanedSubItem['item']);
                }
                if (isset($cleanedSubItem['account']['no'])) {
                  $cleanedSubItem['accountNo'] = $cleanedSubItem['account']['no'];
                  unset($cleanedSubItem['account']);
                }
                if (isset($cleanedSubItem['purchaseOrder']['number'])) {
                  $cleanedSubItem['purchaseOrderNumber'] = $this->getMappedNumber(
                    'purchase-order',
                    $cleanedSubItem['purchaseOrder']['number']
                  );
                  unset($cleanedSubItem['purchaseOrder']);
                }
              }
              if ($key === 'detailDownPayment' && (str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'sales-invoice'))) {
                if (isset($cleanedSubItem['invoice']['number'])) {
                  $moduleSlug = str_contains($endpoint, 'purchase-invoice') ? 'purchase-invoice' : 'sales-invoice';
                  $cleanedSubItem['invoiceNumber'] = $this->getMappedNumber(
                    $moduleSlug,
                    $cleanedSubItem['invoice']['number']
                  );
                }
              }

              if ($key === 'detailMaterial' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material'))) {
                if (isset($cleanedSubItem['item']['no'])) {
                  $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                  unset($cleanedSubItem['item']);
                }
              }
              if ($key === 'detailExtraFinishGood' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material'))) {
                if (isset($cleanedSubItem['item']['no'])) {
                  $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                  unset($cleanedSubItem['item']);
                }
              }
              if ($key === 'detailProcess' && (str_contains($endpoint, 'work-order') || str_contains($endpoint, 'bill-of-material'))) {
                if (isset($cleanedSubItem['processCategory']['name'])) {
                  $cleanedSubItem['processCategoryName'] = $cleanedSubItem['processCategory']['name'];
                  unset($cleanedSubItem['processCategory']);
                }
              }

              if ($key === 'detailInvoice' && (str_contains($endpoint, 'purchase-payment') || str_contains($endpoint, 'sales-receipt'))) {
                if (isset($cleanedSubItem['invoice']['number'])) {
                  // $cleanedSubItem['invoiceNo'] = $cleanedSubItem['invoice']['number'];
                  if (str_contains($endpoint, 'purchase-payment')) {
                    $cleanedSubItem['invoiceNo'] = $this->getMappedNumber(
                      'purchase-invoice',
                      $cleanedSubItem['invoice']['number']
                    );
                  } else {
                    $cleanedSubItem['invoiceNo'] = $this->getMappedNumber(
                      'sales-invoice',
                      $cleanedSubItem['invoice']['number']
                    );
                  }
                  unset($cleanedSubItem['invoiceId']);
                  unset($cleanedSubItem['invoice']);
                }

                if (isset($cleanedSubItem['detailDiscount']) && is_array($cleanedSubItem['detailDiscount'])) {
                  foreach ($cleanedSubItem['detailDiscount'] as $discountKey => $discount) {
                    if (is_array($discount) && isset($discount['account']['no'])) {
                      $cleanedSubItem['detailDiscount'][$discountKey]['accountNo'] = $discount['account']['no'];
                      unset($cleanedSubItem['detailDiscount'][$discountKey]['account']);
                    }
                  }
                }
              }
              $cleanedArray[] = $cleanedSubItem;
            }
          } else {
            if ($subKey === 'id' || $subKey === 'vendorType') {
              continue;
            }
            if (
              $subValue !== null && $subValue !== '' &&
              !(str_ends_with($subKey, 'Id') && $subValue === 0)
            ) {
              $cleanedArray[$subKey] = $subValue;
            }
          }
        }

        if (!empty($cleanedArray)) {
          $cleaned[$key] = $cleanedArray;
        }
        continue;
      }
      // TRANSFORM ARRAY ITEMS===

      $cleaned[$key] = $value;
    }
    return $cleaned;
  }

  // ===DATA CLIENT WITH SESSION INFO===
  protected function dataClient()
  {
    if (!session()->has('accurate_access_token')) {
      throw new Exception('Token Akses Accurate tidak ditemukan di session.');
    }
    if (!session()->has('accurate_database')) {
      throw new Exception('Database Accurate belum dipilih.');
    }

    $dbInfo = session('accurate_database');
    $host = $dbInfo['host'];
    $sessionId = $dbInfo['session'];
    $accessToken = session('accurate_access_token');

    return Http::withToken($accessToken)
      ->withHeaders([
        'X-Session-ID' => $sessionId,
      ])
      ->timeout(600) // Set timeout to 10 minutes for large data operations
      ->connectTimeout(60) // Set connection timeout to 60 seconds
      ->acceptJson()
      ->baseUrl($host . '/accurate');
  }

  // ===DATA CLIENT FOR SPECIFIC DATABASE===
  protected function dataClientForDatabase(array $dbInfo)
  {
    if (!session()->has('accurate_access_token')) {
      throw new Exception('Token Akses Accurate tidak ditemukan di session.');
    }

    $host = $dbInfo['host'];
    $sessionId = $dbInfo['session'];
    $accessToken = session('accurate_access_token');

    return Http::withToken($accessToken)
      ->withHeaders([
        'X-Session-ID' => $sessionId,
      ])
      ->timeout(600)
      ->connectTimeout(60)
      ->acceptJson()
      ->baseUrl($host . '/accurate');
  }

  // ===OPEN DATABASE BY ID===
  public function openDatabaseById(int $dbId): ?array
  {
    if (!session()->has('accurate_access_token')) {
      throw new Exception('Tidak bisa membuka database tanpa Access Token.');
    }
    try {
      $response = Http::withOptions([
        'track_redirects' => true
      ])->withToken(session('accurate_access_token'))
        ->timeout(120) // Set timeout to 2 minutes for database opening
        ->connectTimeout(60) // Set connection timeout to 60 seconds
        ->post(env('ACCURATE_API_URL') . '/api/open-db.do', ['id' => $dbId]);

      if ($response->failed()) {
        return null;
      }

      $responseData = $response->json();

      $redirectHistory = $response->handlerStats()['redirect_history'] ?? [];
      if (!empty($redirectHistory)) {
        $lastUrl = end($redirectHistory);

        $parsedUrl = parse_url($lastUrl);
        $newHost = ($parsedUrl['scheme'] ?? 'https') . '://' . $parsedUrl['host'];
        $responseData['host'] = $newHost;
      }
      return $responseData;
    } catch (Exception $e) {
      return null;
    }
  }

  // ===CONVERT TAX GL ACCOUNT IDS TO NOS===
  protected function convertTaxGlAccountIds(array $taxItem): array
  {
    if (isset($taxItem['salesTaxGlAccountId']) && $taxItem['salesTaxGlAccountId'] !== null) {
      try {
        $accountNo = $this->getGlAccountNoFromSourceById($taxItem['salesTaxGlAccountId']);
        if ($accountNo) {
          $taxItem['salesTaxGlAccountNo'] = $accountNo;
          unset($taxItem['salesTaxGlAccountId']);
        } else {
          unset($taxItem['salesTaxGlAccountId']);
        }
      } catch (\Exception $e) {
        unset($taxItem['salesTaxGlAccountId']);
      }
    }

    if (isset($taxItem['purchaseTaxGlAccountId']) && $taxItem['purchaseTaxGlAccountId'] !== null) {
      try {
        $accountNo = $this->getGlAccountNoFromSourceById($taxItem['purchaseTaxGlAccountId']);
        if ($accountNo) {
          $taxItem['purchaseTaxGlAccountNo'] = $accountNo;
          unset($taxItem['purchaseTaxGlAccountId']);
        } else {
          unset($taxItem['purchaseTaxGlAccountId']);
        }
      } catch (\Exception $e) {
        Log::error('TAX_PURCHASE_GLACCOUNT_FETCH_ERROR', [
          'sourceId' => $taxItem['purchaseTaxGlAccountId'],
          'error' => $e->getMessage()
        ]);
        unset($taxItem['purchaseTaxGlAccountId']);
      }
    }
    return $taxItem;
  }

  // ===GET GL ACCOUNT NO FROM SOURCE DB BY ID===
  protected function getGlAccountNoFromSourceById(int $glAccountId): ?string
  {
    try {
      $glAccounts = $this->fetchModuleData('/api/glaccount/list.do', [
        'sp.pageSize' => 10000
      ]);

      foreach ($glAccounts as $account) {
        if (isset($account['id']) && $account['id'] === $glAccountId) {
          $accountNo = $account['no'] ?? null;
          return $accountNo;
        }
      }
      return null;
    } catch (\Exception $e) {
      return null;
    }
  }

  // ===FETCH MODULE DATA===
  public function fetchModuleData(string $endpoint, array $params = []): array
  {
    try {
      $allData = [];
      $pageNumber = 1;
      $pageSize = 100;
      $params['sp.pageSize'] = $pageSize;

      Log::info("Starting to fetch data from $endpoint", [
        'initial_params' => $params
      ]);

      do {
        $params['sp.page'] = $pageNumber;
        $response = $this->dataClient()->get($endpoint, $params);

        if ($response->failed()) {
          throw new Exception('Failed to fetch module data from Accurate');
        }
        $responseData = $response->json();
        Log::info("Fetched page $pageNumber from $endpoint", [
          'response' => $responseData
        ]);
        $pageData = $responseData['d'] ?? [];

        $allData = array_merge($allData, $pageData);
        $hasMoreData = count($pageData) === $pageSize;

        $pageNumber++;
        if ($pageNumber > 100) {
          break;
        }
      } while ($hasMoreData);
      return $allData;
    } catch (\Exception $e) {
      throw $e;
    }
  }

  // ===GET ACCURATE DATABASE ID===
  protected function getAccurateDatabaseId(?array $targetDbInfo): ?int
  {
    if ($targetDbInfo && isset($targetDbInfo['id'])) {
      return $targetDbInfo['id'];
    }

    $accurateDatabaseId = session('accurate_database.id') ?? null;
    if (!$accurateDatabaseId) {
      $dbId = session('database_id');
      if ($dbId) {
        $accurateDb = \App\Models\AccurateDatabase::where('db_id', $dbId)->first();
        $accurateDatabaseId = $accurateDb?->id;
      }
    }

    return $accurateDatabaseId;
  }

  // ===STORE ENTITY MAPPINGS===
  protected function storeEntityMappings(
    string $module,
    array $originalData,
    array $responseData,
    int $accurateDatabaseId
  ): void {
    $numberField = $this->getNumberFieldForModule($module, $originalData[0] ?? []);
    if (!$numberField) {
      return;
    }

    $results = $responseData['d'] ?? [];

    foreach ($results as $index => $result) {
      if (!isset($result['s']) || $result['s'] !== true) {
        continue;
      }

      // For modules using 'id' as key: the inject loop replaces $item['id'] with
      // the Accurate entity ID, so we must read from '_sourceId' (the original
      // captured id) instead of the now-overwritten $numberField.
      if ($numberField === 'id' && isset($originalData[$index]['_sourceId'])) {
        $sourceIdentifier = $originalData[$index]['_sourceId'];
      } else {
        $sourceIdentifier = $originalData[$index][$numberField] ?? null;
      }

      $accurateId = $result['r']['id'] ?? null;
      // Always use $sourceIdentifier as the mapping key (accurate_number) so
      // getAccurateId() lookup on the next push can find the record.
      // For transaction modules Accurate auto-assigns a different number;
      // using the API-returned number here would cause a lookup mismatch.
      $apiNumber = $result['r']['number'] ?? $result['r']['no'] ?? $result['r']['vendorNo'] ?? $result['r']['customerNo'] ?? null;

      // For UPDATE items, numberField was unset from data (by injection loop),
      // so sourceIdentifier may be null. Fall back to apiNumber from response.
      $effectiveIdentifier = $sourceIdentifier ?? $apiNumber;
      $accurateNumber = $effectiveIdentifier; // always key by source identifier

      if ($effectiveIdentifier && $accurateId) {
        // Check if this was CREATE or UPDATE
        $wasUpdate = isset($originalData[$index]['_isUpdate']) && $originalData[$index]['_isUpdate'] === true;
        
        \App\Models\AccurateEntityMapping::storeMapping(
          $accurateDatabaseId,
          $module,
          $effectiveIdentifier,
          $accurateId,
          $accurateNumber,
          [
            'synced_at' => now()->toIso8601String(),
            'endpoint' => '/api/' . $module . '/bulk-save.do',
            'operation' => $wasUpdate ? 'update' : 'create'
          ]
        );

        // ===UPDATE TRANSACTION STATUS===
        $this->updateTransactionStatus(
          $effectiveIdentifier,
          $module,
          $accurateDatabaseId,
          $wasUpdate ? \App\Models\Transaction::STATUS_PUSHED_UPDATE : \App\Models\Transaction::STATUS_PUSHED_CREATE
        );

        Log::info("Entity mapping stored", [
          'module' => $module,
          'source_identifier' => $sourceIdentifier,
          'accurate_id' => $accurateId,
          'accurate_number' => $accurateNumber,
          'operation' => $wasUpdate ? 'UPDATE' : 'CREATE'
        ]);
      }
    }
  }

  // ===UPDATE TRANSACTION STATUS===
  protected function updateTransactionStatus(
    string $transactionNo,
    string $module,
    int $accurateDatabaseId,
    string $status
  ): void {
    try {
      // Find module record
      $moduleRecord = \App\Models\Module::where('accurate_database_id', $accurateDatabaseId)
        ->where('slug', $module)
        ->first();
      
      if (!$moduleRecord) {
        Log::warning("Module record not found for transaction status update", [
          'module' => $module,
          'accurate_database_id' => $accurateDatabaseId
        ]);
        return;
      }

      // Find and update the most recent transaction with this transaction_no
      // Order by id DESC to get the latest one if there are duplicates
      $transaction = \App\Models\Transaction::where('transaction_no', $transactionNo)
        ->where('module_id', $moduleRecord->id)
        ->where('accurate_database_id', $accurateDatabaseId)
        ->orderBy('id', 'desc')
        ->first();

      if ($transaction) {
        $transaction->update([
          'push_status' => $status,
          'last_pushed_at' => now(),
          'push_count' => $transaction->push_count + 1
        ]);

        Log::info("Transaction status updated", [
          'transaction_id' => $transaction->id,
          'transaction_no' => $transactionNo,
          'module' => $module,
          'status' => $status,
          'push_count' => $transaction->push_count
        ]);
      } else {
        Log::warning("Transaction not found for status update", [
          'transaction_no' => $transactionNo,
          'module' => $module,
          'accurate_database_id' => $accurateDatabaseId
        ]);
      }
    } catch (\Exception $e) {
      Log::error("Failed to update transaction status", [
        'transaction_no' => $transactionNo,
        'module' => $module,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
    }
  }

  // ===GET NUMBER FIELD FOR MODULE===
  protected function getNumberFieldForModule(string $module, array $firstItem): ?string
  {
    // Module-specific mapping takes priority over generic field detection
    $moduleFieldMap = [
      // Modules with unique number/code fields
      'vendor'               => 'vendorNo',
      'customer'             => 'customerNo',
      'item'                 => 'no',
      'glaccount'            => 'no',
      'employee'             => 'no',
      'tax'                  => 'no',
      'project'              => 'no',
      'warehouse'            => 'id',
      'branch'               => 'id',
      'department'           => 'id',
      'sales-order'          => 'number',
      'purchase-order'       => 'number',
      'sales-invoice'        => 'number',
      'purchase-invoice'     => 'number',
      'delivery-order'       => 'number',
      'receive-item'         => 'receiveNumber',
      'sales-quotation'      => 'number',
      'purchase-requisition' => 'number',
      'sales-return'         => 'number',
      'purchase-return'      => 'number',
      'sales-receipt'        => 'number',
      'purchase-payment'     => 'number',
      'item-transfer'        => 'number',
      'job-order'            => 'number',
      'work-order'           => 'number',
      // Modules without a unique number field — use source 'id' as key
      'item-category'        => 'id',
      'unit'                 => 'id',
      'vendor-category'      => 'id',
      'vendor-claim'         => 'id',
      'vendor-price'         => 'id',
      'customer-category'    => 'id',
      'currency'             => 'id',
      'fob'                  => 'id',
      'data-classification'  => 'id',
      'price-category'       => 'id',
      'bill-of-material'     => 'id',
    ];

    if (isset($moduleFieldMap[$module])) {
      return $moduleFieldMap[$module];
    }

    // Fallback: detect from first item (excludes 'name' — not a reliable unique key)
    $possibleFields = ['no', 'vendorNo', 'customerNo', 'number'];

    foreach ($possibleFields as $field) {
      if (isset($firstItem[$field]) && !empty($firstItem[$field])) {
        return $field;
      }
    }

    return null;
  }
}
