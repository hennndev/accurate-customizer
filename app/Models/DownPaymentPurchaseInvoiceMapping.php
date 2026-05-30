<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownPaymentPurchaseInvoiceMapping extends Model
{
    protected $table = 'down_payment_purchase_invoice_mapping_number';

    public $timestamps = false;

    protected $fillable = [
        'db_name',
        'old_number',
        'new_number',
    ];
}
