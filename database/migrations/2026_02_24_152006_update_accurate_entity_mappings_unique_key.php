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
        Schema::table('accurate_entity_mappings', function (Blueprint $table) {
            // Drop old unique constraint on source_identifier
            $table->dropUnique('unique_entity_mapping');

            // Add new unique constraint on accurate_number
            $table->unique(['accurate_database_id', 'module_slug', 'accurate_number'], 'unique_entity_mapping_by_number');

            // Add index on accurate_number for lookup performance
            $table->index('accurate_number', 'idx_accurate_number');
        });
    }

    public function down(): void
    {
        Schema::table('accurate_entity_mappings', function (Blueprint $table) {
            $table->dropUnique('unique_entity_mapping_by_number');
            $table->dropIndex('idx_accurate_number');
            $table->unique(['accurate_database_id', 'module_slug', 'source_identifier'], 'unique_entity_mapping');
        });
    }
};
