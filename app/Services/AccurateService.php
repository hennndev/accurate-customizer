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
    $cleanedData = array_map(function ($item) use ($endpoint) {
      return $this->cleanDataItem($item, $endpoint);
    }, $data);

    $requestBody = [
      'data' => $cleanedData
    ];
    Log::info("BULK_DATA_CLEANED", [
      "data" => $cleanedData,
    ]);
    $response = $this->dataClient()->post($endpoint, $requestBody);
    Log::info('BULK_SAVE_RESPONSE', [
      'endpoint' => $endpoint,
      'status' => $response->status(),
      'response_data' => $response->json(),
    ]);
    return $response->json();
  }

  
  protected function cleanDataItem(array $item, string $endpoint = ''): array
  {
    $cleaned = [];
    
    foreach ($item as $key => $value) {
      if ($key === 'id' || $key === 'vendorType') {
        continue;
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


  public function fetchModuleData(string $endpoint, array $params = []): array
  {
    try {
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
