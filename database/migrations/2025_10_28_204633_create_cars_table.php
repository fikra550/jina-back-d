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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->year('year');
            $table->string('color');
            $table->string('image')->nullable();
            $table->decimal('price_per_day', 10, 2);
            $table->enum('status', ['disponible', 'non disponible'])->default('disponible');
            $table->integer('seats')->default(5);
            $table->integer('doors')->default(4);
            $table->enum('fuel_type', ['petrol', 'diesel', 'electric','hybrid'])->default('petrol');
            $table->enum('transmission', ['manual', 'automatic'])->default('automatic');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};