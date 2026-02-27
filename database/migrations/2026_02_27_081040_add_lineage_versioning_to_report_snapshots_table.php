<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_snapshots')) {
            return;
        }

        Schema::table('report_snapshots', function (Blueprint $table): void {
            if (! Schema::hasColumn('report_snapshots', 'schema_version')) {
                $table->string('schema_version', 80)
                    ->nullable()
                    ->after('snapshot_key')
                    ->index();
            }

            if (! Schema::hasColumn('report_snapshots', 'payload_checksum')) {
                $table->string('payload_checksum', 64)
                    ->nullable()
                    ->after('payload')
                    ->index();
            }

            if (! Schema::hasColumn('report_snapshots', 'lineage_checksum')) {
                $table->string('lineage_checksum', 64)
                    ->nullable()
                    ->after('payload_checksum')
                    ->index();
            }

            if (! Schema::hasColumn('report_snapshots', 'drift_status')) {
                $table->enum('drift_status', ['unknown', 'none', 'schema_changed', 'formula_changed', 'source_changed'])
                    ->default('unknown')
                    ->after('lineage_checksum')
                    ->index();
            }

            if (! Schema::hasColumn('report_snapshots', 'drift_details')) {
                $table->json('drift_details')
                    ->nullable()
                    ->after('drift_status');
            }

            if (! Schema::hasColumn('report_snapshots', 'compared_snapshot_id')) {
                $table->foreignId('compared_snapshot_id')
                    ->nullable()
                    ->after('drift_details')
                    ->constrained('report_snapshots')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('report_snapshots')) {
            return;
        }

        Schema::table('report_snapshots', function (Blueprint $table): void {
            if (Schema::hasColumn('report_snapshots', 'compared_snapshot_id')) {
                $table->dropConstrainedForeignId('compared_snapshot_id');
            }

            if (Schema::hasColumn('report_snapshots', 'drift_details')) {
                $table->dropColumn('drift_details');
            }

            if (Schema::hasColumn('report_snapshots', 'drift_status')) {
                $table->dropColumn('drift_status');
            }

            if (Schema::hasColumn('report_snapshots', 'lineage_checksum')) {
                $table->dropColumn('lineage_checksum');
            }

            if (Schema::hasColumn('report_snapshots', 'payload_checksum')) {
                $table->dropColumn('payload_checksum');
            }

            if (Schema::hasColumn('report_snapshots', 'schema_version')) {
                $table->dropColumn('schema_version');
            }
        });
    }
};
