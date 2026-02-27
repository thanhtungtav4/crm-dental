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
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'branch_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'branch_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('invoice_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'branch_id')) {
            DB::table('invoices')
                ->select('id', 'treatment_plan_id', 'patient_id')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->chunkById(500, function (Collection $rows): void {
                    $planIds = $rows->pluck('treatment_plan_id')->filter()->unique()->values()->all();
                    $patientIds = $rows->pluck('patient_id')->filter()->unique()->values()->all();

                    $planBranchMap = collect();
                    if ($planIds !== []) {
                        $planBranchMap = DB::table('treatment_plans')
                            ->whereIn('id', $planIds)
                            ->pluck('branch_id', 'id');
                    }

                    $patientBranchMap = collect();
                    if ($patientIds !== []) {
                        $patientBranchMap = DB::table('patients')
                            ->whereIn('id', $patientIds)
                            ->pluck('first_branch_id', 'id');
                    }

                    foreach ($rows as $row) {
                        $branchId = null;

                        if ($row->treatment_plan_id !== null) {
                            $branchId = $planBranchMap->get((int) $row->treatment_plan_id);
                        }

                        if ($branchId === null && $row->patient_id !== null) {
                            $branchId = $patientBranchMap->get((int) $row->patient_id);
                        }

                        if ($branchId === null) {
                            continue;
                        }

                        DB::table('invoices')
                            ->where('id', (int) $row->id)
                            ->update(['branch_id' => (int) $branchId]);
                    }
                });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'branch_id')) {
            DB::table('payments')
                ->select('id', 'invoice_id')
                ->whereNull('branch_id')
                ->orderBy('id')
                ->chunkById(500, function (Collection $rows): void {
                    $invoiceIds = $rows->pluck('invoice_id')->filter()->unique()->values()->all();

                    if ($invoiceIds === []) {
                        return;
                    }

                    $invoiceBranchMap = DB::table('invoices')
                        ->whereIn('id', $invoiceIds)
                        ->pluck('branch_id', 'id');

                    foreach ($rows as $row) {
                        $branchId = $invoiceBranchMap->get((int) $row->invoice_id);

                        if ($branchId === null) {
                            continue;
                        }

                        DB::table('payments')
                            ->where('id', (int) $row->id)
                            ->update(['branch_id' => (int) $branchId]);
                    }
                });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->index(['branch_id', 'status', 'issued_at'], 'invoices_branch_status_issued_at_index');
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->index(['branch_id', 'direction', 'paid_at'], 'payments_branch_direction_paid_at_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'branch_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropIndex('payments_branch_direction_paid_at_index');
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'branch_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoices_branch_status_issued_at_index');
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
