<?php
// database/migrations/2025_10_28_204933_create_reservations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            // New time fields
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('18:00:00');
            // New rental days field (alternative to end_date)
            $table->integer('rental_days')->nullable();
            $table->decimal('total_price', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2);
            // ✅ CORRECTED: Retirer la clause ->after() pour le champ JSON
            $table->json('payment_history')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled','contacted' ,'completed','retard'])->default('pending');
            // Changed from matricule string to matricule_id foreign key
            $table->foreignId('matricule_id')->nullable()->constrained()->onDelete('cascade');
            // Notes field only
            $table->text('notes')->nullable();
            
            // ✅ NEW: Kilometer fields for historical data
            $table->decimal('kilometrage_sortie', 10, 2)->nullable();
            $table->decimal('kilometrage_entree', 10, 2)->nullable();
            $table->decimal('matricule_kilometrage_at_start', 10, 2)->nullable();
            $table->decimal('matricule_kilometrage_at_end', 10, 2)->nullable();
            
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};