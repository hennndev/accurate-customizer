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
        Schema::create('transaction_number_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accurate_database_id')->constrained('accurate_databases')->onDelete('cascade');
            $table->string('module_slug'); // e.g., 'purchase-order', 'sales-invoice'
            $table->string('old_number')->index(); // Original number from source DB
            $table->string('new_number')->index(); // New number from target DB
            $table->text('response_data')->nullable(); // Store full response for debugging
            $table->timestamps();
            
            // Unique constraint: one old number maps to one new number per database & module
            $table->unique(['accurate_database_id', 'module_slug', 'old_number'], 'unique_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_number_mappings');
    }
};
