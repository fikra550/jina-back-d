<?php

namespace Database\Seeders;

use App\Models\Matricule;
use App\Models\Car;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MatriculeSeeder extends Seeder
{
    public function run(): void
    {
        Matricule::query()->delete();

        $cars = Car::all();
        $carMap = [];

        // Build lookup map: brand|model|year|transmission|fuel => car_id
        foreach ($cars as $car) {
            $key = $car->brand . '|' . $car->model . '|' . $car->year . '|' . $car->transmission . '|' . $car->fuel_type;
            $carMap[$key] = $car->id;
        }

        // Only license plates belonging to SMAITI CAR
        $plates = [
            // SMAITI CAR (first batch)
            ['plaque' => '15041-T-6', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2022, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '15042-T-6', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2022, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '35205-B-7', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2021, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '38531-B-7', 'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2022, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '38623-B-7', 'brand' => 'Dacia', 'model' => 'Stepway', 'year' => 2022, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '41628-B-7', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2022, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '42064-B-7', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2022, 'transmission' => 'manual', 'fuel' => 'diesel'],

            // SMAITI CAR (later batch)
            ['plaque' => '84186-D-8', 'brand' => 'Renault', 'model' => 'Clio 5', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '838851WW', 'brand' => 'Renault', 'model' => 'Clio 5', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '84175-D-8', 'brand' => 'Renault', 'model' => 'Clio 5', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '84174-D-8', 'brand' => 'Renault', 'model' => 'Clio 5', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '84177-D-8', 'brand' => 'Renault', 'model' => 'Clio 5', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],

            // Dacia Clio 5 (data error – kept as per Excel)
            ['plaque' => '85357-D-8', 'brand' => 'Dacia', 'model' => 'Clio 5', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],

            // Dacia Sandero 2026
            ['plaque' => '85356-D-8', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '85355-D-8', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '85354-D-8', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '85358-D-8', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '893157WW', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '85353-D-8', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '85352-D-8', 'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],

            // Renault Kardian
            ['plaque' => '915351 WW', 'brand' => 'Renault', 'model' => 'Kardian', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915352 WW', 'brand' => 'Renault', 'model' => 'Kardian', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915353 WW', 'brand' => 'Renault', 'model' => 'Kardian', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915354 WW', 'brand' => 'Renault', 'model' => 'Kardian', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],

            // Renault Express
            ['plaque' => '915355 WW', 'brand' => 'Renault', 'model' => 'Express', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915356 WW', 'brand' => 'Renault', 'model' => 'Express', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],

            // Dacia Logan 2026
            ['plaque' => '915357 WW', 'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915358 WW', 'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915359 WW', 'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915360 WW', 'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915361 WW', 'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
            ['plaque' => '915362 WW', 'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2026, 'transmission' => 'manual', 'fuel' => 'diesel'],
        ];

        $matricules = [];

        foreach ($plates as $plate) {
            $key = $plate['brand'] . '|' . $plate['model'] . '|' . $plate['year'] . '|' . $plate['transmission'] . '|' . $plate['fuel'];
            if (!isset($carMap[$key])) {
                // Skip if no matching car (should not happen with filtered data)
                continue;
            }

            $matricules[] = [
                'car_id'              => $carMap[$key],
                'matricule_code'      => $plate['plaque'],
                'kilometrage'         => 0,
                'status'              => 'active',
                'visit_tech'          => Carbon::now()->addYear(),
                'date_taxe_voiture'   => null,
                'date_assurance'      => null,
                'vidange_status'      => 'not done',
                'paquets_de_voiture'  => null,
                'paquets_de_voiture_date' => null,
                'paquets_de_voiture_count' => 0,
                'paquets_de_frein'    => null,
                'paquets_de_frein_date' => null,
                'paquets_de_frein_count' => 0,
                'filter_oil'          => null,
                'filter_oil_date'     => null,
                'filter_oil_count'    => 0,
                'filter_air'          => null,
                'filter_air_date'     => null,
                'filter_air_count'    => 0,
                'ad_blue'             => null,
                'ad_blue_date'        => null,
                'ad_blue_quantity'    => 0,
                'oil'                 => null,
                'oil_date'            => null,
                'oil_quantity'        => 0,
                'additional_maintenance' => json_encode([]),
                'periodic_km_maintenance' => json_encode([]),
                'last_vidange_km'     => 0,
                'vidange_interval'    => 10000,
                'created_at'          => Carbon::now(),
                'updated_at'          => Carbon::now(),
            ];
        }

        Matricule::insert($matricules);
        $this->command->info('Matricules seeded: ' . count($matricules));
    }
}