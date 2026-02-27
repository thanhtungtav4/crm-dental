<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prescriptions') && ! Schema::hasColumn('prescriptions', 'branch_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('notes') && ! Schema::hasColumn('notes', 'branch_id')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('consents') && ! Schema::hasColumn('consents', 'branch_id')) {
            Schema::table('consents', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        $this->backfillPrescriptionBranches();
        $this->backfillNoteBranches();
        $this->backfillConsentBranches();

        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'branch_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->index(['branch_id', 'treatment_date'], 'prescriptions_branch_treatment_date_index');
            });
        }

        if (Schema::hasTable('notes') && Schema::hasColumn('notes', 'branch_id')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->index(['branch_id', 'care_status', 'care_at'], 'notes_branch_care_status_care_at_index');
            });
        }

        if (Schema::hasTable('consents') && Schema::hasColumn('consents', 'branch_id')) {
            Schema::table('consents', function (Blueprint $table): void {
                $table->index(['branch_id', 'status'], 'consents_branch_status_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consents') && Schema::hasColumn('consents', 'branch_id')) {
            Schema::table('consents', function (Blueprint $table): void {
                $table->dropIndex('consents_branch_status_index');
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        if (Schema::hasTable('notes') && Schema::hasColumn('notes', 'branch_id')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->dropIndex('notes_branch_care_status_care_at_index');
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        if (Schema::hasTable('prescriptions') && Schema::hasColumn('prescriptions', 'branch_id')) {
            Schema::table('prescriptions', function (Blueprint $table): void {
                $table->dropIndex('prescriptions_branch_treatment_date_index');
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }

    protected function backfillPrescriptionBranches(): void
    {
        if (! Schema::hasTable('prescriptions') || ! Schema::hasColumn('prescriptions', 'branch_id')) {
            return;
        }

        DB::table('prescriptions')
            ->select(['id', 'patient_id', 'treatment_session_id'])
            ->whereNull('branch_id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows): void {
                $patientIds = $rows->pluck('patient_id')->filter()->unique()->values()->all();
                $sessionIds = $rows->pluck('treatment_session_id')->filter()->unique()->values()->all();

                $patientBranchMap = $patientIds === []
                    ? collect()
                    : DB::table('patients')
                        ->whereIn('id', $patientIds)
                        ->pluck('first_branch_id', 'id');

                $sessionBranchMap = $sessionIds === []
                    ? collect()
                    : DB::table('treatment_sessions')
                        ->join('treatment_plans', 'treatment_plans.id', '=', 'treatment_sessions.treatment_plan_id')
                        ->whereIn('treatment_sessions.id', $sessionIds)
                        ->pluck('treatment_plans.branch_id', 'treatment_sessions.id');

                foreach ($rows as $row) {
                    $branchId = null;

                    if ($row->treatment_session_id !== null) {
                        $branchId = $sessionBranchMap->get((int) $row->treatment_session_id);
                    }

                    if ($branchId === null && $row->patient_id !== null) {
                        $branchId = $patientBranchMap->get((int) $row->patient_id);
                    }

                    if ($branchId === null) {
                        continue;
                    }

                    DB::table('prescriptions')
                        ->where('id', (int) $row->id)
                        ->update(['branch_id' => (int) $branchId]);
                }
            });
    }

    protected function backfillNoteBranches(): void
    {
        if (! Schema::hasTable('notes') || ! Schema::hasColumn('notes', 'branch_id')) {
            return;
        }

        DB::table('notes')
            ->select(['id', 'patient_id', 'customer_id'])
            ->whereNull('branch_id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows): void {
                $patientIds = $rows->pluck('patient_id')->filter()->unique()->values()->all();
                $customerIds = $rows->pluck('customer_id')->filter()->unique()->values()->all();

                $patientBranchMap = $patientIds === []
                    ? collect()
                    : DB::table('patients')
                        ->whereIn('id', $patientIds)
                        ->pluck('first_branch_id', 'id');

                $customerBranchMap = $customerIds === []
                    ? collect()
                    : DB::table('customers')
                        ->whereIn('id', $customerIds)
                        ->pluck('branch_id', 'id');

                foreach ($rows as $row) {
                    $branchId = null;

                    if ($row->patient_id !== null) {
                        $branchId = $patientBranchMap->get((int) $row->patient_id);
                    }

                    if ($branchId === null && $row->customer_id !== null) {
                        $branchId = $customerBranchMap->get((int) $row->customer_id);
                    }

                    if ($branchId === null) {
                        continue;
                    }

                    DB::table('notes')
                        ->where('id', (int) $row->id)
                        ->update(['branch_id' => (int) $branchId]);
                }
            });
    }

    protected function backfillConsentBranches(): void
    {
        if (! Schema::hasTable('consents') || ! Schema::hasColumn('consents', 'branch_id')) {
            return;
        }

        DB::table('consents')
            ->select(['id', 'patient_id', 'plan_item_id'])
            ->whereNull('branch_id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows): void {
                $patientIds = $rows->pluck('patient_id')->filter()->unique()->values()->all();
                $planItemIds = $rows->pluck('plan_item_id')->filter()->unique()->values()->all();

                $patientBranchMap = $patientIds === []
                    ? collect()
                    : DB::table('patients')
                        ->whereIn('id', $patientIds)
                        ->pluck('first_branch_id', 'id');

                $planItemBranchMap = $planItemIds === []
                    ? collect()
                    : DB::table('plan_items')
                        ->join('treatment_plans', 'treatment_plans.id', '=', 'plan_items.treatment_plan_id')
                        ->whereIn('plan_items.id', $planItemIds)
                        ->pluck('treatment_plans.branch_id', 'plan_items.id');

                foreach ($rows as $row) {
                    $branchId = null;

                    if ($row->plan_item_id !== null) {
                        $branchId = $planItemBranchMap->get((int) $row->plan_item_id);
                    }

                    if ($branchId === null && $row->patient_id !== null) {
                        $branchId = $patientBranchMap->get((int) $row->patient_id);
                    }

                    if ($branchId === null) {
                        continue;
                    }

                    DB::table('consents')
                        ->where('id', (int) $row->id)
                        ->update(['branch_id' => (int) $branchId]);
                }
            });
    }
};
