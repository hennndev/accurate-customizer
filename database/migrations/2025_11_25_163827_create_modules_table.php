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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId("accurate_database_id")->constrained("accurate_databases");
            $table->string("name"); // e.g., "Sales Order"
            $table->string("slug"); // e.g., "sales-order"
            $table->string("icon")->nullable(); // e.g., "heroicon-o-document-text"
            $table->text("description")->nullable();
            $table->string("accurate_endpoint")->nullable(); // e.g., "/api/sales-order/list.do"
            $table->boolean("is_active")->default(false);
            $table->integer("order")->default(0);
            $table->timestamps();
            
            // Unique constraint: satu database hanya punya 1 module dengan slug yang sama
            $table->unique(['accurate_database_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
