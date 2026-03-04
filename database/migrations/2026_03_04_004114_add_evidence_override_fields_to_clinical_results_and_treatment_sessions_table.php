<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinical_results')) {
            Schema::table('clinical_results', function (Blueprint $table): void {
                if (! Schema::hasColumn('clinical_results', 'evidence_override_reason')) {
                    $table->text('evidence_override_reason')
                        ->nullable()
                        ->after('notes');
                }

                if (! Schema::hasColumn('clinical_results', 'evidence_override_by')) {
                    $table->foreignId('evidence_override_by')
                        ->nullable()
                        ->after('evidence_override_reason')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('clinical_results', 'evidence_override_at')) {
                    $table->dateTime('evidence_override_at')
                        ->nullable()
                        ->after('evidence_override_by');
                }

                if (! Schema::hasIndex('clinical_results', 'clinical_results_status_override_idx')) {
                    $table->index(['status', 'evidence_override_at'], 'clinical_results_status_override_idx');
                }
            });
        }

        if (Schema::hasTable('treatment_sessions')) {
            Schema::table('treatment_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('treatment_sessions', 'evidence_override_reason')) {
                    $table->text('evidence_override_reason')
                        ->nullable()
                        ->after('status');
                }

                if (! Schema::hasColumn('treatment_sessions', 'evidence_override_by')) {
                    $table->foreignId('evidence_override_by')
                        ->nullable()
                        ->after('evidence_override_reason')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('treatment_sessions', 'evidence_override_at')) {
                    $table->dateTime('evidence_override_at')
                        ->nullable()
                        ->after('evidence_override_by');
                }

                if (! Schema::hasIndex('treatment_sessions', 'treatment_sessions_status_override_idx')) {
                    $table->index(['status', 'evidence_override_at'], 'treatment_sessions_status_override_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clinical_results')) {
            Schema::table('clinical_results', function (Blueprint $table): void {
                if (Schema::hasColumn('clinical_results', 'evidence_override_by')) {
                    $table->dropConstrainedForeignId('evidence_override_by');
                }

                if (Schema::hasColumn('clinical_results', 'evidence_override_reason')) {
                    $table->dropColumn('evidence_override_reason');
                }

                if (Schema::hasColumn('clinical_results', 'evidence_override_at')) {
                    $table->dropColumn('evidence_override_at');
                }

                if (Schema::hasIndex('clinical_results', 'clinical_results_status_override_idx')) {
                    $table->dropIndex('clinical_results_status_override_idx');
                }
            });
        }

        if (Schema::hasTable('treatment_sessions')) {
            Schema::table('treatment_sessions', function (Blueprint $table): void {
                if (Schema::hasColumn('treatment_sessions', 'evidence_override_by')) {
                    $table->dropConstrainedForeignId('evidence_override_by');
                }

                if (Schema::hasColumn('treatment_sessions', 'evidence_override_reason')) {
                    $table->dropColumn('evidence_override_reason');
                }

                if (Schema::hasColumn('treatment_sessions', 'evidence_override_at')) {
                    $table->dropColumn('evidence_override_at');
                }

                if (Schema::hasIndex('treatment_sessions', 'treatment_sessions_status_override_idx')) {
                    $table->dropIndex('treatment_sessions_status_override_idx');
                }
            });
        }
    }
};
