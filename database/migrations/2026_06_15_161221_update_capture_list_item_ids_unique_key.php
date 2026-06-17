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
        DB::statement("
            DELETE t1 FROM capture_list_item_ids t1
            INNER JOIN capture_list_item_ids t2 
            WHERE t1.id > t2.id 
            AND t1.accurate_database_id = t2.accurate_database_id
            AND t1.module_slug = t2.module_slug
            AND t1.list_item_id = t2.list_item_id
        ");

        Schema::table('capture_list_item_ids', function (Blueprint $table) {
            if ($this->indexExists('capture_list_item_ids', 'capture_list_item_ids_unique_key')) {
                $table->dropUnique('capture_list_item_ids_unique_key');
            }

            $table->unique([
                'accurate_database_id',
                'module_slug',
                'list_item_id',
            ], 'capture_list_item_ids_unique_item');
        });
    }

    public function down(): void
    {
        Schema::table('capture_list_item_ids', function (Blueprint $table) {
            if ($this->indexExists('capture_list_item_ids', 'capture_list_item_ids_unique_item')) {
                $table->dropUnique('capture_list_item_ids_unique_item');
            }

            $table->unique([
                'accurate_database_id',
                'module_slug',
                'params_hash',
                'list_item_id',
            ], 'capture_list_item_ids_unique_key');
        });
    }
};
