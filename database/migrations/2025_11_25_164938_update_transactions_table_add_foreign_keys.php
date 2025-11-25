<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('module', 'module_id');
            $table->renameColumn('source_db', 'accurate_database_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->change();
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
            
            $table->unsignedBigInteger('accurate_database_id')->change();
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
            $table->renameColumn('module_id', 'module');
            $table->renameColumn('accurate_database_id', 'source_db');
        });
    }
};
