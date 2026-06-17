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
            $transDateExpression = "COALESCE(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.transDate')), '%d/%m/%Y'), STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.transDate')), '%Y-%m-%d'), STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.transDate')), '%d/%m/%Y %H:%i:%s'), STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(data, '$.transDate')), '%Y-%m-%d %H:%i:%s'))";
            
            $table->date('trans_date_virtual')->virtualAs($transDateExpression)->index('idx_trans_date_virtual');
            $table->string('customer_name_virtual')->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(data, '$.customer.name'))")->index('idx_customer_name_virtual');
            $table->string('program_injek_virtual')->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"PROGRAM INJEK\"'))")->index('idx_program_injek_virtual');
            $table->string('customer_program_virtual')->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"CUSTOMER PROGRAM\"'))")->index('idx_customer_program_virtual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_trans_date_virtual');
            $table->dropIndex('idx_customer_name_virtual');
            $table->dropIndex('idx_program_injek_virtual');
            $table->dropIndex('idx_customer_program_virtual');

            $table->dropColumn('trans_date_virtual');
            $table->dropColumn('customer_name_virtual');
            $table->dropColumn('program_injek_virtual');
            $table->dropColumn('customer_program_virtual');
        });
    }
};
