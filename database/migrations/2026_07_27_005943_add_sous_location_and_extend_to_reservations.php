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
        Schema::table('reservations', function (Blueprint $table) {
    $table->foreignId('sous_location_id')
          ->nullable()
          ->after('contract_year')
          ->constrained('sous_locations')
          ->onDelete('set null');

    $table->boolean('can_extend_days')->default(false)->after('sous_location_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            //
        });
    }
};
