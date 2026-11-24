<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ------------------------------------------------------------
         |  LAVEURS — table des laveurs + règle de rémunération
         |  (doit exister AVANT lavages à cause de la clé étrangère)
         ------------------------------------------------------------ */
        Schema::create('laveurs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();

            // Comment on calcule ce que gagne le laveur :
            //  - percentage : pay_rate = % du prix du lavage (ex: 30 => 30 %)
            //  - fixed      : pay_rate = montant fixe en DH par lavage terminé
            $table->enum('pay_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('pay_rate', 10, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        /* ------------------------------------------------------------
         |  LAVEUR_PAYMENTS — versements faits aux laveurs
         |  reste à payer = somme des laveur_amount (lavages terminés)
         |                  - somme des versements
         ------------------------------------------------------------ */
        Schema::create('laveur_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laveur_id')->constrained('laveurs')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->enum('method', ['cash', 'check', 'card', 'transfer'])->default('cash');
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['laveur_id', 'payment_date']);
        });

        /* ------------------------------------------------------------
         |  LAVAGES
         ------------------------------------------------------------ */
        Schema::create('lavages', function (Blueprint $table) {
            $table->id();

            // Related entities
            $table->foreignId('matricule_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('car_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('set null');
            $table->string('client_name')->nullable(); // client saisi à la main
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');

            // Matricule du véhicule saisi à la main : stocké UNIQUEMENT ici,
            // sans aucun lien avec la table "matricules".
            $table->string('vehicle_plate', 50)->nullable();

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
            $table->foreignId('laveur_id')->nullable()->constrained('laveurs')->onDelete('set null');
            $table->string('washer_name')->nullable();              // nom du laveur (copie, pour l'affichage/historique)
            $table->decimal('laveur_amount', 10, 2)->nullable();    // ce que gagne le laveur sur ce lavage
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
            $table->index('laveur_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lavages');
        Schema::dropIfExists('laveur_payments');
        Schema::dropIfExists('laveurs');
    }
};