<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Support\Facades\Log;

class AccurateService
{

  public function getDatabaseList(): array
  {
    if (!session()->has('accurate_access_token')) {
      throw new Exception('Tidak bisa mengambil daftar database tanpa Access Token.');
    }
    $response = Http::withToken(session('accurate_access_token'))
      ->get(env('ACCURATE_API_URL') . '/api/db-list.do');

    if ($response->failed()) {
      Log::error('ACCURATE_ERROR - Gagal mengambil daftar database', $response->json() ?? ['body' => $response->body()]);
      throw new Exception("Gagal mendapatkan daftar database dari Accurate.");
    }
    return $response->json()['d'] ?? [];
  }


  public function getDatabaseHost()
  {
    $response = $this->client()->post('/api/api-token.do');
    if ($response->failed() || !isset($response->json()['d']['database']['host'])) {
      Log::error('ACCURATE_ERROR - Gagal mendapatkan host database', $response->json() ?? ['body' => $response->body()]);
      throw new Exception("Gagal mendapatkan host database dari Accurate.");
    }
    $host = $response->json()['d']['database']['host'];
    session(['accurate_host' => $host]);
    return $host;
  }


  public function bulkSaveToAccurate(string $endpoint, array $data)
  {
    // Set execution time limit to 5 minutes for large data migration
    set_time_limit(300);
    
    // Special handling for modules that only support save.do (not bulk-save)
    if (str_contains($endpoint, 'warehouse') || str_contains($endpoint, 'price-category')) {
      return $this->saveOneByOne($endpoint, $data);
    }

  // Special handling for tax module: fetch GL Account details from source IDs
  // Since GL Account IDs from source DB won't exist in target DB,
  // we fetch the account details using the source IDs to get their 'no' (account number)
  if (str_contains($endpoint, '/tax/')) {
    $data = array_map(function ($item) {
      $salesTaxGlAccountId = $item['salesTaxGlAccountId'] ?? null;
      $purchaseTaxGlAccountId = $item['purchaseTaxGlAccountId'] ?? null;
      
      // Remove the ID fields
      unset($item['salesTaxGlAccountId']);
      unset($item['purchaseTaxGlAccountId']);
      
      $taxType = $item['taxType'] ?? '';
      $salesAccountNo = null;
      $purchaseAccountNo = null;
      
      // Try to fetch GL Account details for salesTaxGlAccountId
      if ($salesTaxGlAccountId !== null) {
        try {
          $response = $this->dataClient()->get('/api/glaccount/detail.do', [
            'id' => $salesTaxGlAccountId
          ]);
          
          Log::info('TAX_SALES_GL_ACCOUNT_DETAIL_FETCH', [
            'sourceId' => $salesTaxGlAccountId,
            'response' => $response->json()
          ]);
          
          if ($response->successful() && isset($response->json()['d']['no'])) {
            $salesAccountNo = $response->json()['d']['no'];
          }
        } catch (\Exception $e) {
          Log::error('TAX_SALES_GL_ACCOUNT_FETCH_ERROR', [
            'sourceId' => $salesTaxGlAccountId,
            'error' => $e->getMessage()
          ]);
        }
      }
      
      // Try to fetch GL Account details for purchaseTaxGlAccountId
      if ($purchaseTaxGlAccountId !== null) {
        try {
          $response = $this->dataClient()->get('/api/glaccount/detail.do', [
            'id' => $purchaseTaxGlAccountId
          ]);
          
          Log::info('TAX_PURCHASE_GL_ACCOUNT_DETAIL_FETCH', [
            'sourceId' => $purchaseTaxGlAccountId,
            'response' => $response->json()
          ]);
          
          if ($response->successful() && isset($response->json()['d']['no'])) {
            $purchaseAccountNo = $response->json()['d']['no'];
          }
        } catch (\Exception $e) {
          Log::error('TAX_PURCHASE_GL_ACCOUNT_FETCH_ERROR', [
            'sourceId' => $purchaseTaxGlAccountId,
            'error' => $e->getMessage()
          ]);
        }
      }
      
      // Set to null if not found
      $item['salesTaxGlAccountNo'] = $salesAccountNo;
      $item['purchaseTaxGlAccountNo'] = $purchaseAccountNo;
      
      Log::info('TAX_ITEM_GL_ACCOUNTS_MAPPED', [
        'taxCode' => $item['taxCode'] ?? 'N/A',
        'taxType' => $taxType,
        'sourceIds' => [
          'sales' => $salesTaxGlAccountId,
          'purchase' => $purchaseTaxGlAccountId
        ],
        'mappedAccounts' => [
          'salesTaxGlAccountNo' => $salesAccountNo,
          'purchaseTaxGlAccountNo' => $purchaseAccountNo
        ],
        'found' => [
          'sales' => $salesAccountNo !== null,
          'purchase' => $purchaseAccountNo !== null
        ]
      ]);
      
      return $item;
    }, $data);
  }    $cleanedData = array_map(function ($item) use ($endpoint) {
      return $this->cleanDataItem($item, $endpoint);
    }, $data);

    $requestBody = [
      'data' => $cleanedData
    ];

    Log::info("RAW_DATA", [
      "data" => $cleanedData
    ]);

    $response = $this->dataClient()->post($endpoint, $requestBody);
    Log::info('BULK_SAVE_RESPONSE', [
      'endpoint' => $endpoint,
      'status' => $response->status(),
      'response_data' => $response->json(),
    ]);
    return $response->json();
  }

  protected function saveOneByOne(string $endpoint, array $data)
  {
    // Set execution time limit to 5 minutes for large data migration
    set_time_limit(300);
    
    $results = [];
    $successCount = 0;
    $failedCount = 0;

    // Replace bulk-save with save.do
    $saveEndpoint = str_replace('bulk-save.do', 'save.do', $endpoint);
    
    // Get module name for logging
    $moduleName = 'Module';
    if (str_contains($endpoint, 'warehouse')) {
      $moduleName = 'WAREHOUSE';
    } elseif (str_contains($endpoint, 'price-category')) {
      $moduleName = 'PRICE_CATEGORY';
    }

    foreach ($data as $index => $item) {
      $cleanedItem = $this->cleanDataItem($item, $endpoint);
      
      Log::info("{$moduleName}_SAVE_REQUEST", [
        "index" => $index,
        "data" => $cleanedItem,
      ]);

      try {
        $response = $this->dataClient()->post($saveEndpoint, $cleanedItem);
        $result = $response->json();
        
        Log::info("{$moduleName}_SAVE_RESPONSE", [
          'index' => $index,
          'status' => $response->status(),
          'response_data' => $result,
        ]);

        $results[] = $result;
        
        if (isset($result['s']) && $result['s'] === true) {
          $successCount++;
        } else {
          $failedCount++;
        }
      } catch (\Exception $e) {
        Log::error("{$moduleName}_SAVE_ERROR", [
          'index' => $index,
          'error' => $e->getMessage(),
        ]);
        $results[] = [
          's' => false,
          'd' => $e->getMessage()
        ];
        $failedCount++;
      }
    }

    // Return response in similar format as bulk-save
    return [
      's' => $failedCount === 0,
      'd' => $results,
      'total' => count($data),
      'success' => $successCount,
      'failed' => $failedCount
    ];
  }

  
  protected function cleanDataItem(array $item, string $endpoint = ''): array
  {
    $cleaned = [];
    
    foreach ($item as $key => $value) {
      if ($key === 'id' || $key === 'vendorType') {
        continue;
      }

      // Skip locationId for warehouse
      if ($key === 'locationId' && str_contains($endpoint, 'warehouse')) {
        continue;
      }

      // Skip salesTaxGlAccountId and purchaseTaxGlAccountId for tax endpoint
      // These should be converted to *No fields in convertTaxGlAccountIds
      if (str_contains($endpoint, '/tax/') && ($key === 'salesTaxGlAccountId' || $key === 'purchaseTaxGlAccountId')) {
        continue;
      }

      // Special handling for purchase-order, purchase-invoice, purchase-payment, purchase-return and receive-item: replace vendor object with vendorNo only
      if ($key === 'vendor' && is_array($value) && (str_contains($endpoint, 'purchase-order') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'purchase-payment') || str_contains($endpoint, 'purchase-return') || str_contains($endpoint, 'receive-item'))) {
        if (isset($value['vendorNo'])) {
          $cleaned['vendorNo'] = $value['vendorNo'];
        }
        continue;
      }

      // Special handling for sales-order, sales-invoice, sales-quotation, sales-receipt and sales-return: replace customer object with customerNo only
      if ($key === 'customer' && is_array($value) && (str_contains($endpoint, 'sales-order') || str_contains($endpoint, 'sales-invoice') || str_contains($endpoint, 'sales-quotation') || str_contains($endpoint, 'sales-receipt') || str_contains($endpoint, 'sales-return'))) {
        if (isset($value['customerNo'])) {
          $cleaned['customerNo'] = $value['customerNo'];
        }
        continue;
      }

      // Special handling for bank-transfer: replace fromBank and toBank objects with fromBankNo and toBankNo
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

      // Special handling for expense: replace expensePayable object with expensePayableNo
      if ($key === 'expensePayable' && is_array($value) && str_contains($endpoint, 'expense')) {
        if (isset($value['no'])) {
          $cleaned['expensePayableNo'] = $value['no'];
        }
        continue;
      }

      // Special handling for sales-receipt: replace bank object with bankNo
      if ($key === 'bank' && is_array($value) && str_contains($endpoint, 'sales-receipt')) {
        if (isset($value['no'])) {
          $cleaned['bankNo'] = $value['no'];
        }
        continue;
      }

      // Replace transDate with current date for item-adjustment
      if ($key === 'transDate' && str_contains($endpoint, 'item-adjustment')) {
        $value = now()->format('d/m/Y');
      }

      if ($key === 'npwpNo' && is_string($value)) {
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
      
      if (is_array($value)) {
        if (empty($value)) {
          continue;
        }
        
        $cleanedArray = [];
        foreach ($value as $subKey => $subValue) {
          if (is_array($subValue)) {
            $cleanedSubItem = $this->cleanDataItem($subValue, $endpoint);
            if (!empty($cleanedSubItem)) {
              // Special handling for detailItem in purchase-order, purchase-invoice, purchase-return, receive-item, sales-order, sales-invoice, sales-quotation, sales-return and job-order: flatten item object
              if ($key === 'detailItem' && (str_contains($endpoint, 'purchase-order') || str_contains($endpoint, 'purchase-invoice') || str_contains($endpoint, 'purchase-return') || str_contains($endpoint, 'receive-item') || str_contains($endpoint, 'sales-order') || str_contains($endpoint, 'sales-invoice') || str_contains($endpoint, 'job-order') || str_contains($endpoint, 'sales-quotation') || str_contains($endpoint, 'sales-return'))) {
                if (isset($cleanedSubItem['item']['no'])) {
                  $cleanedSubItem['itemNo'] = $cleanedSubItem['item']['no'];
                  unset($cleanedSubItem['item']);
                }
              }
              
              // Special handling for detailItem in item-adjustment: only keep specific fields
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

              // Special handling for detailSerialNumber in item and job-order endpoint: add serialNumberNo
              if ($key === 'detailSerialNumber' && (str_contains($endpoint, '/item/') || str_contains($endpoint, 'job-order'))) {
                if (isset($cleanedSubItem['serialNumber']['number'])) {
                  $cleanedSubItem['serialNumberNo'] = $cleanedSubItem['serialNumber']['number'];
                }
              }

              // Special handling for detailAccount in expense endpoint: flatten account object to accountNo
              if ($key === 'detailAccount' && str_contains($endpoint, 'expense')) {
                if (isset($cleanedSubItem['account']['no'])) {
                  $cleanedSubItem['accountNo'] = $cleanedSubItem['account']['no'];
                  unset($cleanedSubItem['account']);
                }
              }

              // Special handling for detailJournalVoucher in journal-voucher endpoint: flatten glAccount object to accountNo
              if ($key === 'detailJournalVoucher' && str_contains($endpoint, 'journal-voucher')) {
                if (isset($cleanedSubItem['glAccount']['no'])) {
                  $cleanedSubItem['accountNo'] = $cleanedSubItem['glAccount']['no'];
                  unset($cleanedSubItem['glAccount']);
                }
              }
              
              $cleanedArray[] = $cleanedSubItem;
            }
          } else {
            if ($subKey === 'id' || $subKey === 'vendorType') {
              continue;
            }
            
            if ($subValue !== null && $subValue !== '' && 
                !(str_ends_with($subKey, 'Id') && $subValue === 0)) {
              $cleanedArray[$subKey] = $subValue;
            }
          }
        }
        
        if (!empty($cleanedArray)) {
          $cleaned[$key] = $cleanedArray;
        }
        continue;
      }
      $cleaned[$key] = $value;
    }  
    return $cleaned;
  }


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
      ->timeout(300) // Set timeout to 5 minutes for large data operations
      ->acceptJson()
      ->baseUrl($host . '/accurate');
  }

  public function openDatabaseById(int $dbId): ?array
  {
    if (!session()->has('accurate_access_token')) {
      throw new Exception('Tidak bisa membuka database tanpa Access Token.');
    }

    try {
      $response = Http::withOptions([
        'track_redirects' => true
      ])->withToken(session('accurate_access_token'))
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
        Log::info('Accurate host redirected and updated.', ['old_host' => session('accurate_database.host'), 'new_host' => $newHost]);
      }
      return $responseData;
    } catch (Exception $e) {
      Log::error('ACCURATE_ERROR - Gagal membuka database ID: ' . $dbId, ['error' => $e->getMessage()]);
      return null;
    }
  }

  protected function convertTaxGlAccountIds(array $taxItem): array
  {
    Log::info('TAX_ITEM_BEFORE_CONVERSION', [
      'data' => $taxItem
    ]);

    // Convert salesTaxGlAccountId to salesTaxGlAccountNo
    // Strategy: Fetch GL Account from SOURCE DB by ID to get the account 'no',
    // then use that 'no' directly in target (account numbers should be consistent)
    if (isset($taxItem['salesTaxGlAccountId']) && $taxItem['salesTaxGlAccountId'] !== null) {
      try {
        $accountNo = $this->getGlAccountNoFromSourceById($taxItem['salesTaxGlAccountId']);
        if ($accountNo) {
          $taxItem['salesTaxGlAccountNo'] = $accountNo;
          unset($taxItem['salesTaxGlAccountId']);
          Log::info('TAX_SALES_CONVERSION_SUCCESS', [
            'sourceId' => $taxItem['salesTaxGlAccountId'] ?? 'unset',
            'accountNo' => $accountNo
          ]);
        } else {
          Log::warning('TAX_SALES_CONVERSION_FAILED', [
            'sourceId' => $taxItem['salesTaxGlAccountId'],
            'reason' => 'GL Account not found in source DB'
          ]);
          // Remove the Id field even if conversion failed to avoid API error
          unset($taxItem['salesTaxGlAccountId']);
        }
      } catch (\Exception $e) {
        Log::error('TAX_SALES_GLACCOUNT_FETCH_ERROR', [
          'sourceId' => $taxItem['salesTaxGlAccountId'],
          'error' => $e->getMessage()
        ]);
        // Remove the Id field even if error occurred
        unset($taxItem['salesTaxGlAccountId']);
      }
    }

    // Convert purchaseTaxGlAccountId to purchaseTaxGlAccountNo
    if (isset($taxItem['purchaseTaxGlAccountId']) && $taxItem['purchaseTaxGlAccountId'] !== null) {
      try {
        $accountNo = $this->getGlAccountNoFromSourceById($taxItem['purchaseTaxGlAccountId']);
        if ($accountNo) {
          $taxItem['purchaseTaxGlAccountNo'] = $accountNo;
          unset($taxItem['purchaseTaxGlAccountId']);
          Log::info('TAX_PURCHASE_CONVERSION_SUCCESS', [
            'sourceId' => $taxItem['purchaseTaxGlAccountId'] ?? 'unset',
            'accountNo' => $accountNo
          ]);
        } else {
          Log::warning('TAX_PURCHASE_CONVERSION_FAILED', [
            'sourceId' => $taxItem['purchaseTaxGlAccountId'],
            'reason' => 'GL Account not found in source DB'
          ]);
          // Remove the Id field even if conversion failed to avoid API error
          unset($taxItem['purchaseTaxGlAccountId']);
        }
      } catch (\Exception $e) {
        Log::error('TAX_PURCHASE_GLACCOUNT_FETCH_ERROR', [
          'sourceId' => $taxItem['purchaseTaxGlAccountId'],
          'error' => $e->getMessage()
        ]);
        // Remove the Id field even if error occurred
        unset($taxItem['purchaseTaxGlAccountId']);
      }
    }

    Log::info('TAX_ITEM_AFTER_CONVERSION', [
      'data' => $taxItem
    ]);

    return $taxItem;
  }

  protected function getGlAccountNoFromSourceById(int $glAccountId): ?string
  {
    try {
      // Get GL Account from the SOURCE database (stored in transactions table)
      // The transaction data comes from source DB, so we need to fetch GL Account details from source
      // We'll use the fetchModuleData which uses dataClient (target DB session)
      // But for Tax GL Accounts, we need to get the 'no' from the original data
      
      // Since we're working with data that was fetched from source DB and stored in transactions table,
      // the glAccountId is from source DB. We need to fetch that account's 'no' from source.
      // However, the current session is pointing to TARGET DB.
      
      // For now, let's try to get GL Account list and find by ID
      $glAccounts = $this->fetchModuleData('/api/glaccount/list.do', [
        'sp.pageSize' => 10000 // Get all accounts
      ]);
      
      // Find GL Account with matching ID
      foreach ($glAccounts as $account) {
        if (isset($account['id']) && $account['id'] === $glAccountId) {
          $accountNo = $account['no'] ?? null;
          
          Log::info('GLACCOUNT_NO_FOUND_IN_LIST', [
            'sourceId' => $glAccountId,
            'accountNo' => $accountNo
          ]);
          
          return $accountNo;
        }
      }
      
      Log::warning('GLACCOUNT_NO_NOT_FOUND_IN_LIST', [
        'sourceId' => $glAccountId,
        'totalAccountsChecked' => count($glAccounts)
      ]);
      
      return null;
    } catch (\Exception $e) {
      Log::error('GLACCOUNT_FETCH_FROM_SOURCE_ERROR', [
        'sourceId' => $glAccountId,
        'error' => $e->getMessage()
      ]);
      return null;
    }
  }


  public function fetchModuleData(string $endpoint, array $params = []): array
  {
    try {
      // Add default pagination parameter to get more data (default is 20)
      // Set to 1000 to get large datasets in one request
      if (!isset($params['sp.pageSize'])) {
        $params['sp.pageSize'] = 1000;
      }
      
      $response = $this->dataClient()->get($endpoint, $params);
      if ($response->failed()) {
        Log::error('ACCURATE_FETCH_MODULE_ERROR', [
          'endpoint' => $endpoint,
          'params' => $params,
          'response' => $response->json()
        ]);
        throw new Exception('Failed to fetch module data from Accurate');
      }

      return $response->json()['d'] ?? [];
    } catch (\Exception $e) {
      Log::error('ACCURATE_FETCH_MODULE_EXCEPTION', [
        'endpoint' => $endpoint,
        'params' => $params,
        'message' => $e->getMessage()
      ]);
      throw $e;
    }
  }
}
