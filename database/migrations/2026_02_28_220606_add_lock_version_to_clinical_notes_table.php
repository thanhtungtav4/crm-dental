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
        Schema::table('clinical_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('clinical_notes', 'lock_version')) {
                $table->unsignedInteger('lock_version')
                    ->default(1)
                    ->after('updated_by');
            }
        });

        if (Schema::hasColumn('clinical_notes', 'lock_version')) {
            DB::table('clinical_notes')
                ->whereNull('lock_version')
                ->update(['lock_version' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinical_notes', function (Blueprint $table) {
            if (Schema::hasColumn('clinical_notes', 'lock_version')) {
                $table->dropColumn('lock_version');
            }
        });
    }
};
