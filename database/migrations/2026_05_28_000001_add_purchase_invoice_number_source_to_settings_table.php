<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'purchase_invoice_number_source')) {
                $table->string('purchase_invoice_number_source', 50)
                    ->default('mapping_table')
                    ->after('sales_invoice_number_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'purchase_invoice_number_source')) {
                $table->dropColumn('purchase_invoice_number_source');
            }
        });
    }
};
