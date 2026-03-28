<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\OperationalAutomationAuditReadModelService;
use Illuminate\Support\Carbon;

it('counts only tracked recent automation failures inside the lookback window', function (): void {
    $actor = User::factory()->create([
        'email' => 'ops@example.test',
    ]);

    AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_FAIL,
        actorId: $actor->id,
        metadata: [
            'command' => 'ops:check-backup-health',
            'status' => 'failed',
        ],
    );

    AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_FAIL,
        actorId: $actor->id,
        metadata: [
            'channel' => 'observability_health',
            'status' => 'failed',
        ],
    );

    AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_FAIL,
        actorId: $actor->id,
        metadata: [
            'command' => 'care:send-birthday-message',
            'status' => 'failed',
        ],
    );

    $staleAudit = AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_FAIL,
        actorId: $actor->id,
        metadata: [
            'command' => 'ops:run-release-gates',
            'status' => 'failed',
        ],
    );
    $staleAudit->forceFill([
        'created_at' => Carbon::parse('2026-03-20 00:00:00'),
        'occurred_at' => Carbon::parse('2026-03-20 00:00:00'),
        'updated_at' => Carbon::parse('2026-03-20 00:00:00'),
    ])->saveQuietly();

    $service = app(OperationalAutomationAuditReadModelService::class);

    expect($service->recentFailureCount(now()->subHour()))->toBe(2);
});

it('returns recent tracked ops runs with actor loaded and newest first', function (): void {
    $actor = User::factory()->create([
        'email' => 'ops-runner@example.test',
    ]);

    $first = AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_RUN,
        actorId: $actor->id,
        metadata: [
            'command' => 'ops:run-production-readiness',
            'status' => 'pass',
        ],
    );

    $second = AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_FAIL,
        actorId: $actor->id,
        metadata: [
            'channel' => 'release_gates',
            'status' => 'fail',
        ],
    );

    AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_RUN,
        actorId: $actor->id,
        metadata: [
            'command' => 'marketing:send-digest',
            'status' => 'pass',
        ],
    );

    $runs = app(OperationalAutomationAuditReadModelService::class)->recentRuns(10);

    expect($runs)->toHaveCount(2)
        ->and($runs->first()->is($second))->toBeTrue()
        ->and($runs->last()->is($first))->toBeTrue()
        ->and($runs->every(fn (AuditLog $auditLog): bool => $auditLog->relationLoaded('actor')))->toBeTrue();
});
