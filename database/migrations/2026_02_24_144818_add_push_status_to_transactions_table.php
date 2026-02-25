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
            // Status: 'pending', 'pushed_create', 'pushed_update', 'failed'
            $table->string('push_status')->default('pending')->after('captured_at');
            
            // Timestamp kapan terakhir di-push
            $table->timestamp('last_pushed_at')->nullable()->after('push_status');
            
            // Jumlah berapa kali di-push (untuk tracking re-push)
            $table->integer('push_count')->default(0)->after('last_pushed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['push_status', 'last_pushed_at', 'push_count']);
        });
    }
};
