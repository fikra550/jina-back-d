<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\Car;
use App\Models\Client;
use App\Models\Matricule;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        Reservation::query()->delete();

        $cars = Car::all();
        $clients = Client::all();
        $matricules = Matricule::all();
        $faker = Faker::create();

        $statuses = ['pending', 'confirmed', 'cancelled', 'contacted', 'completed', 'retard'];
        $paymentMethods = ['cash', 'bank_transfer', 'check', 'card'];

        for ($i = 0; $i < 100; $i++) {
            $car = $cars->random();
            $client = $clients->random();
            $matricule = $matricules->random();
            $status = $faker->randomElement($statuses);
            
            // Convert to Carbon instance
            $startDate = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));
            $rentalDays = $faker->numberBetween(1, 14);
            $endDate = (clone $startDate)->addDays($rentalDays);
            
            $totalPrice = $car->price_per_day * $rentalDays;
            $amountPaid = $faker->randomFloat(2, 0, $totalPrice);

            $paymentHistory = [];
            if ($amountPaid > 0) {
                $paymentHistory[] = [
                    'id' => uniqid(),
                    'amount' => $amountPaid,
                    'date' => $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
                    'method' => $faker->randomElement($paymentMethods),
                    'notes' => 'Paiement initial',
                    'created_at' => Carbon::now()->toDateTimeString()
                ];
            }

            Reservation::create([
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_days' => $rentalDays,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'rental_days' => $rentalDays,
                'total_price' => $totalPrice,
                'amount_paid' => $amountPaid,
                'remaining_amount' => $totalPrice - $amountPaid,
                'payment_history' => json_encode($paymentHistory),
                'status' => $status,
                'matricule_id' => $matricule->id,
                'car_id' => $car->id,
                'client_id' => $client->id,
                'has_second_driver' => $faker->boolean,
                'second_driver_client_id' => $faker->boolean ? $clients->random()->id : null,
                'notes' => $faker->sentence,
                'reception_notes' => $faker->optional()->sentence,
                'kilometrage_sortie' => $matricule->kilometrage + $faker->numberBetween(0, 5000),
                'kilometrage_entree' => $matricule->kilometrage + $faker->numberBetween(0, 5000),
                'matricule_kilometrage_at_start' => $matricule->kilometrage,
                'matricule_kilometrage_at_end' => $matricule->kilometrage + $faker->numberBetween(0, 5000),
                'created_by' => 'System',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}