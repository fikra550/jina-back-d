// database/migrations/xxxx_xx_xx_add_estimate_number_to_accidents_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accidents', function (Blueprint $table) {
            $table->string('estimate_number')->nullable()->after('estimate_status');
        });
    }

    public function down()
    {
        Schema::table('accidents', function (Blueprint $table) {
            $table->dropColumn('estimate_number');
        });
    }
};