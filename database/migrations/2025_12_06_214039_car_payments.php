<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('car_financings', function (Blueprint $table) {
            $table->id();
            
            // Reference to the car via matricule
            $table->foreignId('matricule_id')->constrained()->onDelete('cascade');
            
            // Basic financing information
            $table->string('dossier_number')->unique(); // V986339 from your document
            $table->string('account_number')->nullable(); // CF000130700
            $table->enum('credit_type', ['vehicule_entreprise', 'vehicule_personnel', 'other'])->default('vehicule_entreprise');
            
            // Contract details
            $table->date('contract_date');
            $table->decimal('credit_amount', 15, 2); // 177.000,00
            $table->decimal('preti_interet_ttc_differe', 15, 2)->nullable(); // Additional interest amount if any
            $table->integer('duration_months'); // 36 months
            $table->integer('differed_months')->default(0); // 0 months
            $table->string('periodicity')->default('mensuel'); // mensuel, trimestriel, etc.
            $table->integer('total_installments'); // 36
            $table->date('first_installment_date'); // 28/04/2025
            $table->date('last_installment_date'); // 28/03/2028
            
            // Bank/domiciliation information
            $table->string('bank_name')->nullable(); // AWB
            $table->string('bank_account')->nullable();
            
            // Current status
            $table->integer('current_installment_number')->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('total_remaining', 15, 2);
            $table->enum('status', ['active', 'completed', 'late', 'defaulted'])->default('active');
            
            // Payment details (fixed per installment)
            $table->decimal('installment_amount', 15, 2); // 6.053,44
            $table->decimal('prestation_amount', 15, 2)->nullable(); // 189,29 (if separate from installment)
            
            // Optional: Store the entire amortization schedule as JSON
            $table->json('amortization_schedule')->nullable();
            
            // Additional notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('dossier_number');
            $table->index('contract_date');
            $table->index('status');
        });

        // Create a separate table for tracking each payment/installment
        Schema::create('car_financing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_financing_id')->constrained()->onDelete('cascade');
            
            // Installment details
            $table->date('due_date'); // 28/04/2025, 28/05/2025, etc.
            $table->integer('installment_number'); // 1, 2, 3...
            
            // Payment breakdown
            $table->decimal('capital_amount', 15, 2); // 4.119,96
            $table->decimal('interest_amount', 15, 2); // 1.585,63
            $table->decimal('tva_amount', 15, 2)->nullable(); // 158,56
            $table->decimal('prestation_amount', 15, 2)->nullable(); // 189,29
            $table->decimal('total_amount', 15, 2); // 6.053,44
            
            // Payment status
            $table->enum('status', ['pending', 'paid', 'overdue', 'partial'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            
            // Remaining balances after this installment
            $table->decimal('remaining_capital', 15, 2); // 172.860,04
            $table->decimal('remaining_interest', 15, 2)->nullable(); // 32.365,19
            $table->decimal('remaining_total', 15, 2); // 205.245,23
            
            // Payment method details
            $table->string('payment_method')->nullable(); // bank_transfer, check, cash, etc.
            $table->string('transaction_reference')->nullable();
            $table->text('payment_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['car_financing_id', 'installment_number']);
            $table->index('due_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('car_financing_payments');
        Schema::dropIfExists('car_financings');
    }
};