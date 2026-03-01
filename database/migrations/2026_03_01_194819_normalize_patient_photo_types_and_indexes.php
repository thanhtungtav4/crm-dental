<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_photos', 'type')) {
                return;
            }

            $table->index(['patient_id', 'type', 'date'], 'patient_photos_patient_type_date_idx');
        });

        DB::table('patient_photos')
            ->where('type', 'ortho')
            ->update(['type' => 'ext']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            if (Schema::hasColumn('patient_photos', 'type')) {
                $table->dropIndex('patient_photos_patient_type_date_idx');
            }
        });

        DB::table('patient_photos')
            ->where('type', 'ext')
            ->update(['type' => 'ortho']);
    }
};
