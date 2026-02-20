<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Skip if table doesn't exist (SQLite test env may not have it)
        if (!Schema::hasTable('clinical_notes')) {
            return;
        }

        Schema::table('clinical_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('clinical_notes', 'indication_types')) {
                $table->json('indication_types')->nullable(); // ['cephalometric', 'panorama', '3d', 'anh_ext', 'anh_int', etc.]
            }
            // indication_images already exists in the table
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clinical_notes')) {
            return;
        }

        Schema::table('clinical_notes', function (Blueprint $table) {
            if (Schema::hasColumn('clinical_notes', 'indication_types')) {
                $table->dropColumn('indication_types');
            }
        });
    }
};
