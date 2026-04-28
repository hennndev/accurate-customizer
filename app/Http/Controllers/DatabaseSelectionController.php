<?php

namespace App\Http\Controllers;

use App\Models\AccurateDatabase;
use Illuminate\Http\Request;
use App\Services\AccurateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class DatabaseSelectionController extends Controller
{
  public function showSelection(AccurateService $accurate)
  {
    try {
      $databases = $accurate->getDatabaseList();
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
      $this->forceLogout();
      return redirect()->route('login')->with('info', 'Sesi Accurate Anda telah berakhir. Silakan login ulang.');
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
        return $this->handleRedirect($request, 'error', 'Gagal mendapatkan sesi untuk database yang dipilih.');
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
      return $this->handleRedirect($request, 'success', 'Successfully connected to ' . $dbData['alias']);
    } catch (Exception $e) {
      if ($this->isAccurateSessionExpired($e)) {
        $this->forceLogout();
        return redirect()->route('login')->with('info', 'Sesi Accurate Anda telah berakhir. Silakan login ulang.');
      }

      Log::error('DB_SELECTION_ERROR', ['message' => $e->getMessage()]);
      return $this->handleRedirect($request, 'error', 'Terjadi kesalahan saat memilih database: ' . $e->getMessage());
    }
  }

  private function handleRedirect(Request $request, string $type, string $message)
  {
    $previousUrl = url()->previous();

    // Jika berasal dari halaman /select-database, redirect ke route modules.index
    if (str_contains($previousUrl, '/select-database')) {
      return redirect()->route('modules.index')->with($type, $message);
    }

    // Selain itu, redirect back ke halaman sebelumnya
    return redirect()->back()->with($type, $message);
  }

  private function forceLogout(): void
  {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    request()->session()->forget([
      'accurate_access_token',
      'accurate_refresh_token',
      'accurate_database',
      'accurate_database_list_cache',
      'database_id',
      'database_name',
      'accurate_host',
    ]);
  }

  private function isAccurateSessionExpired(Exception $e): bool
  {
    $message = strtolower($e->getMessage());

    return str_contains($message, 'sesi accurate sudah habis')
      || str_contains($message, 'tidak valid')
      || str_contains($message, '401')
      || str_contains($message, '403');
  }
}
