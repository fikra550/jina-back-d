<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->json('signatures')->nullable()->after('payment_history');
            $table->string('signature_code', 10)->nullable();
            $table->string('signature_token', 64)->nullable()->unique();
            $table->timestamp('signature_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('signatures');
        });
    }
};