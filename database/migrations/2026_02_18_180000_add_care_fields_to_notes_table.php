<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('notes')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            if (!Schema::hasColumn('notes', 'care_type')) {
                $table->string('care_type', 100)->nullable()->after('type');
            }

            if (!Schema::hasColumn('notes', 'care_channel')) {
                $table->string('care_channel', 50)->nullable()->after('care_type');
            }

            if (!Schema::hasColumn('notes', 'care_status')) {
                $table->string('care_status', 50)->nullable()->after('care_channel');
            }

            if (!Schema::hasColumn('notes', 'care_at')) {
                $table->dateTime('care_at')->nullable()->after('care_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notes')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            if (Schema::hasColumn('notes', 'care_at')) {
                $table->dropColumn('care_at');
            }

            if (Schema::hasColumn('notes', 'care_status')) {
                $table->dropColumn('care_status');
            }

            if (Schema::hasColumn('notes', 'care_channel')) {
                $table->dropColumn('care_channel');
            }

            if (Schema::hasColumn('notes', 'care_type')) {
                $table->dropColumn('care_type');
            }
        });
    }
};

