<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'capture_log_id')) {
                $table->unsignedBigInteger('capture_log_id')->nullable()->after('accurate_database_id');
                $table->index(['capture_log_id', 'module_id', 'accurate_database_id', 'transaction_no'], 'transactions_capture_dedupe_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'capture_log_id')) {
                $table->dropIndex('transactions_capture_dedupe_idx');
                $table->dropColumn('capture_log_id');
            }
        });
    }
};
