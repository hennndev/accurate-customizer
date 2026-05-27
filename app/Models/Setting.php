<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    
    /** @var array<string> */
    public const SALES_INVOICE_NUMBER_SOURCES = [
        'mapping_table'              => 'Sales Invoice Mapping Table (sales_invoice_mapping_number)',
        'transaction_number_mappings' => 'Transaction Number Mappings (transaction_number_mappings)',
    ];

    protected $fillable = [
        'retention_days',
        'migrate_per_page',
        'sales_invoice_number_source',
    ];
    
    protected $casts = [
        'retention_days' => 'integer',
        'migrate_per_page' => 'integer',
    ];
}
