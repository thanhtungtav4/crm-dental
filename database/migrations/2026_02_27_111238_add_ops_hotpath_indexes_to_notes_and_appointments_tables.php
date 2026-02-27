<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('notes')
            && Schema::hasColumn('notes', 'care_type')
            && Schema::hasColumn('notes', 'care_status')
            && Schema::hasColumn('notes', 'care_at')
        ) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->index(
                    ['care_type', 'care_status', 'care_at'],
                    'notes_care_type_status_care_at_index',
                );
            });
        }

        if (
            Schema::hasTable('appointments')
            && Schema::hasColumn('appointments', 'branch_id')
            && Schema::hasColumn('appointments', 'doctor_id')
            && Schema::hasColumn('appointments', 'status')
            && Schema::hasColumn('appointments', 'date')
        ) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->index(
                    ['branch_id', 'doctor_id', 'status', 'date'],
                    'appointments_branch_doctor_status_date_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->dropIndex('appointments_branch_doctor_status_date_index');
            });
        }

        if (Schema::hasTable('notes')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->dropIndex('notes_care_type_status_care_at_index');
            });
        }
    }
};
