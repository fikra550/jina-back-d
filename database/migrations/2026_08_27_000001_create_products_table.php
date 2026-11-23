<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('reference')->nullable()->unique();
            $table->text('description')->nullable();
            // category_id is added in a later migration (needs product_categories first)
            $table->string('unit')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->nullable();        // unités FERMÉES (ex: nombre de bidons)
            $table->decimal('volume_per_unit', 10, 2)->nullable(); // litres par unité (ex: 5 pour un bidon de 5 L) — null = produit non liquide
            $table->decimal('open_volume', 10, 2)->default(0);     // litres restants dans l'unité entamée
            $table->integer('alert_threshold')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('set null');
            $table->string('client_name')->nullable(); // client saisi à la main
            $table->foreignId('car_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('matricule_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->enum('payment_status', ['paid', 'unpaid', 'partial'])->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->date('sale_date');
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index('sale_date');
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->integer('quantity');
            $table->integer('stock_after');
            $table->decimal('volume', 10, 2)->nullable(); // litres (+ entrée / - sortie) pour les produits liquides
            $table->string('reason')->nullable();
            $table->foreignId('product_sale_id')->nullable()->constrained()->onDelete('set null');
            // lavage_id is added in a later migration
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_sales');
        Schema::dropIfExists('products');
    }
};