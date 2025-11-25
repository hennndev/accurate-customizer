<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class AccurateController extends Controller
{
    public function redirectToAccurate(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('state', $state);

        $query = http_build_query([
            'client_id' => env('ACCURATE_CLIENT_ID'), // <-- Kembali baca dari .env
            'response_type' => 'code',
            'redirect_uri' => route('accurate.callback'),
            'scope' => 'purchase_order_view delivery_order_view vendor_view', // Scope yang sudah benar
            'state' => $state,
        ]);

        return redirect(env('ACCURATE_API_URL') . '/api/authorize.do?' . $query);
    }

    public function handleCallback(Request $request)
    {
        if ($request->state !== session('state')) {
            return redirect()->route('settings.accurate')->with('error', 'Invalid state.');
        }

        try {
            $clientId = env('ACCURATE_CLIENT_ID'); // <-- Kembali baca dari .env
            $clientSecret = env('ACCURATE_CLIENT_SECRET'); // <-- Kembali baca dari .env
            $authHeader = base64_encode($clientId . ':' . $clientSecret);

            $response = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . $authHeader,
            ])->post(env('ACCURATE_API_URL') . '/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => $request->code,
                'redirect_uri' => route('accurate.callback'),
            ]);

            if ($response->failed()) {
                Log::error('ACCURATE_OAUTH_ERROR', ['response' => $response->body()]);
                throw new Exception('Gagal mendapatkan token dari Accurate: ' . ($response->json()['error_description'] ?? $response->body()));
            }

            $tokenData = $response->json();
            session([
                'accurate_access_token' => $tokenData['access_token'],
                'accurate_refresh_token' => $tokenData['refresh_token'],
            ]);

            return redirect()->route('database.selection');

        } catch (Exception $e) {
            return redirect()->route('settings.accurate')->with('error', $e->getMessage());
        }
    }

    public function disconnect(Request $request)
    {
        $request->session()->forget(['accurate_access_token', 'accurate_refresh_token', 'accurate_database']);
        return redirect()->route('settings.accurate')->with('success', 'Koneksi Accurate berhasil diputuskan.');
    }
}