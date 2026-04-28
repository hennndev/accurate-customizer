<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Menampilkan halaman login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Menangani permintaan autentikasi yang masuk.
     */
public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();
    $request->session()->regenerate();

    // Selalu arahkan ke halaman "HOME" (dashboard atau log Anda)
    return redirect()->intended(RouteServiceProvider::HOME);
}
    /**
     * Menghancurkan sesi terautentikasi.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget([
            'accurate_access_token',
            'accurate_refresh_token',
            'accurate_database',
            'accurate_database_list_cache',
            'database_id',
            'database_name',
            'accurate_host',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}