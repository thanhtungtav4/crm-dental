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
        Schema::create('treatment_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_session_id')->nullable()->constrained('treatment_sessions')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('cost', 12, 2)->default(0);
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); // This line remains as it is already present
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_materials');
    }
};
