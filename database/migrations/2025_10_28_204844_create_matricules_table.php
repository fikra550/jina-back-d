<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matricules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->onDelete('cascade');
            $table->string('matricule_code')->unique();
            $table->decimal('kilometrage', 10, 2);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('visit_tech');
            $table->date('date_taxe_voiture')->nullable();
            $table->date('date_assurance')->nullable();
            $table->enum('vidange_status', ['done', 'not done'])->default('not done');
            
            // Maintenance fields with dates and quantities
            // Paquets de Voiture
            $table->enum('paquets_de_voiture', ['yes', 'no'])->nullable();
            $table->date('paquets_de_voiture_date')->nullable();
            $table->integer('paquets_de_voiture_count')->nullable()->default(0);
            $table->json('paquets_de_voiture_history')->nullable();
            
            // Paquets de Frein
            $table->enum('paquets_de_frein', ['yes', 'no'])->nullable();
            $table->date('paquets_de_frein_date')->nullable();
            $table->integer('paquets_de_frein_count')->nullable()->default(0);
            $table->json('paquets_de_frein_history')->nullable();
            
            // Filter Oil
            $table->enum('filter_oil', ['yes', 'no'])->nullable();
            $table->date('filter_oil_date')->nullable();
            $table->integer('filter_oil_count')->nullable()->default(0);
            $table->json('filter_oil_history')->nullable();
            
            // Filter Air
            $table->enum('filter_air', ['yes', 'no'])->nullable();
            $table->date('filter_air_date')->nullable();
            $table->integer('filter_air_count')->nullable()->default(0);
            $table->json('filter_air_history')->nullable();
            
            // Ad Blue
            $table->enum('ad_blue', ['yes', 'no'])->nullable();
            $table->date('ad_blue_date')->nullable();
            $table->decimal('ad_blue_quantity', 10, 2)->nullable()->default(0);
            $table->json('ad_blue_history')->nullable();
            
            // Oil
            $table->enum('oil', ['yes', 'no'])->nullable();
            $table->date('oil_date')->nullable();
            $table->decimal('oil_quantity', 10, 2)->nullable()->default(0);
            $table->json('oil_history')->nullable();
            
            // Additional maintenance items
            $table->json('additional_maintenance')->nullable();
            $table->json('periodic_km_maintenance')->nullable();
            
            // Vidange tracking
            $table->decimal('last_vidange_km', 10, 2)->default(0);
            $table->decimal('vidange_interval', 10, 2)->default(10000);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matricules');
    }
};