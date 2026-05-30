<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('down_payment_purchase_invoice_mapping_number', function (Blueprint $table) {
            $table->string('db_name');
            $table->string('old_number')->nullable();
            $table->string('new_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('down_payment_purchase_invoice_mapping_number');
    }
};
