<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->boolean('is_overbooked')->default(false)->after('is_emergency');
            $table->text('overbooking_reason')->nullable()->after('reschedule_reason');
            $table->timestamp('overbooking_override_at')->nullable()->after('overbooking_reason');
            $table->unsignedBigInteger('overbooking_override_by')->nullable()->after('overbooking_override_at');

            $table->foreign('overbooking_override_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('is_overbooked');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['overbooking_override_by']);
            $table->dropIndex(['is_overbooked']);
            $table->dropColumn([
                'is_overbooked',
                'overbooking_reason',
                'overbooking_override_at',
                'overbooking_override_by',
            ]);
        });
    }
};
