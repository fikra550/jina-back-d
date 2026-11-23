<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        Report::query()->delete();

        $users = Utilisateur::where('is_system', false)->get();
        if ($users->isEmpty()) {
            $users = Utilisateur::all();
        }

        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $user = $users->random();
            $rowCount = $faker->numberBetween(3, 10);
            $rows = [];
            $totalHT = 0;

            for ($j = 0; $j < $rowCount; $j++) {
                $price = $faker->randomFloat(2, 100, 5000);
                $rows[] = [
                    'date' => $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
                    'nom' => $faker->name,
                    'price' => $price
                ];
                $totalHT += $price;
            }

            Report::create([
                'user_id' => $user->id,
                'file_name' => 'Rapport_' . $faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d') . '.pdf',
                'file_path' => 'reports/' . $user->id . '/Rapport_' . $i . '.pdf',
                'rows' => json_encode($rows),
                'total_ht' => $totalHT,
                'created_at' => $faker->dateTimeBetween('-90 days', 'now'),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}   