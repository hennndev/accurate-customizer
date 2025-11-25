<?php

namespace App\Http\Controllers;

use App\Services\AccurateService; // Import service
use Illuminate\Http\Request;
use App\Models\AccurateConnection;
use Exception; // Import Exception

class SettingsController extends Controller
{
    public function index()
    {
        // Mengambil data pengguna yang sedang login
        $user = auth()->user();

        // Mengirim data ke view
        return view('settings.index', ['user' => $user]);
    }

    public function accurateSettings()
{
    // --- LOGIKA BARU YANG LEBIH SEDERHANA DAN ANDAL ---
    // Status koneksi ditentukan hanya dengan memeriksa keberadaan access token di session.
    $isConnected = session()->has('accurate_access_token');
    
    return view('settings.accurate', [
        'isConnected' => $isConnected,
        'errorMessage' => null // errorMessage tidak lagi diperlukan dengan logika ini
    ]);
}

}