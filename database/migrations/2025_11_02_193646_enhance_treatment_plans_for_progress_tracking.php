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
        // Enhance treatment_plans table
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->integer('total_visits')->default(1)->after('total_estimated_cost');
            $table->integer('completed_visits')->default(0)->after('total_visits');
            $table->integer('progress_percentage')->default(0)->after('completed_visits'); // 0-100
            $table->string('before_photo')->nullable()->after('notes'); // Single main before photo
            $table->string('after_photo')->nullable()->after('before_photo'); // Single main after photo
            $table->date('actual_start_date')->nullable()->after('expected_end_date');
            $table->date('actual_end_date')->nullable()->after('actual_start_date');
        });

        // Enhance plan_items table
        Schema::table('plan_items', function (Blueprint $table) {
            // Tooth notation (FDI: 11-48, Universal: 1-32)
            $table->string('tooth_number')->nullable()->after('service_id'); // e.g., "11", "16", "21-22"
            $table->enum('tooth_notation', ['fdi', 'universal'])->default('fdi')->after('tooth_number');
            
            // Actual cost tracking
            $table->decimal('estimated_cost', 12, 2)->default(0)->after('price'); // Renamed from price for clarity
            $table->decimal('actual_cost', 12, 2)->default(0)->after('estimated_cost');
            
            // Visit tracking
            $table->integer('required_visits')->default(1)->after('quantity');
            $table->integer('completed_visits')->default(0)->after('required_visits');
            
            // Photos for this specific item (before/after)
            $table->string('before_photo')->nullable()->after('notes');
            $table->string('after_photo')->nullable()->after('before_photo');
            
            // Progress tracking
            $table->integer('progress_percentage')->default(0)->after('after_photo'); // 0-100
            $table->date('started_at')->nullable()->after('progress_percentage');
            $table->date('completed_at')->nullable()->after('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn([
                'total_visits',
                'completed_visits',
                'progress_percentage',
                'before_photo',
                'after_photo',
                'actual_start_date',
                'actual_end_date',
            ]);
        });

        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn([
                'tooth_number',
                'tooth_notation',
                'estimated_cost',
                'actual_cost',
                'required_visits',
                'completed_visits',
                'before_photo',
                'after_photo',
                'progress_percentage',
                'started_at',
                'completed_at',
            ]);
        });
    }
};
