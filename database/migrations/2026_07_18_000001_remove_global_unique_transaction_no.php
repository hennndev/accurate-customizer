<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $tableName, string $indexName): bool
    {
        $databaseName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$databaseName, $tableName, $indexName]
        );

        return $result !== null;
    }

    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if ($this->indexExists('transactions', 'transactions_unique_transaction_no')) {
                $table->dropUnique('transactions_unique_transaction_no');
            }

            if (!$this->indexExists('transactions', 'transactions_unique_number_per_module_db')) {
                $table->unique(
                    ['accurate_database_id', 'module_id', 'transaction_no'],
                    'transactions_unique_number_per_module_db'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if ($this->indexExists('transactions', 'transactions_unique_number_per_module_db')) {
                $table->dropUnique('transactions_unique_number_per_module_db');
            }

            if (!$this->indexExists('transactions', 'transactions_unique_transaction_no')) {
                // Delete duplicate transaction_no globally before applying the global unique constraint
                DB::statement("
                    DELETE t1 FROM transactions t1
                    INNER JOIN transactions t2
                        ON t1.id < t2.id
                        AND t1.transaction_no = t2.transaction_no
                ");
                
                $table->unique('transaction_no', 'transactions_unique_transaction_no');
            }
        });
    }
};
