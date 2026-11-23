<?php

namespace Database\Seeders;

use App\Models\Car;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    public function run(): void
    {
        Car::query()->delete();

        $cars = [
            // Dacia Sandero (SMAITI CAR)
            ['brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2021, 'color' => 'Blanc', 'price_per_day' => 250, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],
            ['brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2022, 'color' => 'Blanc', 'price_per_day' => 250, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],
            ['brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'color' => 'Blanc', 'price_per_day' => 290, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],

            // Dacia Logan
            ['brand' => 'Dacia', 'model' => 'Logan', 'year' => 2022, 'color' => 'Blanc', 'price_per_day' => 240, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],
            ['brand' => 'Dacia', 'model' => 'Logan', 'year' => 2026, 'color' => 'Blanc', 'price_per_day' => 280, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],

            // Dacia Stepway
            ['brand' => 'Dacia', 'model' => 'Stepway', 'year' => 2022, 'color' => 'Blanc', 'price_per_day' => 270, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],

            // Renault Clio 5
            ['brand' => 'Renault', 'model' => 'Clio 5', 'year' => 2026, 'color' => 'Blanc', 'price_per_day' => 320, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],

            // Dacia Clio 5 (data error – kept as per Excel)
            ['brand' => 'Dacia', 'model' => 'Clio 5', 'year' => 2026, 'color' => 'Blanc', 'price_per_day' => 320, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],

            // Renault Kardian
            ['brand' => 'Renault', 'model' => 'Kardian', 'year' => 2026, 'color' => 'Blanc', 'price_per_day' => 360, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],

            // Renault Express
            ['brand' => 'Renault', 'model' => 'Express', 'year' => 2026, 'color' => 'Blanc', 'price_per_day' => 280, 'seats' => 5, 'doors' => 4, 'fuel_type' => 'diesel', 'transmission' => 'manual'],
        ];

        foreach ($cars as $car) {
            Car::create($car);
        }
    }
}