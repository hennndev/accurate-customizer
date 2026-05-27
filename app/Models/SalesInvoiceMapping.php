<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceMapping extends Model
{
    protected $table = 'sales_invoice_mapping_number';

    public $timestamps = false;

    protected $fillable = [
        'db_name',
        'old_number',
        'new_number',
    ];
}
