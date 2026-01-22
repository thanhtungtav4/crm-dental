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
        // ===== CUSTOMERS TABLE =====
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->indexExists('customers', 'idx_customers_phone')) {
                $table->index('phone', 'idx_customers_phone');
            }
            if (!$this->indexExists('customers', 'idx_customers_email')) {
                $table->index('email', 'idx_customers_email');
            }
            if (!$this->indexExists('customers', 'idx_customers_status')) {
                $table->index('status', 'idx_customers_status');
            }
            if (!$this->indexExists('customers', 'idx_customers_source')) {
                $table->index('source', 'idx_customers_source');
            }
            if (!$this->indexExists('customers', 'idx_customers_branch_status')) {
                $table->index(['branch_id', 'status'], 'idx_customers_branch_status');
            }
            if (!$this->indexExists('customers', 'idx_customers_created_at')) {
                $table->index('created_at', 'idx_customers_created_at');
            }
        });

        // ===== PATIENTS TABLE =====
        Schema::table('patients', function (Blueprint $table) {
            $table->index('phone', 'idx_patients_phone'); // Search by phone
            $table->index('email', 'idx_patients_email'); // Search by email
            $table->index('customer_id', 'idx_patients_customer'); // Join with customers
            $table->index(['first_branch_id', 'created_at'], 'idx_patients_branch_date'); // Branch analytics
        });

        // ===== APPOINTMENTS TABLE =====
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('date', 'idx_appointments_date'); // Calendar queries
            $table->index('status', 'idx_appointments_status'); // Filter by status
            $table->index(['doctor_id', 'date'], 'idx_appointments_doctor_date'); // Doctor schedule
            $table->index(['branch_id', 'date'], 'idx_appointments_branch_date'); // Branch schedule
            $table->index(['customer_id', 'status'], 'idx_appointments_customer_status'); // Customer appointments
            $table->index(['patient_id', 'status'], 'idx_appointments_patient_status'); // Patient appointments
        });

        // ===== TREATMENT PLANS TABLE =====
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->index('status', 'idx_treatment_plans_status'); // Filter by status
            $table->index(['patient_id', 'status'], 'idx_treatment_plans_patient_status'); // Patient plans
            $table->index(['doctor_id', 'status'], 'idx_treatment_plans_doctor_status'); // Doctor workload
            $table->index('approved_at', 'idx_treatment_plans_approved_at'); // Approval reports
        });

        // ===== TREATMENT SESSIONS TABLE =====
        Schema::table('treatment_sessions', function (Blueprint $table) {
            $table->index('status', 'idx_treatment_sessions_status'); // Filter by status
            $table->index(['treatment_plan_id', 'performed_at'], 'idx_sessions_plan_date'); // Plan history
            $table->index(['doctor_id', 'performed_at'], 'idx_sessions_doctor_date'); // Doctor schedule
            $table->index('performed_at', 'idx_sessions_performed_at'); // Date range queries
        });

        // ===== INVOICES TABLE =====
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('status', 'idx_invoices_status'); // Filter by status
            $table->index(['patient_id', 'status'], 'idx_invoices_patient_status'); // Patient invoices
            $table->index('created_at', 'idx_invoices_created_at'); // Revenue reports
            $table->index(['treatment_plan_id', 'status'], 'idx_invoices_plan_status'); // Plan invoices
        });

        // ===== PAYMENTS TABLE =====
        Schema::table('payments', function (Blueprint $table) {
            $table->index('payment_date', 'idx_payments_date'); // Revenue by date
            $table->index('payment_method', 'idx_payments_method'); // Payment method reports
            $table->index(['patient_id', 'payment_date'], 'idx_payments_patient_date'); // Patient payments
            $table->index(['invoice_id', 'payment_date'], 'idx_payments_invoice_date'); // Invoice payments
        });

        // ===== MATERIALS TABLE =====
        Schema::table('materials', function (Blueprint $table) {
            $table->index('sku', 'idx_materials_sku'); // Search by SKU
            $table->index(['branch_id', 'quantity_in_stock'], 'idx_materials_branch_stock'); // Stock alerts
        });

        // ===== INVENTORY TRANSACTIONS TABLE =====
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index(['material_id', 'created_at'], 'idx_inventory_material_date'); // Material history
            $table->index(['branch_id', 'type', 'created_at'], 'idx_inventory_branch_type_date'); // Branch reports
        });

        // ===== NOTES TABLE =====
        Schema::table('notes', function (Blueprint $table) {
            $table->index(['notable_type', 'notable_id'], 'idx_notes_notable'); // Polymorphic queries
            $table->index(['customer_id', 'created_at'], 'idx_notes_customer_date'); // Customer notes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all indexes
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_phone');
            $table->dropIndex('idx_customers_email');
            $table->dropIndex('idx_customers_status');
            $table->dropIndex('idx_customers_source');
            $table->dropIndex('idx_customers_branch_status');
            $table->dropIndex('idx_customers_created_at');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('idx_patients_phone');
            $table->dropIndex('idx_patients_email');
            $table->dropIndex('idx_patients_customer');
            $table->dropIndex('idx_patients_branch_date');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_date');
            $table->dropIndex('idx_appointments_status');
            $table->dropIndex('idx_appointments_doctor_date');
            $table->dropIndex('idx_appointments_branch_date');
            $table->dropIndex('idx_appointments_customer_status');
            $table->dropIndex('idx_appointments_patient_status');
        });

        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropIndex('idx_treatment_plans_status');
            $table->dropIndex('idx_treatment_plans_patient_status');
            $table->dropIndex('idx_treatment_plans_doctor_status');
            $table->dropIndex('idx_treatment_plans_approved_at');
        });

        Schema::table('treatment_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_treatment_sessions_status');
            $table->dropIndex('idx_sessions_plan_date');
            $table->dropIndex('idx_sessions_doctor_date');
            $table->dropIndex('idx_sessions_performed_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_status');
            $table->dropIndex('idx_invoices_patient_status');
            $table->dropIndex('idx_invoices_created_at');
            $table->dropIndex('idx_invoices_plan_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_date');
            $table->dropIndex('idx_payments_method');
            $table->dropIndex('idx_payments_patient_date');
            $table->dropIndex('idx_payments_invoice_date');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex('idx_materials_sku');
            $table->dropIndex('idx_materials_branch_stock');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_material_date');
            $table->dropIndex('idx_inventory_branch_type_date');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('idx_notes_notable');
            $table->dropIndex('idx_notes_customer_date');
        });
    }
    
    /**
     * Check if index exists on table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
        $doctrineTable = $doctrineSchemaManager->introspectTable($table);
        
        return $doctrineTable->hasIndex($indexName);
    }
};
