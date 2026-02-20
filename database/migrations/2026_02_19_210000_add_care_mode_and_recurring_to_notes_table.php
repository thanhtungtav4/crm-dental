<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            if (! Schema::hasColumn('notes', 'care_mode')) {
                $table->string('care_mode', 20)->nullable()->after('care_at');
                $table->index('care_mode');
            }

            if (! Schema::hasColumn('notes', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('care_mode');
            }
        });

        DB::table('notes')
            ->whereNull('care_mode')
            ->update([
                'care_mode' => DB::raw("CASE WHEN care_status = 'planned' THEN 'scheduled' ELSE 'immediate' END"),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            if (Schema::hasColumn('notes', 'is_recurring')) {
                $table->dropColumn('is_recurring');
            }

            if (Schema::hasColumn('notes', 'care_mode')) {
                $table->dropIndex(['care_mode']);
                $table->dropColumn('care_mode');
            }
        });
    }
};

