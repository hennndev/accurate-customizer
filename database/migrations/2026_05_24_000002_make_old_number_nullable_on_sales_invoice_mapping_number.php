<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE sales_invoice_mapping_number MODIFY old_number VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE sales_invoice_mapping_number SET old_number = '' WHERE old_number IS NULL");
        DB::statement('ALTER TABLE sales_invoice_mapping_number MODIFY old_number VARCHAR(255) NOT NULL');
    }
};
