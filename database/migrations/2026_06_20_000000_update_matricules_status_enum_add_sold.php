<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'sold' to the status ENUM
        DB::statement("ALTER TABLE matricules MODIFY status ENUM('active', 'inactive', 'sold') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        // Revert (will fail if any 'sold' records exist)
        DB::statement("ALTER TABLE matricules MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
    }
};