<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_movements', function (Blueprint $table) {
            $table->foreignId('lavage_id')
                  ->nullable()
                  ->after('product_sale_id')
                  ->constrained('lavages')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_movements', function (Blueprint $table) {
            $table->dropForeign(['lavage_id']);
            $table->dropColumn('lavage_id');
        });
    }
};