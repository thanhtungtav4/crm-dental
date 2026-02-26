<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('requires_consent')->default(false)->after('tooth_specific');
            $table->index('requires_consent');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['requires_consent']);
            $table->dropColumn('requires_consent');
        });
    }
};
