<?php
// database/migrations/2026_06_16_000002_update_accidents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accidents', function (Blueprint $table) {
            // Accident identification
            if (!Schema::hasColumn('accidents', 'accident_number')) {
                $table->string('accident_number')->unique()->after('id');
            }
            
            // Reservation link
            if (!Schema::hasColumn('accidents', 'reservation_id')) {
                $table->foreignId('reservation_id')->nullable()->after('accident_number')->constrained()->onDelete('set null');
            }
            
            // Accident details
            if (!Schema::hasColumn('accidents', 'time_accident')) {
                $table->time('time_accident')->nullable()->after('date_accident');
            }
            
            if (!Schema::hasColumn('accidents', 'location')) {
                $table->string('location')->nullable()->after('time_accident');
            }
            
            if (!Schema::hasColumn('accidents', 'description')) {
                $table->text('description')->nullable()->after('location');
            }
            
            if (!Schema::hasColumn('accidents', 'police_report_number')) {
                $table->string('police_report_number')->nullable()->after('description');
            }
            
            // Garage inspection
            if (!Schema::hasColumn('accidents', 'garage_id')) {
                $table->foreignId('garage_id')->nullable()->after('police_report_number')->constrained()->onDelete('set null');
            }
            
            if (!Schema::hasColumn('accidents', 'inspection_notes')) {
                $table->text('inspection_notes')->nullable()->after('garage_id');
            }
            
            // Costs
            if (!Schema::hasColumn('accidents', 'estimated_cost')) {
                $table->decimal('estimated_cost', 10, 2)->nullable()->after('expert_notes');
            }
            
            if (!Schema::hasColumn('accidents', 'total_repair_cost')) {
                $table->decimal('total_repair_cost', 10, 2)->nullable()->after('estimated_cost');
            }
            
            if (!Schema::hasColumn('accidents', 'franchise_amount')) {
                $table->decimal('franchise_amount', 10, 2)->nullable()->after('total_repair_cost');
            }
            
            // Payments
            if (!Schema::hasColumn('accidents', 'insurance_paid')) {
                $table->decimal('insurance_paid', 10, 2)->default(0)->after('franchise_amount');
            }
            
            if (!Schema::hasColumn('accidents', 'client_paid')) {
                $table->decimal('client_paid', 10, 2)->default(0)->after('insurance_paid');
            }
            
            if (!Schema::hasColumn('accidents', 'total_paid')) {
                $table->decimal('total_paid', 10, 2)->default(0)->after('client_paid');
            }
            
            if (!Schema::hasColumn('accidents', 'remaining_amount')) {
                $table->decimal('remaining_amount', 10, 2)->default(0)->after('total_paid');
            }
            
            // Internal notes and closure
            if (!Schema::hasColumn('accidents', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('notes');
            }
            
            if (!Schema::hasColumn('accidents', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('internal_notes');
            }
            
            // Estimate data
            if (!Schema::hasColumn('accidents', 'estimate_items')) {
                $table->json('estimate_items')->nullable()->after('closed_at');
            }
            
            if (!Schema::hasColumn('accidents', 'estimate_total_ht')) {
                $table->decimal('estimate_total_ht', 10, 2)->nullable()->after('estimate_items');
            }
            
            if (!Schema::hasColumn('accidents', 'estimate_tva')) {
                $table->decimal('estimate_tva', 10, 2)->nullable()->after('estimate_total_ht');
            }
            
            if (!Schema::hasColumn('accidents', 'estimate_total_ttc')) {
                $table->decimal('estimate_total_ttc', 10, 2)->nullable()->after('estimate_tva');
            }
            
            if (!Schema::hasColumn('accidents', 'estimate_status')) {
                $table->enum('estimate_status', ['draft', 'sent', 'approved', 'rejected'])->default('draft')->after('estimate_total_ttc');
            }
            
            // Repair tracking
            if (!Schema::hasColumn('accidents', 'repair_start_date')) {
                $table->date('repair_start_date')->nullable()->after('estimate_status');
            }
            
            if (!Schema::hasColumn('accidents', 'repair_end_date')) {
                $table->date('repair_end_date')->nullable()->after('repair_start_date');
            }
            
            if (!Schema::hasColumn('accidents', 'repair_notes')) {
                $table->text('repair_notes')->nullable()->after('repair_end_date');
            }
            
            // Invoice data
            if (!Schema::hasColumn('accidents', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('repair_notes');
            }
            
            if (!Schema::hasColumn('accidents', 'invoice_items')) {
                $table->json('invoice_items')->nullable()->after('invoice_number');
            }
            
            if (!Schema::hasColumn('accidents', 'invoice_total_ht')) {
                $table->decimal('invoice_total_ht', 10, 2)->nullable()->after('invoice_items');
            }
            
            if (!Schema::hasColumn('accidents', 'invoice_tva')) {
                $table->decimal('invoice_tva', 10, 2)->nullable()->after('invoice_total_ht');
            }
            
            if (!Schema::hasColumn('accidents', 'invoice_total_ttc')) {
                $table->decimal('invoice_total_ttc', 10, 2)->nullable()->after('invoice_tva');
            }
            
            // Payments array
            if (!Schema::hasColumn('accidents', 'payments')) {
                $table->json('payments')->nullable()->after('invoice_total_ttc');
            }
        });

        // ---- MISE À JOUR DE LA COLONNE 'status' POUR LE NOUVEAU WORKFLOW ----
        if (Schema::hasColumn('accidents', 'status')) {
            // 1. Ajouter une colonne temporaire avec le nouvel ENUM
            Schema::table('accidents', function (Blueprint $table) {
                $table->enum('status_new', [
                    'open',
                    'under_review',
                    'waiting_estimate',
                    'estimate_approved',
                    'under_repair',
                    'invoice_received',
                    'waiting_payment',
                    'closed'
                ])->default('open')->nullable()->after('status');
            });

            // 2. Mapper les anciennes valeurs vers les nouvelles
            $mapping = [
                'pending' => 'open',
                'evaluation_owner' => 'under_review',
                'contact expert' => 'waiting_estimate',
                'evaluation_expert' => 'estimate_approved',
                'fixed' => 'under_repair',
                'waiting' => 'waiting_payment',
                'completed' => 'closed'
            ];

            foreach ($mapping as $old => $new) {
                DB::table('accidents')
                    ->where('status', $old)
                    ->update(['status_new' => $new]);
            }

            // Pour les valeurs non mappées, on met 'open'
            DB::table('accidents')
                ->whereNull('status_new')
                ->update(['status_new' => 'open']);

            // 3. Supprimer l'ancienne colonne et renommer la nouvelle (via ALTER TABLE direct)
            DB::statement("ALTER TABLE accidents DROP COLUMN status");
            DB::statement("ALTER TABLE accidents CHANGE status_new status ENUM('open','under_review','waiting_estimate','estimate_approved','under_repair','invoice_received','waiting_payment','closed') NOT NULL DEFAULT 'open'");
        }
    }

    public function down(): void
    {
        // Supprimer les colonnes ajoutées
        Schema::table('accidents', function (Blueprint $table) {
            $columns = [
                'accident_number', 'reservation_id', 'time_accident', 'location',
                'description', 'police_report_number', 'garage_id', 'inspection_notes',
                'estimated_cost', 'total_repair_cost', 'franchise_amount',
                'insurance_paid', 'client_paid', 'total_paid', 'remaining_amount',
                'internal_notes', 'closed_at', 'estimate_items', 'estimate_total_ht',
                'estimate_tva', 'estimate_total_ttc', 'estimate_status',
                'repair_start_date', 'repair_end_date', 'repair_notes',
                'invoice_number', 'invoice_items', 'invoice_total_ht',
                'invoice_tva', 'invoice_total_ttc', 'payments'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('accidents', $column)) {
                    if (in_array($column, ['reservation_id', 'garage_id'])) {
                        $table->dropForeign([$column]);
                    }
                    $table->dropColumn($column);
                }
            }
        });

        // Restaurer l'ancien ENUM (si besoin, mais en général on ne rollback pas les migrations de structure)
        if (Schema::hasColumn('accidents', 'status')) {
            DB::statement("ALTER TABLE accidents MODIFY status ENUM('pending','evaluation_owner','contact expert','evaluation_expert','fixed','waiting','completed') NOT NULL DEFAULT 'pending'");
        }
    }
};