<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UtilisateurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Temporarily disable foreign key checks to allow deletion
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Delete all records instead of truncating
        Utilisateur::query()->delete();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create SYSTEM superadmin user (hidden from management)
        Utilisateur::create([
            'Fullname' => 'Tracker Jina',
            'password' => Hash::make('eyes212!'),
            'status' => 'active',
            'role' => 'superadmin', // now using superadmin
            'is_system' => true,
        ]);

        // Create a regular superadmin (visible)
        Utilisateur::create([
            'Fullname' => 'Jina Super',
            'password' => Hash::make('alaslaoui123'),
            'status' => 'active',
            'role' => 'superadmin',
            'is_system' => false,
        ]);

        // Create regular admin user (visible in management)
        Utilisateur::create([
            'Fullname' => 'Jina Admin',
            'password' => Hash::make('admin123'),
            'status' => 'active',
            'role' => 'admin',
            'is_system' => false,
        ]);

        // Create employee user
        Utilisateur::create([
            'Fullname' => 'Employee Jina',
            'password' => Hash::make('employee123'),
            'status' => 'active',
            'role' => 'employee',
            'is_system' => false,
        ]);
    }
}