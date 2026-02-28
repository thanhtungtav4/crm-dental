<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('installment_plans')) {
            Schema::create('installment_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('plan_code')->unique();
                $table->decimal('financed_amount', 12, 2);
                $table->decimal('down_payment_amount', 12, 2)->default(0);
                $table->decimal('remaining_amount', 12, 2);
                $table->unsignedSmallInteger('number_of_installments')->default(1);
                $table->decimal('installment_amount', 12, 2)->default(0);
                $table->date('start_date');
                $table->date('next_due_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['active', 'completed', 'defaulted', 'cancelled'])->default('active');
                $table->json('schedule')->nullable();
                $table->unsignedTinyInteger('dunning_level')->default(0);
                $table->timestamp('last_dunned_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique('invoice_id');
                $table->index(['status', 'next_due_date']);
            });

            return;
        }

        Schema::table('installment_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('installment_plans', 'patient_id')) {
                $table->foreignId('patient_id')->nullable()->after('invoice_id')->constrained('patients')->nullOnDelete();
            }

            if (! Schema::hasColumn('installment_plans', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('patient_id')->constrained('branches')->nullOnDelete();
            }

            if (! Schema::hasColumn('installment_plans', 'plan_code')) {
                $table->string('plan_code')->nullable()->after('branch_id');
            }

            if (! Schema::hasColumn('installment_plans', 'financed_amount')) {
                $table->decimal('financed_amount', 12, 2)->nullable()->after('plan_code');
            }

            if (! Schema::hasColumn('installment_plans', 'down_payment_amount')) {
                $table->decimal('down_payment_amount', 12, 2)->default(0)->after('financed_amount');
            }

            if (! Schema::hasColumn('installment_plans', 'next_due_date')) {
                $table->date('next_due_date')->nullable()->after('start_date');
            }

            if (! Schema::hasColumn('installment_plans', 'dunning_level')) {
                $table->unsignedTinyInteger('dunning_level')->default(0)->after('schedule');
            }

            if (! Schema::hasColumn('installment_plans', 'last_dunned_at')) {
                $table->timestamp('last_dunned_at')->nullable()->after('dunning_level');
            }
        });

        if (Schema::hasColumn('installment_plans', 'total_amount')) {
            DB::table('installment_plans')->whereNull('financed_amount')->update([
                'financed_amount' => DB::raw('total_amount'),
            ]);
        }

        DB::table('installment_plans')->whereNull('financed_amount')->update([
            'financed_amount' => DB::raw('remaining_amount'),
        ]);

        DB::table('installment_plans')->whereNull('plan_code')->orderBy('id')->each(function (object $plan): void {
            DB::table('installment_plans')->where('id', $plan->id)->update([
                'plan_code' => 'IP-' . str_pad((string) $plan->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('installment_plans')) {
            return;
        }

        Schema::table('installment_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('installment_plans', 'last_dunned_at')) {
                $table->dropColumn('last_dunned_at');
            }

            if (Schema::hasColumn('installment_plans', 'dunning_level')) {
                $table->dropColumn('dunning_level');
            }

            if (Schema::hasColumn('installment_plans', 'next_due_date')) {
                $table->dropColumn('next_due_date');
            }
        });
    }
};
