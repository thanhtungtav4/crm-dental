<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinic_setting_logs')) {
            return;
        }

        Schema::table('clinic_setting_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('clinic_setting_logs', 'change_reason')) {
                $table->string('change_reason')->nullable()->after('new_value');
            }

            if (! Schema::hasColumn('clinic_setting_logs', 'context')) {
                $table->json('context')->nullable()->after('change_reason');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clinic_setting_logs')) {
            return;
        }

        Schema::table('clinic_setting_logs', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_setting_logs', 'context')) {
                $table->dropColumn('context');
            }

            if (Schema::hasColumn('clinic_setting_logs', 'change_reason')) {
                $table->dropColumn('change_reason');
            }
        });
    }
};
