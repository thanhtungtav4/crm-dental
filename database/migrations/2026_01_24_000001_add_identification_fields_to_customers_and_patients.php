<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add identification fields to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->string('identification_hash')->nullable()->index()->after('notes');
            $table->timestamp('last_verified_at')->nullable()->after('identification_hash');
            $table->text('verification_notes')->nullable()->after('last_verified_at');
        });

        // Add identification fields to patients table
        Schema::table('patients', function (Blueprint $table) {
            $table->string('identification_hash')->nullable()->index()->after('updated_by');
            $table->timestamp('last_verified_at')->nullable()->after('identification_hash');
            $table->text('verification_notes')->nullable()->after('last_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['identification_hash', 'last_verified_at', 'verification_notes']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['identification_hash', 'last_verified_at', 'verification_notes']);
        });
    }
};