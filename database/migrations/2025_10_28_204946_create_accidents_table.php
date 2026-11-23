<?php
// database/migrations/2025_10_28_204946_create_accidents_table.php

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
        Schema::create('accidents', function (Blueprint $table) {
            $table->id();
            $table->date('date_accident');
            $table->decimal('amount_of_losses', 10, 2);
            $table->decimal('amount_assurance', 10, 2);
            $table->string('nom_expert')->nullable();
            $table->json('img_accident')->nullable(); // Changed to JSON for multiple images
            $table->json('img_evaluation_expert')->nullable(); // Changed to JSON for multiple images
            $table->json('img_fixed')->nullable(); // Changed to JSON for multiple images
            $table->json('image_facture')->nullable(); // Changed to JSON for multiple images
            $table->enum('status', ['pending','evaluation_owner','contact expert','evaluation_expert','fixed','waiting','completed'])->default('pending');
            $table->enum('accident_type', ['grave', 'non_grave'])->default('grave');
            $table->enum('procedure_type', ['classic', 'forphie'])->default('classic');
            $table->enum('expert_decision', ['pending', 'accepted', 'rejected'])->nullable();
            $table->decimal('expert_amount', 10, 2)->nullable();
            $table->text('expert_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('matricule_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accidents');
    }
};