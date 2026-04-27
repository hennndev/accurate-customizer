<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_list_item_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accurate_database_id')->constrained('accurate_databases')->cascadeOnDelete();
            $table->string('module_slug');
            $table->string('params_hash', 64);
            $table->unsignedBigInteger('list_item_id');
            $table->string('fallback_number')->nullable();
            $table->timestamp('captured_from_list_at')->nullable();
            $table->timestamps();

            $table->unique([
                'accurate_database_id',
                'module_slug',
                'params_hash',
                'list_item_id',
            ], 'capture_list_item_ids_unique_key');

            $table->index([
                'accurate_database_id',
                'module_slug',
                'params_hash',
            ], 'capture_list_item_ids_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_list_item_ids');
    }
};
