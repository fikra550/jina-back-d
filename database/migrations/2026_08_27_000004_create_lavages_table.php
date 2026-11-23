<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lavages', function (Blueprint $table) {
            $table->id();

            // Related entities
            $table->foreignId('matricule_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('car_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('set null');
            $table->string('client_name')->nullable(); // client saisi à la main
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');

            // Scheduling
            $table->date('date_lavage');
            $table->time('time_lavage')->nullable();

            // Workflow timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_minutes')->nullable();

            // Type & services breakdown
            $table->enum('type', ['externe', 'interne', 'complet']);
            $table->json('services')->nullable();

            // Pricing
            $table->decimal('price', 10, 2);
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('tva_amount', 10, 2)->nullable();

            // Status & payments
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled']);
            $table->enum('payment_status', ['paid', 'unpaid', 'partial'])->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->json('payment_history')->nullable();

            // Photos
            $table->json('photos_before')->nullable();
            $table->json('photos_after')->nullable();

            // Worker / bay / condition
            $table->string('washer_name')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('utilisateurs')->onDelete('set null');
            $table->string('bay')->nullable();
            $table->text('vehicle_condition_before')->nullable();
            $table->text('vehicle_condition_after')->nullable();

            // Client feedback
            $table->tinyInteger('rating')->nullable();
            $table->text('feedback')->nullable();

            // Misc
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();

            $table->timestamps();

            $table->index('date_lavage');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lavages');
    }
};