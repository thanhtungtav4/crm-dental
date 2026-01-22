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
        // Add columns to appointments
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('doctor_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('appointments', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('note');
            }
            if (!Schema::hasColumn('appointments', 'reminder_hours')) {
                $table->integer('reminder_hours')->default(24)->after('date');
            }
        });
        
        // Add discount and tax to invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'issued_at')) {
                $table->dateTime('issued_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->dateTime('due_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('invoices', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('status');
            }
        });
        
        // Add priority to treatment_plans
        Schema::table('treatment_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('treatment_plans', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->after('status');
            }
            if (!Schema::hasColumn('treatment_plans', 'expected_start_date')) {
                $table->date('expected_start_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('treatment_plans', 'expected_end_date')) {
                $table->date('expected_end_date')->nullable()->after('status');
            }
        });
        
        // Add missing columns to customers
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'address')) {
                $table->text('address')->nullable()->after('email');
            }
            if (!Schema::hasColumn('customers', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('customers', 'last_contacted_at')) {
                $table->date('last_contacted_at')->nullable();
            }
            if (!Schema::hasColumn('customers', 'next_follow_up_at')) {
                $table->date('next_follow_up_at')->nullable();
            }
        });
        
        // Add more fields to plan_items
        Schema::table('plan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_items', 'status')) {
                $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            }
            if (!Schema::hasColumn('plan_items', 'priority')) {
                $table->integer('priority')->default(1);
            }
            if (!Schema::hasColumn('plan_items', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['assigned_to', 'internal_notes', 'reminder_hours']);
        });
        
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal', 
                'discount_amount', 
                'tax_amount', 
                'discount_type', 
                'discount_value',
                'issued_at',
                'due_date',
                'paid_at'
            ]);
        });
        
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn([
                'priority',
                'expected_start_date',
                'expected_end_date',
                'actual_start_date',
                'actual_end_date'
            ]);
        });
        
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['address', 'assigned_to', 'last_contacted_at', 'next_follow_up_at']);
        });
        
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn(['status', 'priority', 'notes']);
        });
    }
};
