<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request) {
        return view('configuration.index');
    }

    public function update() {
      
    }
}
