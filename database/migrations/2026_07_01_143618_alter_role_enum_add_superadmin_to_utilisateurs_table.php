<?php
// database/migrations/xxxx_xx_xx_xxxxxx_alter_role_enum_add_superadmin_to_utilisateurs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up() {
        // For MySQL:
        DB::statement("ALTER TABLE utilisateurs MODIFY role ENUM('admin', 'employee', 'superadmin') NOT NULL DEFAULT 'employee'");
    }

    public function down() {
        // Revert to old enum (will fail if any 'superadmin' rows exist)
        DB::statement("ALTER TABLE utilisateurs MODIFY role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee'");
    }
};