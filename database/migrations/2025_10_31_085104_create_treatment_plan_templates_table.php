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
        Schema::create('treatment_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên template
            $table->string('code')->unique(); // Mã template (VD: IMPLANT_BASIC)
            $table->text('description')->nullable();
            $table->json('items')->nullable(); // Danh sách dịch vụ mẫu
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->integer('estimated_duration')->nullable()->comment('Days');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('is_active');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_templates');
    }
};
