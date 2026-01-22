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
        Schema::create('tooth_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., K02, IMP
            $table->string('name'); // e.g., Sâu răng, Implant
            $table->string('category')->nullable(); // e.g., Bệnh lý, Phục hình
            $table->string('color')->nullable(); // hex code for UI
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tooth_conditions');
    }
};
