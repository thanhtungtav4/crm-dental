<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->default(now());

            $table->text('examination_note')->nullable()->comment('Nội dung khám tổng quát');
            $table->text('treatment_plan_note')->nullable()->comment('Ghi chú kế hoạch điều trị');

            $table->json('indications')->nullable()->comment('Chỉ định cận lâm sàng (X-Quang, XN máu...)');
            $table->json('diagnoses')->nullable()->comment('Chẩn đoán và sơ đồ răng');

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
    }
};
