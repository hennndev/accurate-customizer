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
    Schema::create('accurate_connections', function (Blueprint $table) {
        $table->id();
        $table->string('alias'); // Nama yang mudah dikenali, cth: "PT Malen"
        $table->string('client_id');
        $table->text('client_secret'); // Dibuat text agar aman saat dienkripsi
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accurate_connections');
    }
};
