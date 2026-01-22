<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add indexes using raw SQL to avoid duplicates
     */
    public function up(): void
    {
        // Skip for SQLite (tests) where information_schema doesn't exist
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Helper to create index if not exists
        $this->createIndexIfNotExists('customers', 'idx_customers_phone', 'phone');
        $this->createIndexIfNotExists('customers', 'idx_customers_email', 'email');
        $this->createIndexIfNotExists('customers', 'idx_customers_status', 'status');
        $this->createIndexIfNotExists('customers', 'idx_customers_source', 'source');
        $this->createIndexIfNotExists('customers', 'idx_customers_created_at', 'created_at');
        
        $this->createIndexIfNotExists('patients', 'idx_patients_phone', 'phone');
        $this->createIndexIfNotExists('patients', 'idx_patients_email', 'email');
        $this->createIndexIfNotExists('patients', 'idx_patients_customer', 'customer_id');
        
        $this->createIndexIfNotExists('appointments', 'idx_appointments_date', 'date');
        $this->createIndexIfNotExists('appointments', 'idx_appointments_status', 'status');
        
        $this->createIndexIfNotExists('treatment_plans', 'idx_treatment_plans_status', 'status');
        $this->createIndexIfNotExists('treatment_plans', 'idx_treatment_plans_priority', 'priority');
        
        $this->createIndexIfNotExists('treatment_sessions', 'idx_sessions_status', 'status');
        $this->createIndexIfNotExists('treatment_sessions', 'idx_sessions_performed_at', 'performed_at');
        
        $this->createIndexIfNotExists('invoices', 'idx_invoices_status', 'status');
        $this->createIndexIfNotExists('invoices', 'idx_invoices_created_at', 'created_at');
        
        $this->createIndexIfNotExists('payments', 'idx_payments_paid_at', 'paid_at');
        $this->createIndexIfNotExists('payments', 'idx_payments_method', 'method');
        
        $this->createIndexIfNotExists('materials', 'idx_materials_sku', 'sku');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Indexes will be dropped if tables are dropped
        // No need to explicitly drop
    }
    
    /**
     * Create index if it doesn't exist
     */
    private function createIndexIfNotExists(string $table, string $indexName, string $column): void
    {
        $exists = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = '{$table}' 
            AND index_name = '{$indexName}'
        ");
        
        if ($exists[0]->count == 0) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        }
    }
};
