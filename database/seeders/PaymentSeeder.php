<?php

namespace Database\Seeders;

use App\Models\CarFinancing;
use App\Models\CarFinancingPayment;
use App\Models\Matricule;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Faker\Factory as Faker;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        CarFinancing::query()->delete();
        CarFinancingPayment::query()->delete();

        $matricules = Matricule::all();
        $faker = Faker::create();

        $creditTypes = ['vehicule_entreprise', 'vehicule_personnel', 'other'];
        $statuses = ['active', 'completed', 'late', 'defaulted'];
        $periodicities = ['mensuel', 'trimestriel', 'semestriel', 'annuel'];

        for ($i = 0; $i < 100; $i++) {
            $matricule = $matricules->random();
            $creditAmount = $faker->numberBetween(50000, 300000);
            $durationMonths = $faker->numberBetween(12, 60);
            $installmentAmount = round($creditAmount / $durationMonths, 2);
            $status = $faker->randomElement($statuses);
            
            // Convert to Carbon instance
            $firstDate = Carbon::instance($faker->dateTimeBetween('-24 months', 'now'));
            $lastDate = (clone $firstDate)->addMonths($durationMonths);

            $financing = CarFinancing::create([
                'matricule_id' => $matricule->id,
                'dossier_number' => 'DOS-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'account_number' => 'CF' . $faker->unique()->numberBetween(100000, 999999),
                'credit_type' => $faker->randomElement($creditTypes),
                'contract_date' => $firstDate->toDateString(),
                'credit_amount' => $creditAmount,
                'preti_interet_ttc_differe' => $faker->randomFloat(2, 0, 10000),
                'duration_months' => $durationMonths,
                'differed_months' => $faker->numberBetween(0, 6),
                'periodicity' => $faker->randomElement($periodicities),
                'total_installments' => $durationMonths,
                'first_installment_date' => $firstDate->toDateString(),
                'last_installment_date' => $lastDate->toDateString(),
                'bank_name' => 'Banque ' . $faker->numberBetween(1, 10),
                'bank_account' => 'BE' . $faker->numberBetween(100000, 999999),
                'current_installment_number' => $faker->numberBetween(0, $durationMonths),
                'total_paid' => $faker->randomFloat(2, 0, $creditAmount),
                'total_remaining' => $creditAmount - $faker->randomFloat(2, 0, $creditAmount),
                'status' => $status,
                'installment_amount' => $installmentAmount,
                'prestation_amount' => $faker->randomFloat(2, 0, 500),
                'notes' => $faker->sentence,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Generate payments
            $remainingCapital = $creditAmount;
            $totalInterest = 0;
            $monthlyRate = 0.02;

            for ($j = 1; $j <= $durationMonths; $j++) {
                $interest = $remainingCapital * $monthlyRate;
                $capital = $installmentAmount - $interest;
                if ($j == $durationMonths) {
                    $capital = $remainingCapital;
                    $interest = $installmentAmount - $capital;
                }
                $remainingCapital -= $capital;
                $totalInterest += $interest;

                $paymentStatus = 'pending';
                if ($j <= $financing->current_installment_number) {
                    $paymentStatus = 'paid';
                } elseif ($j > $financing->current_installment_number && $status === 'late') {
                    $paymentStatus = 'overdue';
                }

                $dueDate = (clone $firstDate)->addMonths($j);

                CarFinancingPayment::create([
                    'car_financing_id' => $financing->id,
                    'due_date' => $dueDate->toDateString(),
                    'installment_number' => $j,
                    'capital_amount' => round($capital, 2),
                    'interest_amount' => round($interest, 2),
                    'tva_amount' => round($interest * 0.20, 2),
                    'prestation_amount' => $faker->randomFloat(2, 0, 500),
                    'total_amount' => round($installmentAmount, 2),
                    'status' => $paymentStatus,
                    'paid_date' => $paymentStatus === 'paid' ? (clone $dueDate)->subDays($faker->numberBetween(0, 5))->toDateString() : null,
                    'paid_amount' => $paymentStatus === 'paid' ? round($installmentAmount, 2) : 0,
                    'remaining_capital' => round($remainingCapital, 2),
                    'remaining_interest' => round($totalInterest, 2),
                    'remaining_total' => round($remainingCapital + $totalInterest, 2),
                    'payment_method' => $paymentStatus === 'paid' ? $faker->randomElement(['cash', 'bank_transfer', 'check', 'card']) : null,
                    'transaction_reference' => $paymentStatus === 'paid' ? 'TRX' . $faker->numberBetween(100000, 999999) : null,
                    'payment_notes' => $paymentStatus === 'paid' ? 'Paiement régulier' : null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }
}