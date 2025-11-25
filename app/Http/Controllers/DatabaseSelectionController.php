<?php

namespace App\Http\Controllers;

use App\Models\AccurateDatabase;
use Illuminate\Http\Request;
use App\Services\AccurateService;
use Illuminate\Support\Facades\Log;
use Exception;

class DatabaseSelectionController extends Controller
{
    public function showSelection(AccurateService $accurate)
    {
        try {
            $databases = $accurate->getDatabaseList();
            Log::info('ACCURATE_DB_LIST_RESPONSE', $databases);

            if (count($databases) === 1) {
                // Memanggil method openDatabaseById untuk mendapatkan host dan session
                $dbDetail = $accurate->openDatabaseById($databases[0]['id']);
                if ($dbDetail) {
                    session(['accurate_database' => $dbDetail]);
                    return redirect()->route('login.redirect')->with('success', 'Database Accurate berhasil terhubung secara otomatis.');
                }
            }

            return view('database.selection', ['databases' => $databases]);

        } catch (Exception $e) {
            session()->forget('accurate_access_token');
            return redirect()->route('accurate.auth')->with('info', 'Sesi Accurate Anda telah berakhir, silakan otorisasi ulang.');
        }
    }

    // --- METHOD INI YANG DIPERBARUI SECARA SIGNIFIKAN ---
    public function selectDatabase(Request $request, AccurateService $accurate)
    {
        $request->validate(['selected_db_json' => 'required|json']);
        
        $dbData = json_decode($request->input('selected_db_json'), true);
        
        try {
            // Selalu panggil openDatabaseById untuk mendapatkan host dan session terbaru
            $detailDb = $accurate->openDatabaseById($dbData['id']);
            
            if (!$detailDb || !isset($detailDb['session'])) {
                return back()->with('error', 'Gagal mendapatkan sesi untuk database yang dipilih.');
            }

            // Check if database already exists, if not create it
            AccurateDatabase::firstOrCreate(
                ['db_id' => $dbData['id']], // Check by db_id
                ['db_name' => $dbData['alias']] // If not exists, create with db_name
            );

            // Simpan seluruh data detail (termasuk host dan session) ke session Laravel
            session([
              'accurate_database' => $detailDb,
              'database_id' => $dbData['id'],
              'database_name' => $dbData['alias'],
            ]);
            
            // Redirect back to the previous page (works for both modules and migrate pages)
            return redirect()->back()->with('success', 'Successfully connected to ' . $dbData['alias']);

        } catch (Exception $e) {
            Log::error('DB_SELECTION_ERROR', ['message' => $e->getMessage()]);
            return back()->with('error', 'Terjadi kesalahan saat memilih database: ' . $e->getMessage());
        }
    }
}