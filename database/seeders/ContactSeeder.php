<?php

namespace Database\Seeders;

use App\Models\Contact;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        Contact::query()->delete();

        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            Contact::create([
                'fullname' => $faker->name,
                'email' => $faker->email,
                'phone' => $faker->phoneNumber,
                'message' => $faker->paragraph(2),
                'created_at' => $faker->dateTimeBetween('-90 days', 'now'),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}