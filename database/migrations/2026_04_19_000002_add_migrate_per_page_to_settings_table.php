<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'migrate_per_page')) {
                $table->unsignedSmallInteger('migrate_per_page')->default(100)->after('retention_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'migrate_per_page')) {
                $table->dropColumn('migrate_per_page');
            }
        });
    }
};
