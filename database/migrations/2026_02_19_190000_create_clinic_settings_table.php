<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('clinic_settings')) {
            return;
        }

        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 100)->index();
            $table->string('key', 150)->unique();
            $table->string('label', 255);
            $table->text('value')->nullable();
            $table->string('value_type', 30)->default('text');
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_settings');
    }
};

