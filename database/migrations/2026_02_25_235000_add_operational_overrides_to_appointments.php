<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointments', 'is_walk_in')) {
                $table->boolean('is_walk_in')->default(false)->after('status');
            }

            if (! Schema::hasColumn('appointments', 'is_emergency')) {
                $table->boolean('is_emergency')->default(false)->after('is_walk_in');
            }

            if (! Schema::hasColumn('appointments', 'late_arrival_minutes')) {
                $table->unsignedInteger('late_arrival_minutes')->nullable()->after('is_emergency');
            }

            if (! Schema::hasColumn('appointments', 'operation_override_reason')) {
                $table->text('operation_override_reason')->nullable()->after('reschedule_reason');
            }

            if (! Schema::hasColumn('appointments', 'operation_override_at')) {
                $table->timestamp('operation_override_at')->nullable()->after('operation_override_reason');
            }

            if (! Schema::hasColumn('appointments', 'operation_override_by')) {
                $table->foreignId('operation_override_by')
                    ->nullable()
                    ->after('operation_override_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('appointment_override_logs')) {
            Schema::create('appointment_override_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
                $table->string('override_type', 50);
                $table->text('reason');
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['appointment_id', 'override_type']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_override_logs');

        Schema::table('appointments', function (Blueprint $table): void {
            if (Schema::hasColumn('appointments', 'operation_override_by')) {
                $table->dropConstrainedForeignId('operation_override_by');
            }

            foreach ([
                'operation_override_at',
                'operation_override_reason',
                'late_arrival_minutes',
                'is_emergency',
                'is_walk_in',
            ] as $column) {
                if (Schema::hasColumn('appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
