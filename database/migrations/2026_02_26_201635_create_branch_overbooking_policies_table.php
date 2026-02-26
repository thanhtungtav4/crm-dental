<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_overbooking_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedTinyInteger('max_parallel_per_doctor')->default(1);
            $table->boolean('require_override_reason')->default(true);
            $table->timestamps();

            $table->unique('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_overbooking_policies');
    }
};
