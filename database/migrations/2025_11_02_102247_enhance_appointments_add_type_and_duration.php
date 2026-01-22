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
        Schema::table('appointments', function (Blueprint $table) {
            // Appointment classification
            $table->enum('appointment_type', ['consultation', 'treatment', 'follow_up', 'emergency'])
                ->default('consultation')
                ->after('branch_id');
            
            // Duration tracking
            $table->integer('duration_minutes')->default(30)->after('appointment_type');
            
            // Chief complaint (reason for visit)
            $table->text('chief_complaint')->nullable()->after('note');
            
            // Confirmation tracking
            $table->timestamp('confirmed_at')->nullable()->after('chief_complaint');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            
            // Indexes for filtering & scheduling
            $table->index('appointment_type');
            $table->index(['doctor_id', 'date', 'duration_minutes']); // Schedule optimization
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn([
                'appointment_type',
                'duration_minutes',
                'chief_complaint',
                'confirmed_at',
                'confirmed_by'
            ]);
        });
    }
};
