<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_snapshots')) {
            return;
        }

        if (! Schema::hasColumn('report_snapshots', 'branch_scope_id')) {
            Schema::table('report_snapshots', function (Blueprint $table): void {
                $table->unsignedBigInteger('branch_scope_id')
                    ->default(0)
                    ->after('branch_id');
            });
        }

        DB::table('report_snapshots')
            ->whereNotNull('branch_id')
            ->update([
                'branch_scope_id' => DB::raw('branch_id'),
            ]);

        $duplicateGroups = DB::table('report_snapshots')
            ->select([
                'snapshot_key',
                'snapshot_date',
                'branch_scope_id',
                DB::raw('MAX(id) as keep_id'),
                DB::raw('COUNT(*) as total_rows'),
            ])
            ->groupBy('snapshot_key', 'snapshot_date', 'branch_scope_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('report_snapshots')
                ->where('snapshot_key', (string) $group->snapshot_key)
                ->whereDate('snapshot_date', (string) $group->snapshot_date)
                ->where('branch_scope_id', (int) $group->branch_scope_id)
                ->where('id', '!=', (int) $group->keep_id)
                ->delete();
        }

        Schema::table('report_snapshots', function (Blueprint $table): void {
            $table->unique(
                ['snapshot_key', 'snapshot_date', 'branch_scope_id'],
                'report_snapshots_unique_snapshot_scope',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('report_snapshots')) {
            return;
        }

        if (! Schema::hasColumn('report_snapshots', 'branch_scope_id')) {
            return;
        }

        Schema::table('report_snapshots', function (Blueprint $table): void {
            $table->dropUnique('report_snapshots_unique_snapshot_scope');
            $table->dropColumn('branch_scope_id');
        });
    }
};
