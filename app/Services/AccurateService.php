<?php

namespace App\Services;

use App\Models\CustomItem;
use App\Models\Quotation;
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

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
    $sessionId = $dbInfo['session']; // <-- Pastikan baris ini ada
    $accessToken = session('accurate_access_token');

    return Http::withToken($accessToken)
      ->withHeaders([
        'X-Session-ID' => $sessionId, // <-- Pastikan header ini ada
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
      // --- PERUBAHAN DIMULAI DI SINI ---
      // Tambahkan opsi untuk melacak pengalihan (redirect)
      $response = Http::withOptions([
        'track_redirects' => true
      ])->withToken(session('accurate_access_token'))
        ->post(env('ACCURATE_API_URL') . '/api/open-db.do', ['id' => $dbId]);

      if ($response->failed()) {
        return null;
      }

      $responseData = $response->json();

      // Cek apakah ada riwayat pengalihan
      $redirectHistory = $response->handlerStats()['redirect_history'] ?? [];
      if (!empty($redirectHistory)) {
        // Ambil URL terakhir (yang paling baru) dari riwayat
        $lastUrl = end($redirectHistory);

        // Ekstrak host baru dari URL tersebut
        $parsedUrl = parse_url($lastUrl);
        $newHost = ($parsedUrl['scheme'] ?? 'https') . '://' . $parsedUrl['host'];

        // Ganti host di data respons dengan host yang baru
        $responseData['host'] = $newHost;
        Log::info('Accurate host redirected and updated.', ['old_host' => session('accurate_database.host'), 'new_host' => $newHost]);
      }
      // --- AKHIR PERUBAHAN ---

      return $responseData;
    } catch (Exception $e) {
      Log::error('ACCURATE_ERROR - Gagal membuka database ID: ' . $dbId, ['error' => $e->getMessage()]);
      return null;
    }
  }

  public function getPurchaseOrders(Request $request)
  {
    try {
      $params = [
        'fields' => 'id,number,transDate,vendor', // Data yang kita perlukan
        'sort'   => 'transDate desc'              // Urutkan dari yang terbaru
      ];

      // Terapkan filter tanggal jika diisi
      if ($request->filled('start_date') && $request->filled('end_date')) {
        $params['filter.transDate.op']    = 'RANGE';
        $params['filter.transDate.val[0]'] = $request->start_date;
        $params['filter.transDate.val[1]'] = $request->end_date;
      }

      // Terapkan filter pencarian jika diisi
      if ($request->filled('search')) {
        $params['filter.keywords.op']  = 'CONTAIN';
        $params['filter.keywords.val'] = $request->search;
      }

      $response = $this->dataClient()->get('/api/purchase-order/list.do', $params);

      if ($response->failed()) {
        Log::error('Gagal mengambil daftar PO dari Accurate', $response->json());
        return collect([]);
      }

      return collect($response->json()['d'] ?? []);
    } catch (\Exception $e) {
      Log::error('Exception saat mengambil daftar PO', ['message' => $e->getMessage()]);
      return collect([]);
    }
  }

  public function getPurchaseOrderDetail(int $poId)
  {
    try {
      // Endpoint untuk detail PO
      $response = $this->dataClient()->get('/api/purchase-order/detail.do', ['id' => $poId]);

      if ($response->failed()) {
        Log::error('Gagal mengambil detail PO dari Accurate', ['po_id' => $poId, 'response' => $response->json()]);
        return null;
      }

      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat mengambil detail PO', ['po_id' => $poId, 'message' => $e->getMessage()]);
      return null;
    }
  }


  // SALES ORDER ACCURATE API
  public function getSalesOrders(Request $request)
  {
    try {
      $params = [
        'fields' => 'id,number,transDate,customer,totalAmount,status',
        'sort' => 'transDate desc',
        'sp.page' => $request->get('page', 1),
        'sp.pageSize' => 20,
      ];

      // Filter tanggal (opsional)
      if ($request->filled(['start_date', 'end_date'])) {
        $params['filter.transDate.op'] = 'BETWEEN';
        $params['filter.transDate.val[0]'] = $request->start_date;
        $params['filter.transDate.val[1]'] = $request->end_date;
      }

      // Filter pencarian (opsional)
      if ($request->filled('search')) {
        $params['filter.keywords.op'] = 'CONTAIN';
        $params['filter.keywords.val'] = $request->search;
      }

      // Request ke Accurate
      $response = $this->dataClient()->get('/api/sales-order/list.do', $params);

      if ($response->failed()) {
        Log::error('Gagal mengambil daftar Sales Order dari Accurate', [
          'response' => $response->json()
        ]);
        return collect([]);
      }

      return collect($response->json()['d'] ?? []);
    } catch (\Throwable $e) {
      Log::error('Exception saat mengambil daftar Sales Order', [
        'message' => $e->getMessage()
      ]);
      return collect([]);
    }
  }
  public function getSalesOrderDetail(int $soId)
  {
    try {
      $response = $this->dataClient()->get('/api/sales-order/detail.do', ['id' => $soId]);

      if ($response->failed()) {
        Log::error('Gagal mengambil detail Sales Order dari Accurate', [
          'so_id' => $soId,
          'response' => $response->json(),
        ]);
        return null;
      }

      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat mengambil detail Sales Order', [
        'so_id' => $soId,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }
  public function getSalesOrderByNumber(string $soNumber)
  {
    try {
      // 1️⃣ Cari sales order berdasarkan nomor
      $params = [
        'fields' => 'id,number,transDate,customer,totalAmount,status',
        'filter.number' => 'EQUALS',
        'filter.number.val' => $soNumber,
      ];

      $response = $this->dataClient()->get('/api/sales-order/list.do', $params);

      if ($response->failed()) {
        Log::error('Gagal mencari Sales Order berdasarkan nomor', [
          'so_number' => $soNumber,
          'response' => $response->json(),
        ]);
        return null;
      }

      $list = collect($response->json()['d'] ?? []);

      // 2️⃣ Pastikan ada hasil
      if ($list->isEmpty()) {
        Log::warning('Sales Order tidak ditemukan berdasarkan nomor', [
          'so_number' => $soNumber,
        ]);
        return null;
      }

      // 3️⃣ Ambil ID pertama yang ditemukan
      $soId = $list->first()['id'] ?? null;

      if (!$soId) {
        Log::warning('Sales Order ditemukan tapi tidak punya ID', [
          'so_number' => $soNumber,
          'data' => $list->first(),
        ]);
        return null;
      }

      // 4️⃣ Ambil detail berdasarkan ID
      return $this->getSalesOrderDetail((int) $soId);
    } catch (\Throwable $e) {
      Log::error('Exception saat mengambil Sales Order berdasarkan nomor', [
        'so_number' => $soNumber,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }



  // FINISHED GOOD ACCURATE API
  public function getFinishedGoodSlips(Request $request)
  {
    try {
      $params = [
        'fields' => 'id,number,transDate,warehouse,totalQuantity,status',
        'sort' => 'transDate desc',
        'sp.page' => $request->get('page', 1),
        'sp.pageSize' => 20,
      ];

      if ($request->filled(['start_date', 'end_date'])) {
        $params['filter.transDate.op'] = 'BETWEEN';
        $params['filter.transDate.val[0]'] = $request->start_date;
        $params['filter.transDate.val[1]'] = $request->end_date;
      }

      if ($request->filled('search')) {
        $params['filter.keywords.op'] = 'CONTAIN';
        $params['filter.keywords.val'] = $request->search;
      }

      $response = $this->dataClient()->get('/api/finished-good-slip/list.do', $params);

      if ($response->failed()) {
        Log::error('Gagal ambil daftar Finished Good Slip', $response->json());
        return collect([]);
      }

      return collect($response->json()['d'] ?? []);
    } catch (\Throwable $e) {
      Log::error('Exception saat ambil Finished Good Slip', ['message' => $e->getMessage()]);
      return collect([]);
    }
  }

  public function getFinishedGoodSlipDetail(int $id)
  {
    try {
      $response = $this->dataClient()->get('/api/finished-good-slip/detail.do', ['id' => $id]);
      if ($response->failed()) {
        Log::error('Gagal ambil detail Finished Good Slip', ['id' => $id, 'response' => $response->json()]);
        return null;
      }
      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception ambil detail Finished Good Slip', ['id' => $id, 'message' => $e->getMessage()]);
      return null;
    }
  }


  public function saveFinishedGoodSlip(array $data)
  {
    try {
      $response = $this->dataClient()->asForm()->post('/api/finished-good-slip/save.do', $data);

      if ($response->failed()) {
        Log::error('Gagal menyimpan Finished Good Slip', [
          'data' => $data,
          'response' => $response->json()
        ]);
        throw new Exception('Gagal menyimpan Finished Good Slip.');
      }

      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat menyimpan Finished Good Slip', [
        'message' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  public function deleteFinishedGoodSlip(int $id)
  {
    try {
      $response = $this->dataClient()->post('/api/finished-good-slip/delete.do', ['id' => $id]);

      if ($response->failed()) {
        Log::error('Gagal menghapus Finished Good Slip', [
          'id' => $id,
          'response' => $response->json()
        ]);
        throw new Exception('Gagal menghapus Finished Good Slip.');
      }

      return $response->json()['d'] ?? true;
    } catch (\Exception $e) {
      Log::error('Exception saat hapus Finished Good Slip', [
        'id' => $id,
        'message' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  public function findFinishedGoodSlipByItemNo(string $itemNo)
  {
    try {
      $params = [
        'fields' => 'id,number,transDate,warehouse,status,detailItem',
        'filter.detailItem.itemNo.op' => 'EQUAL',
        'filter.detailItem.itemNo.val' => $itemNo,
        'sort' => 'transDate desc',
        'sp.pageSize' => 1 // ambil hanya 1 yang terbaru
      ];

      $response = $this->dataClient()->get('/api/finished-good-slip/list.do', $params);

      if ($response->failed()) {
        Log::error('Gagal mencari Finished Good Slip berdasarkan itemNo', [
          'itemNo' => $itemNo,
          'response' => $response->json()
        ]);
        return null;
      }

      $data = $response->json()['d'] ?? [];
      if (empty($data)) {
        return null;
      }

      // Ambil yang pertama (hasil paling baru)
      return $data[0];
    } catch (\Exception $e) {
      Log::error('Exception saat mencari Finished Good Slip by itemNo', [
        'itemNo' => $itemNo,
        'message' => $e->getMessage()
      ]);
      return null;
    }
  }




  // WORK ORDER ACCURATE API
  public function getWorkOrders(Request $request)
  {
    try {
      $params = [
        'fields' => 'id,number,transDate,item,quantity,warehouse,status,bom',
        'sort' => 'transDate desc',
        'sp.page' => $request->get('page', 1),
        'sp.pageSize' => 20,
      ];

      // Filter tanggal (opsional)
      if ($request->filled(['start_date', 'end_date'])) {
        $params['filter.transDate.op'] = 'BETWEEN';
        $params['filter.transDate.val[0]'] = $request->start_date;
        $params['filter.transDate.val[1]'] = $request->end_date;
      }

      // Filter pencarian (opsional)
      if ($request->filled('search')) {
        $params['filter.keywords.op'] = 'CONTAIN';
        $params['filter.keywords.val'] = $request->search;
      }

      // Request ke Accurate
      $response = $this->dataClient()->get('/api/work-order/list.do', $params);

      if ($response->failed()) {
        Log::error('Gagal mengambil daftar Work Order dari Accurate', [
          'response' => $response->json()
        ]);
        return collect([]);
      }

      return collect($response->json()['d'] ?? []);
    } catch (\Throwable $e) {
      Log::error('Exception saat mengambil daftar Work Order', [
        'message' => $e->getMessage()
      ]);
      return collect([]);
    }
  }

  public function getWorkOrderDetail(int $id)
  {
    try {
      $response = $this->dataClient()->get('/api/work-order/detail.do', ['id' => $id]);

      if ($response->failed()) {
        Log::error('Gagal mengambil detail Work Order dari Accurate', [
          'id' => $id,
          'response' => $response->json(),
        ]);
        return null;
      }

      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat mengambil detail Work Order', [
        'id' => $id,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }

  /**
   * Menyimpan (membuat baru atau update) Work Order (Perintah Kerja).
   * Data $data harus dalam format form-data yang sesuai dengan Accurate API.
   *
   * Contoh $data:
   * [
   * 'transDate' => '31/10/2024',
   * 'itemNo' => 'SKU-BARANG-JADI',
   * 'quantity' => 10,
   * 'warehouseId' => 1, // ID Gudang
   * 'bomId' => 5 // ID Bill of Material
   * ]
   */
  public function saveWorkOrder(array $data)
  {
    try {
      // Endpoint save menggunakan method POST dan asForm
      $response = $this->dataClient()->asForm()->post('/api/work-order/save.do', $data);

      if ($response->failed()) {
        Log::error('Gagal menyimpan Work Order ke Accurate', [
          'data' => $data,
          'response' => $response->json()
        ]);
        throw new Exception('Gagal menyimpan Work Order: ' . ($response->json()['s']['m'] ?? 'Error tidak diketahui'));
      }

      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat menyimpan Work Order', [
        'message' => $e->getMessage(),
        'data' => $data
      ]);
      throw $e;
    }
  }

  public function deleteWorkOrder(int $id)
  {
    try {
      $response = $this->dataClient()->post('/api/work-order/delete.do', ['id' => $id]);

      if ($response->failed()) {
        Log::error('Gagal menghapus Work Order dari Accurate', [
          'id' => $id,
          'response' => $response->json()
        ]);
        throw new Exception('Gagal menghapus Work Order.');
      }

      return $response->json()['s'] ?? true; // 's' biasanya true jika sukses
    } catch (\Exception $e) {
      Log::error('Exception saat menghapus Work Order', [
        'id' => $id,
        'message' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  public function getCustomerDetail(int $id)
  {
    try {
      // Panggil endpoint Accurate untuk ambil detail customer
      $response = $this->dataClient()->get('/api/customer/detail.do', ['id' => $id]);

      // Jika gagal, log error dan kembalikan null
      if ($response->failed()) {
        Log::error('Gagal mengambil detail Customer dari Accurate', [
          'id' => $id,
          'response' => $response->json(),
        ]);
        return null;
      }

      // Ambil hasil dari response
      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat mengambil detail Customer', [
        'id' => $id,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }





  // DELIVERY ORDERS
  // DELIVERY ORDER ACCURATE API
  public function getDeliveryOrders(Request $request)
  {
    try {
      $params = [
        'fields' => 'id,number,transDate,customer,warehouse,status,vehicleNo,driverName',
        'sort' => 'transDate desc',
        'sp.page' => $request->get('page', 1),
        'sp.pageSize' => 20,
      ];

      // Filter tanggal (opsional)
      if ($request->filled(['start_date', 'end_date'])) {
        $params['filter.transDate.op'] = 'BETWEEN';
        $params['filter.transDate.val[0]'] = $request->start_date;
        $params['filter.transDate.val[1]'] = $request->end_date;
      }

      // Filter pencarian (opsional)
      if ($request->filled('search')) {
        $params['filter.keywords.op'] = 'CONTAIN';
        $params['filter.keywords.val'] = $request->search;
      }

      // Request ke Accurate
      $response = $this->dataClient()->get('/api/delivery-order/list.do', $params);

      if ($response->failed()) {
        Log::error('Gagal mengambil daftar Delivery Order dari Accurate', [
          'response' => $response->json()
        ]);
        return collect([]);
      }

      return collect($response->json()['d'] ?? []);
    } catch (\Throwable $e) {
      Log::error('Exception saat mengambil daftar Delivery Order', [
        'message' => $e->getMessage()
      ]);
      return collect([]);
    }
  }

  public function getDeliveryOrderDetail(int $id)
  {
    try {
      $response = $this->dataClient()->get('/api/delivery-order/detail.do', ['id' => $id]);

      if ($response->failed()) {
        Log::error('Gagal mengambil detail Delivery Order dari Accurate', [
          'id' => $id,
          'response' => $response->json(),
        ]);
        return null;
      }

      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat mengambil detail Delivery Order', [
        'id' => $id,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }


  public function saveDeliveryOrder(array $data)
  {
    try {
      $response = $this->dataClient()->asForm()->post('/api/delivery-order/save.do', $data);

      if ($response->failed()) {
        Log::error('Gagal menyimpan Delivery Order ke Accurate', [
          'data' => $data,
          'response' => $response->json()
        ]);

        $errorMessage = $response->json()['s']['m'] ?? 'Error tidak diketahui';
        throw new Exception('Gagal menyimpan Delivery Order: ' . $errorMessage);
      }

      return $response->json()['d'] ?? null;
    } catch (\Exception $e) {
      Log::error('Exception saat menyimpan Delivery Order', [
        'message' => $e->getMessage(),
        'data' => $data
      ]);
      throw $e;
    }
  }


  public function deleteDeliveryOrder(int $id)
  {
    try {
      $response = $this->dataClient()->post('/api/delivery-order/delete.do', ['id' => $id]);

      if ($response->failed()) {
        Log::error('Gagal menghapus Delivery Order dari Accurate', [
          'id' => $id,
          'response' => $response->json()
        ]);
        throw new Exception('Gagal menghapus Delivery Order.');
      }

      return $response->json()['s'] ?? true;
    } catch (\Exception $e) {
      Log::error('Exception saat menghapus Delivery Order', [
        'id' => $id,
        'message' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  /**
   * Fetch module data from Accurate API
   * Used for generic module data capture
   * 
   * @param string $endpoint - API endpoint
   * @param array $params - Optional query parameters (default: empty)
   * @return array - Returns data from 'd' key or empty array
   */
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
