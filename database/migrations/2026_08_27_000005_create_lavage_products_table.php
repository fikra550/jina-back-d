<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lavage_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lavage_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['lavage_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lavage_products');
    }
};