<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UtilisateurSeeder::class,
            CarSeeder::class,
            ClientsTableSeeder::class,
            MatriculeSeeder::class,
            ProductCategorySeeder::class,
        ]);
    }
}