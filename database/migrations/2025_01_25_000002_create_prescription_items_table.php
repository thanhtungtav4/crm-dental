<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('medication_name');
            $table->string('dosage')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('unit')->nullable(); // viên, gói, chai, ống...
            $table->string('instructions')->nullable(); // Ngày uống 2 lần, sáng - tối
            $table->string('duration')->nullable(); // 7 ngày, 2 tuần...
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
