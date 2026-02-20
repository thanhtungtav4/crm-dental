<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'birthday')) {
                $table->date('birthday')->nullable()->after('email');
            }

            if (!Schema::hasColumn('customers', 'gender')) {
                $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('birthday');
            }

            if (!Schema::hasColumn('customers', 'customer_group_id')) {
                $table->foreignId('customer_group_id')
                    ->nullable()
                    ->after('source')
                    ->constrained('customer_groups')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('customers', 'promotion_group_id')) {
                $table->foreignId('promotion_group_id')
                    ->nullable()
                    ->after('customer_group_id')
                    ->constrained('promotion_groups')
                    ->nullOnDelete();
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'phone_secondary')) {
                $table->string('phone_secondary', 20)->nullable()->after('phone');
            }

            if (!Schema::hasColumn('patients', 'cccd')) {
                $table->string('cccd', 20)->nullable()->after('birthday');
            }

            if (!Schema::hasColumn('patients', 'occupation')) {
                $table->string('occupation')->nullable()->after('email');
            }

            if (!Schema::hasColumn('patients', 'customer_group_id')) {
                $table->foreignId('customer_group_id')
                    ->nullable()
                    ->after('occupation')
                    ->constrained('customer_groups')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('patients', 'promotion_group_id')) {
                $table->foreignId('promotion_group_id')
                    ->nullable()
                    ->after('customer_group_id')
                    ->constrained('promotion_groups')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('patients', 'primary_doctor_id')) {
                $table->foreignId('primary_doctor_id')
                    ->nullable()
                    ->after('promotion_group_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('patients', 'owner_staff_id')) {
                $table->foreignId('owner_staff_id')
                    ->nullable()
                    ->after('primary_doctor_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('patients', 'first_visit_reason')) {
                $table->text('first_visit_reason')->nullable()->after('address');
            }

            if (!Schema::hasColumn('patients', 'note')) {
                $table->text('note')->nullable()->after('first_visit_reason');
            }

            if (!Schema::hasColumn('patients', 'status')) {
                $table->enum('status', ['active', 'inactive', 'blocked'])
                    ->default('active')
                    ->after('note');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'appointment_kind')) {
                $table->enum('appointment_kind', ['booking', 're_exam'])
                    ->default('booking')
                    ->after('appointment_type');
            }

            if (!Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('internal_notes');
            }

            if (!Schema::hasColumn('appointments', 'reschedule_reason')) {
                $table->text('reschedule_reason')->nullable()->after('cancellation_reason');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'direction')) {
                $table->enum('direction', ['receipt', 'refund'])
                    ->default('receipt')
                    ->after('amount');
            }

            if (!Schema::hasColumn('payments', 'refund_reason')) {
                $table->text('refund_reason')->nullable()->after('note');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'invoice_exported')) {
                $table->boolean('invoice_exported')->default(false)->after('status');
            }

            if (!Schema::hasColumn('invoices', 'exported_at')) {
                $table->timestamp('exported_at')->nullable()->after('invoice_exported');
            }
        });

        Schema::table('patient_medical_records', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_medical_records', 'emergency_contact_email')) {
                $table->string('emergency_contact_email')->nullable()->after('emergency_contact_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_medical_records', function (Blueprint $table) {
            if (Schema::hasColumn('patient_medical_records', 'emergency_contact_email')) {
                $table->dropColumn('emergency_contact_email');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'exported_at')) {
                $table->dropColumn('exported_at');
            }

            if (Schema::hasColumn('invoices', 'invoice_exported')) {
                $table->dropColumn('invoice_exported');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'refund_reason')) {
                $table->dropColumn('refund_reason');
            }

            if (Schema::hasColumn('payments', 'direction')) {
                $table->dropColumn('direction');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'reschedule_reason')) {
                $table->dropColumn('reschedule_reason');
            }

            if (Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }

            if (Schema::hasColumn('appointments', 'appointment_kind')) {
                $table->dropColumn('appointment_kind');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('patients', 'note')) {
                $table->dropColumn('note');
            }

            if (Schema::hasColumn('patients', 'first_visit_reason')) {
                $table->dropColumn('first_visit_reason');
            }

            if (Schema::hasColumn('patients', 'owner_staff_id')) {
                $table->dropConstrainedForeignId('owner_staff_id');
            }

            if (Schema::hasColumn('patients', 'primary_doctor_id')) {
                $table->dropConstrainedForeignId('primary_doctor_id');
            }

            if (Schema::hasColumn('patients', 'promotion_group_id')) {
                $table->dropConstrainedForeignId('promotion_group_id');
            }

            if (Schema::hasColumn('patients', 'customer_group_id')) {
                $table->dropConstrainedForeignId('customer_group_id');
            }

            if (Schema::hasColumn('patients', 'occupation')) {
                $table->dropColumn('occupation');
            }

            if (Schema::hasColumn('patients', 'cccd')) {
                $table->dropColumn('cccd');
            }

            if (Schema::hasColumn('patients', 'phone_secondary')) {
                $table->dropColumn('phone_secondary');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'promotion_group_id')) {
                $table->dropConstrainedForeignId('promotion_group_id');
            }

            if (Schema::hasColumn('customers', 'customer_group_id')) {
                $table->dropConstrainedForeignId('customer_group_id');
            }

            if (Schema::hasColumn('customers', 'gender')) {
                $table->dropColumn('gender');
            }

            if (Schema::hasColumn('customers', 'birthday')) {
                $table->dropColumn('birthday');
            }
        });
    }
};
