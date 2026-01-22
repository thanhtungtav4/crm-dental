<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('treatment_materials', 'treatment_session_id')) {
                $table->foreignId('treatment_session_id')->nullable()->constrained('treatment_sessions')->nullOnDelete();
            }
            if (!Schema::hasColumn('treatment_materials', 'material_id')) {
                $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            }
            if (!Schema::hasColumn('treatment_materials', 'quantity')) {
                $table->integer('quantity')->default(1);
            }
            if (!Schema::hasColumn('treatment_materials', 'cost')) {
                $table->decimal('cost', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('treatment_materials', 'used_by')) {
                $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_materials', function (Blueprint $table) {
            if (Schema::hasColumn('treatment_materials', 'used_by')) {
                $table->dropConstrainedForeignId('used_by');
            }
            if (Schema::hasColumn('treatment_materials', 'treatment_session_id')) {
                $table->dropConstrainedForeignId('treatment_session_id');
            }
            if (Schema::hasColumn('treatment_materials', 'material_id')) {
                $table->dropConstrainedForeignId('material_id');
            }
            if (Schema::hasColumn('treatment_materials', 'quantity')) {
                $table->dropColumn('quantity');
            }
            if (Schema::hasColumn('treatment_materials', 'cost')) {
                $table->dropColumn('cost');
            }
        });
    }
};
