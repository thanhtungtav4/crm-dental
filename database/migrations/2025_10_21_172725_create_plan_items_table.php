<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->nullable()->constrained('treatment_plans')->nullOnDelete();
            $table->string('name'); // fallback displayed service name
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->json('estimated_supplies')->nullable(); // [{material_id, qty}]
            $table->enum('status',['pending','in_progress','completed','cancelled'])->default('pending');
            $table->enum('priority',['low','normal','high','urgent'])->default('normal');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
