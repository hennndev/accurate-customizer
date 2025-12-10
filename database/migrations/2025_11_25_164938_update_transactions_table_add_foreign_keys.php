<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if doctrine/dbal is installed for renameColumn support
        // Alternative approach: drop old columns and create new ones
        Schema::table('transactions', function (Blueprint $table) {
            // Add new columns first
            $table->unsignedBigInteger('module_id')->nullable()->after('transaction_no');
            $table->unsignedBigInteger('accurate_database_id')->nullable()->after('module_id');
        });

        // Copy data from old columns to new columns
        DB::statement('UPDATE transactions SET module_id = CAST(module AS UNSIGNED), accurate_database_id = CAST(source_db AS UNSIGNED)');

        Schema::table('transactions', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn(['module', 'source_db']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Make new columns not nullable
            $table->unsignedBigInteger('module_id')->nullable(false)->change();
            $table->unsignedBigInteger('accurate_database_id')->nullable(false)->change();
            
            // Add foreign keys
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
            $table->foreign('accurate_database_id')->references('id')->on('accurate_databases')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
            $table->dropForeign(['accurate_database_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Add old columns back
            $table->string('module')->nullable();
            $table->string('source_db')->nullable();
        });

        // Copy data back
        DB::statement('UPDATE transactions SET module = CAST(module_id AS CHAR), source_db = CAST(accurate_database_id AS CHAR)');

        Schema::table('transactions', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['module_id', 'accurate_database_id']);
        });
    }
};
