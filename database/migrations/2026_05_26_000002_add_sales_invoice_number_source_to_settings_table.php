<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'sales_invoice_number_source')) {
                $table->string('sales_invoice_number_source', 50)
                    ->default('mapping_table')
                    ->after('migrate_per_page');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'sales_invoice_number_source')) {
                $table->dropColumn('sales_invoice_number_source');
            }
        });
    }
};
