<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('signature_token_second_driver', 64)
                  ->nullable()
                  ->unique()
                  ->after('signature_token');

            $table->string('signature_code_second_driver', 10)
                  ->nullable()
                  ->after('signature_token_second_driver');

            $table->timestamp('signature_expires_at_second_driver')
                  ->nullable()
                  ->after('signature_code_second_driver');
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'signature_token_second_driver',
                'signature_code_second_driver',
                'signature_expires_at_second_driver',
            ]);
        });
    }
};