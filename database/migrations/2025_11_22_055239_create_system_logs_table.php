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
    Schema::create('system_logs', function (Blueprint $table) {
      $table->id();
      $table->string('event_type'); //capture, migrate, delete, mass delete, rollback, retry, validate, export
      $table->string('module')->nullable(); //sales_order, work_order, purchase, inventory
      $table->foreignId('transaction_id')->nullable()->constrained('transactions'); // link ke tabel transactions (optional karena capture event belum ada transaksi)
      $table->string('status')->default('success'); // success | failed | warning | info
      $table->json('payload')->nullable(); // raw request/response data (Accurate API response/error)
      $table->text('message')->nullable(); // readable message: "Migration success: SO-2024-22"
      $table->foreignId('user_id')->nullable()->constrained('users');
      $table->timestamps();
    });   
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('system_logs');
  }
};
