<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Support\Facades\Log;

class AccurateService
{
  // ===GET DATABASE LIST===
  public function getDatabaseList(): array {
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
  public function getDatabaseHost() {
    $response = $this->client()->post('/api/api-token.do');
    if ($response->failed() || !isset($response->json()['d']['database']['host'])) {
      throw new Exception("Gagal mendapatkan host database dari Accurate.");
    }
    $host = $response->json()['d']['database']['host'];
    session(['accurate_host' => $host]);
    return $host;
  }

  // ===BULK SAVE TO ACCURATE===
  public function bulkSaveToAccurate(string $endpoint, array $data, ?array $targetDbInfo = null) {
    // Execution time 10 minutes untuk large data (prevent something worse)
    set_time_limit(600);
    if (
      str_contains($endpoint, 'warehouse') || 
      str_contains($endpoint, 'price-category') || 
      str_contains($endpoint, 'work-order') || 
      str_contains($endpoint, 'bill-of-material')) {
      return $this->saveOneByOne($endpoint, $data, $targetDbInfo);
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
      return $this->cleanDataItem($item, $endpoint);
    }, $data);

    $requestBody = [
      'data' => $cleanedData
    ];
    $response = $client->post($endpoint, $requestBody);
    $responseData = $response->json();
    $this->storeNumberMappings($endpoint, $data, $responseData, $targetDbInfo);
    return $responseData;
  }

  // ===STORE NUMBER MAPPINGS===
  protected function storeNumberMappings(string $endpoint, array $originalData, array $responseData, ?array $targetDbInfo = null): void {
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
  protected function saveOneByOne(string $endpoint, array $data, ?array $targetDbInfo = null) {
    // Execution time 10 minutes untuk large data (prevent something worse)
    set_time_limit(600);

    // Determine which client to use
    $client = $targetDbInfo ? $this->dataClientForDatabase($targetDbInfo) : $this->dataClient();

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
      $cleanedItem = $this->cleanDataItem($item, $endpoint);

      try {
        $response = $client->post($saveEndpoint, $cleanedItem);
        $result = $response->json();
        $results[] = $result;

        if (isset($result['s']) && $result['s'] === true) {
          $successCount++;
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
  protected function getMappedNumber(string $moduleSlug, string $oldNumber): string {
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
  protected function cleanDataItem(array $item, string $endpoint = ''): array {
    $handler = \App\Modules\ModuleManager::forEndpoint($endpoint);
    $sharedContext = [];
    $meta = [];
    $handler->transformDetail($item, $sharedContext, $meta);
    
    $cleaned = [];

    foreach ($item as $key => $value) {
      // ===START SKIP FIELDS===
      if ($key === 'id' || $key === 'vendorType') {
        continue;
      }
      if ($key === 'transactionType' && str_contains($endpoint, 'journal-voucher')) {
        continue;
      }
      if ($key === 'locationId' && str_contains($endpoint, 'warehouse')) {
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
        str_contains($endpoint, 'item-transfer')
      )) {
        continue;
      }
      // ===END SKIP FIELDS===

      // ===START TRANSFORM FIELDS===
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
          $cleaned['invoiceNumber'] = $value['number'];
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
      if ($key === 'item' && is_array($value) && str_contains($endpoint, 'bill-of-material')) {
        if (isset($value['no'])) {
          $cleaned['itemNo'] = $value['no'];
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
            $cleanedSubItem = $this->cleanDataItem($subValue, $endpoint);
            if (!empty($cleanedSubItem)) {
              if ($key === 'detailItem' && (str_contains($endpoint, 'purchase-order') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'purchase-return') || str_contains($endpoint, 'receive-item') || str_contains($endpoint, 'sales-order') || str_contains($endpoint, 'sales-invoice') || str_contains($endpoint, 'job-order') || str_contains($endpoint, 'sales-quotation') || str_contains($endpoint, 'sales-return') || str_contains($endpoint, 'delivery-order') || str_contains($endpoint, 'item-transfer'))) {
                if (isset($cleanedSubItem['item']['no'])) {
                  $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                  unset($cleanedSubItem['item']);
                }
                if (isset($cleanedSubItem['purchaseOrder']['number']) && !str_contains($endpoint, "receive-item")) {
                  $cleanedSubItem['purchaseOrderNumber'] = $this->getMappedNumber(
                    'purchase-order',
                    $cleanedSubItem['purchaseOrder']['number']
                  );
                  unset($cleanedSubItem['purchaseOrder']);
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

              if ($key === 'detailInvoice' && str_contains($endpoint, 'purchase-payment')) {
                if (isset($cleanedSubItem['invoice']['number'])) {
                  $cleanedSubItem['invoiceNo'] = $cleanedSubItem['invoice']['number'];
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
  protected function dataClient() {
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
  protected function dataClientForDatabase(array $dbInfo) {
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
  public function openDatabaseById(int $dbId): ?array {
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
  protected function convertTaxGlAccountIds(array $taxItem): array {
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
  protected function getGlAccountNoFromSourceById(int $glAccountId): ?string {
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
  public function fetchModuleData(string $endpoint, array $params = []): array {
    try {
      $allData = [];
      $pageNumber = 1;
      $pageSize = 100;
      $params['sp.pageSize'] = $pageSize;
      do {
        $params['sp.page'] = $pageNumber;
        $response = $this->dataClient()->get($endpoint, $params);
        if ($response->failed()) {
          throw new Exception('Failed to fetch module data from Accurate');
        }
        $responseData = $response->json();
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
}
