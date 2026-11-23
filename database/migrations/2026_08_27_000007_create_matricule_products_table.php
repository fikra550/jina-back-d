<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('matricule_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricule_id')->constrained('matricules')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->decimal('volume_used', 10, 2)->nullable(); // litres utilisés (produits liquides)
            $table->enum('purpose', ['maintenance', 'repair', 'accessory', 'other'])->default('maintenance');
            $table->text('notes')->nullable();
            $table->string('added_by')->nullable();
            $table->timestamps();

            $table->index(['matricule_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricule_products');
    }
};