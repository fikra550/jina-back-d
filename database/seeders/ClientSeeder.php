<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::query()->delete();

        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;

            Client::create([
                'nom' => $lastName,
                'prenom' => $firstName,
                'telephone' => $faker->phoneNumber,
                'email' => $faker->email,
                'city' => $faker->city,
                'cin_number' => 'CIN' . $faker->unique()->numberBetween(100000, 999999),
                'driver_license_number' => 'DL' . $faker->unique()->numberBetween(100000, 999999),
                'cin_image' => null,
                'driver_license_image' => null,
                'date_naissance' => $faker->dateTimeBetween('-60 years', '-20 years'),
                'lieu_naissance' => $faker->city,
                'cin_delivre_le' => $faker->dateTimeBetween('-10 years', '-1 year'),
                'permis_delivre_le' => $faker->dateTimeBetween('-10 years', '-1 year'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}