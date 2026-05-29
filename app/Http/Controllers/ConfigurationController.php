<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class ConfigurationController extends Controller
{
    public function index()
    {
        // Get current settings or create default
        $setting = Setting::firstOrCreate(
            ['id' => 1],
            ['retention_days' => 30]
        );
        
        return view('configuration.index', compact('setting'));
    }
    
    public function update(Request $request)
    {
        $validated = $request->validate([
            'retention_days'               => 'required|integer|min:1|max:365',
            'sales_invoice_number_source'  => 'required|in:mapping_table,transaction_number_mappings',
            'purchase_invoice_number_source' => 'required|in:mapping_table,transaction_number_mappings',
            'receive_item_number_source'   => 'required|in:mapping_table,transaction_number_mappings',
        ]);
        
        $setting = Setting::firstOrCreate(['id' => 1]);
        $setting->update([
            'retention_days'               => $validated['retention_days'],
            'sales_invoice_number_source'  => $validated['sales_invoice_number_source'],
            'purchase_invoice_number_source' => $validated['purchase_invoice_number_source'],
            'receive_item_number_source'   => $validated['receive_item_number_source'],
        ]);
        
        return redirect()->route('configuration.index')
            ->with('success', 'Configuration updated successfully! Data retention set to ' . $validated['retention_days'] . ' days.');
    }
}
