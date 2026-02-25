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
        Schema::create('accurate_entity_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accurate_database_id')->constrained('accurate_databases')->onDelete('cascade');
            $table->string('module_slug'); // 'item', 'customer', 'vendor', etc
            $table->string('source_identifier'); // number/no dari source (e.g., 'ITEM-001', 'CUST-001')
            $table->bigInteger('accurate_id'); // ID dari Accurate setelah save
            $table->string('accurate_number')->nullable(); // Number yang di-generate Accurate (if different)
            $table->json('metadata')->nullable(); // Additional info (e.g., last sync time, status)
            $table->timestamps();
            
            // Unique constraint: one source identifier per module per database
            $table->unique(['accurate_database_id', 'module_slug', 'source_identifier'], 'unique_entity_mapping');
            
            // Index untuk performance
            $table->index(['accurate_database_id', 'module_slug']);
            $table->index('accurate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accurate_entity_mappings');
    }
};
