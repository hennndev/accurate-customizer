<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("\n            DELETE t1 FROM transactions t1\n            INNER JOIN transactions t2\n                ON t1.id < t2.id\n                AND t1.accurate_database_id = t2.accurate_database_id\n                AND t1.module_id = t2.module_id\n                AND t1.transaction_no = t2.transaction_no\n        ");

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(
                ['accurate_database_id', 'module_id', 'transaction_no'],
                'transactions_unique_number_per_module_db'
            );
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_unique_number_per_module_db');
        });
    }
};
