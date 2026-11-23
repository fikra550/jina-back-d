<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_clients_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone');
            $table->string('email');
            $table->string('city');
            
            // KEEP ONLY ONE SET - remove duplicates
            $table->string('cin_number')->nullable();
            $table->text('cin_image')->nullable(); // For base64 images
            
            $table->string('driver_license_number')->nullable();
            $table->text('driver_license_image')->nullable(); // For base64 images
            
            // Additional fields
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->date('cin_delivre_le')->nullable();
            $table->date('permis_delivre_le')->nullable();
            
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('email');
            $table->index('telephone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};