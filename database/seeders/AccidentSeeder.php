<?php

namespace Database\Seeders;

use App\Models\Accident;
use App\Models\Car;
use App\Models\Client;
use App\Models\Matricule;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Faker\Factory as Faker;

class AccidentSeeder extends Seeder
{
    public function run(): void
    {
        Accident::query()->delete();

        $cars = Car::all();
        $clients = Client::all();
        $matricules = Matricule::all();
        $faker = Faker::create();

        $statuses = ['open', 'under_review', 'waiting_estimate', 'estimate_approved', 'under_repair', 'invoice_received', 'waiting_payment', 'closed'];
        $accidentTypes = ['grave', 'non_grave'];
        $procedureTypes = ['classic', 'forphie'];
        $expertDecisions = ['pending', 'accepted', 'rejected'];
        $estimateStatuses = ['draft', 'sent', 'approved', 'rejected'];

        for ($i = 0; $i < 100; $i++) {
            $car = $cars->random();
            $client = $clients->random();
            $matricule = $matricules->random();
            $status = $faker->randomElement($statuses);
            $totalRepairCost = $faker->randomFloat(2, 1000, 50000);
            $totalPaid = $faker->randomFloat(2, 0, $totalRepairCost);
            $remainingAmount = max(0, $totalRepairCost - $totalPaid);

            Accident::create([
                'date_accident' => $faker->dateTimeBetween('-180 days', 'now')->format('Y-m-d'),
                'amount_of_losses' => $faker->randomFloat(2, 1000, 50000),
                'amount_assurance' => $faker->randomFloat(2, 1000, 50000),
                'nom_expert' => 'Expert ' . $faker->numberBetween(1, 20),
                'img_accident' => [],
                'img_evaluation_expert' => [],
                'img_fixed' => [],
                'image_facture' => [],
                'status' => $status,
                'accident_type' => $faker->randomElement($accidentTypes),
                'procedure_type' => $faker->randomElement($procedureTypes),
                'expert_decision' => $faker->randomElement($expertDecisions),
                'expert_amount' => $faker->randomFloat(2, 1000, 30000),
                'expert_notes' => $faker->sentence,
                'notes' => $faker->sentence,
                'matricule_id' => $matricule->id,
                'car_id' => $car->id,
                'client_id' => $client->id,
                'garage_id' => null,
                'inspection_notes' => $faker->sentence,
                'estimated_cost' => $faker->randomFloat(2, 1000, 30000),
                'total_repair_cost' => $totalRepairCost,
                'franchise_amount' => $faker->randomFloat(2, 0, 5000),
                'insurance_paid' => $faker->randomFloat(2, 0, $totalRepairCost / 2),
                'client_paid' => $faker->randomFloat(2, 0, $totalRepairCost / 2),
                'total_paid' => $totalPaid,
                'remaining_amount' => $remainingAmount,
                'internal_notes' => $faker->sentence,
                'closed_at' => $status === 'closed' ? $faker->dateTimeBetween('-30 days', 'now') : null,
                'estimate_items' => [
                    ['name' => $faker->word, 'quantity' => $faker->numberBetween(1, 5), 'unit_price' => $faker->randomFloat(2, 50, 500)],
                    ['name' => $faker->word, 'quantity' => $faker->numberBetween(1, 3), 'unit_price' => $faker->randomFloat(2, 100, 800)],
                ],
                'estimate_total_ht' => $faker->randomFloat(2, 1000, 30000),
                'estimate_tva' => $faker->randomFloat(2, 100, 5000),
                'estimate_total_ttc' => $faker->randomFloat(2, 1000, 35000),
                'estimate_status' => $faker->randomElement($estimateStatuses),
                'repair_start_date' => $faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
                'repair_end_date' => $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
                'repair_notes' => $faker->sentence,
                'invoice_number' => 'INV-' . $faker->unique()->numberBetween(1, 999999),
                'invoice_items' => [
                    ['name' => $faker->word, 'quantity' => 1, 'unit_price' => $faker->randomFloat(2, 100, 1000)],
                ],
                'invoice_total_ht' => $faker->randomFloat(2, 1000, 30000),
                'invoice_tva' => $faker->randomFloat(2, 100, 5000),
                'invoice_total_ttc' => $faker->randomFloat(2, 1000, 35000),
                'payments' => [
                    [
                        'id' => 'pay_' . uniqid(),
                        'amount' => $faker->randomFloat(2, 100, 10000),
                        'payer' => $faker->randomElement(['client', 'insurance']),
                        'date' => $faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
                        'method' => $faker->randomElement(['cash', 'check', 'bank_transfer']),
                        'reference' => 'REF-' . $faker->numberBetween(1000, 9999),
                        'notes' => $faker->sentence,
                    ]
                ],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}