<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_transfer_requests', function (Blueprint $table): void {
            $table->index(
                ['patient_id', 'to_branch_id', 'status'],
                'branch_transfer_requests_patient_target_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('branch_transfer_requests', function (Blueprint $table): void {
            $table->dropIndex('branch_transfer_requests_patient_target_status_index');
        });
    }
};
