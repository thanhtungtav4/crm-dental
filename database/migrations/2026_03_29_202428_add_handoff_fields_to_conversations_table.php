<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('handoff_priority', 20)
                ->default('normal')
                ->after('last_outbound_at');
            $table->text('handoff_summary')
                ->nullable()
                ->after('handoff_priority');
            $table->foreignId('handoff_updated_by')
                ->nullable()
                ->after('handoff_summary')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('handoff_updated_at')
                ->nullable()
                ->after('handoff_updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handoff_updated_by');
            $table->dropColumn([
                'handoff_priority',
                'handoff_summary',
                'handoff_updated_at',
            ]);
        });
    }
};
