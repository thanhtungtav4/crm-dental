<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Drop existing foreign key to doctors (if any), then reference users
            try {
                $table->dropForeign(['doctor_id']);
            } catch (\Throwable $e) {
                // ignore if not exists
            }
            $table->foreign('doctor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            try {
                $table->dropForeign(['doctor_id']);
            } catch (\Throwable $e) {
                // ignore if not exists
            }
            // Restore FK to doctors table if it exists
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
        });
    }
};
