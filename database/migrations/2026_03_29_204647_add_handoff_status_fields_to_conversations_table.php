<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('handoff_status', 40)
                ->default('new')
                ->after('handoff_priority');
            $table->dateTime('handoff_next_action_at')
                ->nullable()
                ->after('handoff_summary');
            $table->unsignedInteger('handoff_version')
                ->default(0)
                ->after('handoff_updated_at');
            $table->index(
                ['handoff_status', 'handoff_next_action_at'],
                'conversations_handoff_status_next_action_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_handoff_status_next_action_index');
            $table->dropColumn([
                'handoff_status',
                'handoff_next_action_at',
                'handoff_version',
            ]);
        });
    }
};
