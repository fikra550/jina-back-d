<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Huile',      'slug' => 'oil',       'color' => '#f59e0b', 'icon' => 'Droplet',  'sort_order' => 1],
            ['name' => 'AdBlue',     'slug' => 'ad_blue',   'color' => '#3b82f6', 'icon' => 'Droplets', 'sort_order' => 2],
            ['name' => 'Filtre',     'slug' => 'filter',    'color' => '#8b5cf6', 'icon' => 'Filter',   'sort_order' => 3],
            ['name' => 'Accessoire', 'slug' => 'accessory', 'color' => '#10b981', 'icon' => 'Wrench',   'sort_order' => 4],
            ['name' => 'Autre',      'slug' => 'other',     'color' => '#64748b', 'icon' => 'Package',  'sort_order' => 5],
        ];

        foreach ($rows as $row) {
            DB::table('product_categories')->updateOrInsert(
                ['slug' => $row['slug']],
                array_merge($row, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}