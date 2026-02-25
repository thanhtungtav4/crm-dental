<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            $table->enum('approval_status', ['draft', 'proposed', 'approved', 'declined'])
                ->default('proposed')
                ->after('patient_approved');
            $table->text('approval_decline_reason')->nullable()->after('approval_status');
            $table->index('approval_status', 'idx_plan_items_approval_status');
        });

        DB::table('plan_items')
            ->where(function ($query) {
                $query->where('patient_approved', true)
                    ->orWhereIn('status', ['in_progress', 'completed']);
            })
            ->update([
                'approval_status' => 'approved',
                'patient_approved' => true,
            ]);

        DB::table('plan_items')
            ->where('approval_status', '<>', 'approved')
            ->update([
                'approval_status' => 'proposed',
                'patient_approved' => false,
            ]);
    }

    public function down(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropIndex('idx_plan_items_approval_status');
            $table->dropColumn([
                'approval_status',
                'approval_decline_reason',
            ]);
        });
    }
};
