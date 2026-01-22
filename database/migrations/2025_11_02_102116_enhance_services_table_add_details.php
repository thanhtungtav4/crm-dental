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
        Schema::table('services', function (Blueprint $table) {
            // Category relationship
            $table->foreignId('category_id')->nullable()->after('id')->constrained('service_categories')->nullOnDelete();
            
            // Service details
            $table->text('description')->nullable()->after('name');
            $table->integer('duration_minutes')->default(30)->after('unit');
            $table->boolean('tooth_specific')->default(false)->after('duration_minutes');
            
            // Materials & Commission
            $table->json('default_materials')->nullable()->after('tooth_specific')->comment('Array of {material_id, quantity}');
            $table->decimal('doctor_commission_rate', 5, 2)->default(0)->after('default_materials')->comment('Percentage: 0-100');
            
            // Branch-specific pricing
            $table->foreignId('branch_id')->nullable()->after('doctor_commission_rate')->constrained('branches')->nullOnDelete()->comment('NULL = all branches, specific = branch-only service');
            
            // Display order
            $table->integer('sort_order')->default(0)->after('active');
            
            // Indexes
            $table->index('category_id');
            $table->index('active');
            $table->index('branch_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn([
                'category_id',
                'description',
                'duration_minutes',
                'tooth_specific',
                'default_materials',
                'doctor_commission_rate',
                'branch_id',
                'sort_order'
            ]);
        });
    }
};
