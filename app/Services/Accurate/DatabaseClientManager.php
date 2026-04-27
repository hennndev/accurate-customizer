<?php

namespace App\Services\Accurate;

use Illuminate\Support\Facades\Http;
use Exception;

class DatabaseClientManager
{
    public function getDatabaseList(): array
    {
        if (!session()->has('accurate_access_token')) {
            throw new Exception('Tidak bisa mengambil daftar database tanpa Access Token.');
        }

        if (session()->has('accurate_database_list_cache')) {
            $cache = session('accurate_database_list_cache');
            if (isset($cache['timestamp']) && (time() - $cache['timestamp']) < 1800) {
                return $cache['data'];
            }
        }

        try {
            $response = Http::withToken(session('accurate_access_token'))
                ->timeout(120)
                ->connectTimeout(60)
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

    public function getDatabaseHost()
    {
        $response = $this->getDataClient()->post('/api/api-token.do');
        if ($response->failed() || !isset($response->json()['d']['database']['host'])) {
            throw new Exception("Gagal mendapatkan host database dari Accurate.");
        }
        $host = $response->json()['d']['database']['host'];
        session(['accurate_host' => $host]);
        return $host;
    }

    public function getDataClient(int $timeoutSeconds = 600)
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
            ->withHeaders(['X-Session-ID' => $sessionId])
            ->timeout($timeoutSeconds)
            ->connectTimeout(60)
            ->acceptJson()
            ->baseUrl($host . '/accurate');
    }

    public function getDataClientForDatabase(array $dbInfo, ?string $accessToken = null, int $timeoutSeconds = 600)
    {
        $resolvedAccessToken = $accessToken ?? session('accurate_access_token');
        if (!$resolvedAccessToken) {
            throw new Exception('Token Akses Accurate tidak ditemukan di session.');
        }

        $host = $dbInfo['host'];
        $sessionId = $dbInfo['session'];
        return Http::withToken($resolvedAccessToken)
            ->withHeaders(['X-Session-ID' => $sessionId])
            ->timeout($timeoutSeconds)
            ->connectTimeout(60)
            ->acceptJson()
            ->baseUrl($host . '/accurate');
    }

    public function openDatabaseById(int $dbId): ?array
    {
        if (!session()->has('accurate_access_token')) {
            throw new Exception('Tidak bisa membuka database tanpa Access Token.');
        }

        try {
            $response = Http::withOptions(['track_redirects' => true])
                ->withToken(session('accurate_access_token'))
                ->timeout(120)
                ->connectTimeout(60)
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

    public function getAccurateDatabaseId(?array $targetDbInfo): ?int
    {
        if ($targetDbInfo && isset($targetDbInfo['_local_db_id'])) {
            return (int) $targetDbInfo['_local_db_id'];
        }

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
}
