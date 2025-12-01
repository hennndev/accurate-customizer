<?php

namespace App\Http\Controllers;


class SettingsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return view('settings.index', ['user' => $user]);
    }

    public function accurateSettings()
{
    $isConnected = session()->has('accurate_access_token');
    
    return view('settings.accurate', [
        'isConnected' => $isConnected,
        'errorMessage' => null 
    ]);
}

}