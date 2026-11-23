<?php
// database/migrations/2026_06_24_add_contract_number_to_reservations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('contract_number')->nullable()->after('status');
            $table->integer('contract_year')->nullable()->after('contract_number');
            // optional index for faster lookups
            $table->index(['contract_year', 'contract_number']);
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['contract_number', 'contract_year']);
        });
    }
};