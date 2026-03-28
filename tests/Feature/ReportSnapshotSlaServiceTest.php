<?php

use App\Models\Branch;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\ReportSnapshotSlaService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Carbon;

it('creates a missing snapshot placeholder when the scope has no records', function (): void {
    Carbon::setTestNow('2026-03-28 10:00:00');

    try {
        $branch = Branch::factory()->create();
        $actor = User::factory()->create();

        $summary = app(ReportSnapshotSlaService::class)->checkScope(
            snapshotKey: 'operational_kpi_pack',
            snapshotDate: Carbon::parse('2026-03-27'),
            branchId: $branch->id,
            dryRun: false,
            actorId: $actor->id,
        );

        $placeholder = ReportSnapshot::query()
            ->where('snapshot_key', 'operational_kpi_pack')
            ->whereDate('snapshot_date', '2026-03-27')
            ->where('branch_id', $branch->id)
            ->first();

        expect($summary)->toBe([
            'on_time' => 0,
            'late' => 0,
            'stale' => 0,
            'missing' => 1,
        ])
            ->and($placeholder)->not->toBeNull()
            ->and($placeholder?->status)->toBe(ReportSnapshot::STATUS_FAILED)
            ->and($placeholder?->sla_status)->toBe(ReportSnapshot::SLA_MISSING)
            ->and($placeholder?->created_by)->toBe($actor->id)
            ->and($placeholder?->sla_due_at?->toDateTimeString())->toBe(
                Carbon::parse('2026-03-27')->endOfDay()->addHours(ClinicRuntimeSettings::reportSnapshotSlaHours())->toDateTimeString(),
            );
    } finally {
        Carbon::setTestNow();
    }
});

it('classifies on-time late stale and missing snapshots while persisting sla status', function (): void {
    Carbon::setTestNow('2026-03-28 10:00:00');

    try {
        $staleCutoff = now()->subHours(ClinicRuntimeSettings::reportSnapshotStaleAfterHours())->subMinute();
        [$branchA, $branchB, $branchC, $branchD] = Branch::factory()->count(4)->create()->all();

        $onTime = ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-28',
            'branch_id' => $branchA->id,
            'status' => ReportSnapshot::STATUS_SUCCESS,
            'sla_status' => ReportSnapshot::SLA_MISSING,
            'generated_at' => Carbon::parse('2026-03-28 02:00:00'),
            'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
            'payload' => [],
            'lineage' => [],
        ]);

        $late = ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-28',
            'branch_id' => $branchB->id,
            'status' => ReportSnapshot::STATUS_SUCCESS,
            'sla_status' => ReportSnapshot::SLA_MISSING,
            'generated_at' => Carbon::parse('2026-03-28 07:00:00'),
            'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
            'payload' => [],
            'lineage' => [],
        ]);

        $stale = ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-28',
            'branch_id' => $branchC->id,
            'status' => ReportSnapshot::STATUS_SUCCESS,
            'sla_status' => ReportSnapshot::SLA_MISSING,
            'generated_at' => $staleCutoff,
            'sla_due_at' => Carbon::parse('2026-03-28 23:00:00'),
            'payload' => [],
            'lineage' => [],
        ]);

        $missing = ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-28',
            'branch_id' => $branchD->id,
            'status' => ReportSnapshot::STATUS_FAILED,
            'sla_status' => ReportSnapshot::SLA_ON_TIME,
            'generated_at' => null,
            'sla_due_at' => Carbon::parse('2026-03-28 23:00:00'),
            'payload' => [],
            'lineage' => [],
        ]);

        $summary = app(ReportSnapshotSlaService::class)->checkScope(
            snapshotKey: 'operational_kpi_pack',
            snapshotDate: Carbon::parse('2026-03-28'),
            branchId: null,
            dryRun: false,
        );

        expect($summary)->toBe([
            'on_time' => 1,
            'late' => 1,
            'stale' => 1,
            'missing' => 1,
        ])
            ->and($onTime->fresh()?->sla_status)->toBe(ReportSnapshot::SLA_ON_TIME)
            ->and($late->fresh()?->sla_status)->toBe(ReportSnapshot::SLA_LATE)
            ->and($stale->fresh()?->sla_status)->toBe(ReportSnapshot::SLA_STALE)
            ->and($missing->fresh()?->sla_status)->toBe(ReportSnapshot::SLA_MISSING);
    } finally {
        Carbon::setTestNow();
    }
});
