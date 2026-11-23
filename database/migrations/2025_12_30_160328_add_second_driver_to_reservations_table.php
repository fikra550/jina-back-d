<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {

            $table->boolean('has_second_driver')
                  ->default(false)
                  ->after('client_id');

            $table->foreignId('second_driver_client_id')
                  ->nullable()
                  ->after('has_second_driver')
                  ->constrained('clients')
                  ->onDelete('set null');

            $table->text('reception_notes')
                  ->nullable()
                  ->after('notes');      
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['second_driver_client_id']);
            $table->dropColumn([
                'second_driver_client_id',
                'has_second_driver',
                'reception_notes'
            ]);
        });
    }
};
