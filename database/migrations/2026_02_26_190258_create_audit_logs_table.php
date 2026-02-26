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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('action');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_index');
            $table->foreign('actor_id', 'audit_logs_actor_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign('audit_logs_actor_id_foreign');
            $table->dropIndex('audit_logs_entity_index');
            $table->dropIndex('audit_logs_action_index');
        });

        Schema::dropIfExists('audit_logs');
    }
};
