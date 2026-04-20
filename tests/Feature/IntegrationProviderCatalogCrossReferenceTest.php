<?php

use App\Services\IntegrationOperationalReadModelService;
use App\Services\IntegrationProviderActionService;
use App\Services\IntegrationProviderHealthReadModelService;

// ---------------------------------------------------------------------------
// Provider catalog — key contract
// ---------------------------------------------------------------------------

it('health read-model returns exactly 7 provider cards', function (): void {
    $svc = app(IntegrationProviderHealthReadModelService::class);
    $cards = $svc->cards();

    expect($cards)->toHaveCount(7);

    $keys = array_column($cards, 'key');
    expect($keys)->toContain('zalo_oa')
        ->toContain('facebook_messenger')
        ->toContain('zns')
        ->toContain('google_calendar')
        ->toContain('emr')
        ->toContain('dicom')
        ->toContain('web_lead');
});

it('every provider card contains all required structural keys', function (): void {
    $svc = app(IntegrationProviderHealthReadModelService::class);

    foreach ($svc->cards() as $card) {
        expect($card)->toHaveKey('key')
            ->toHaveKey('label')
            ->toHaveKey('enabled')
            ->toHaveKey('tone')
            ->toHaveKey('status')
            ->toHaveKey('score')
            ->toHaveKey('issues')
            ->toHaveKey('recommendations')
            ->toHaveKey('issue_count')
            ->toHaveKey('recommendation_count');

        expect($card['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        expect($card['issues'])->toBeArray();
        expect($card['recommendations'])->toBeArray();
    }
});

it('counts() always sums healthy + degraded + disabled to total provider count', function (): void {
    $svc = app(IntegrationProviderHealthReadModelService::class);
    $counts = $svc->counts();

    $total = $counts['healthy'] + $counts['degraded'] + $counts['disabled'];

    expect($total)->toBe(count($svc->cards()));
});

it('provider() throws InvalidArgumentException for unknown provider key', function (): void {
    $svc = app(IntegrationProviderHealthReadModelService::class);

    expect(fn () => $svc->provider('unknown_provider'))
        ->toThrow(InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// Action service — readiness label catalog
// ---------------------------------------------------------------------------

it('readiness providers are all accessible through IntegrationProviderActionService', function (): void {
    $svc = app(IntegrationProviderActionService::class);

    $readinessProviders = ['zalo_oa', 'facebook_messenger', 'zns', 'dicom', 'web_lead'];

    foreach ($readinessProviders as $providerKey) {
        $report = $svc->readinessReport($providerKey);

        expect($report)->toHaveKey('success')
            ->toHaveKey('title')
            ->toHaveKey('body')
            ->toHaveKey('score');
    }
});

it('readiness report returns non-empty label for every readiness provider', function (): void {
    $svc = app(IntegrationProviderActionService::class);

    foreach (['zalo_oa', 'facebook_messenger', 'zns', 'dicom', 'web_lead'] as $providerKey) {
        $report = $svc->readinessReport($providerKey);
        expect(filled($report['title']))->toBeTrue("Provider [{$providerKey}] returned empty title");
        expect($report['score'])->toBeInt();
    }
});

it('readiness notification payload has all required keys for readiness providers', function (): void {
    $svc = app(IntegrationProviderActionService::class);

    foreach (['zalo_oa', 'facebook_messenger', 'zns', 'dicom', 'web_lead'] as $providerKey) {
        $notification = $svc->readinessNotification($providerKey);

        expect($notification)->toHaveKey('title')
            ->toHaveKey('body');
    }
});

// ---------------------------------------------------------------------------
// Operational reader — dead-letter backlog contract
// ---------------------------------------------------------------------------

it('emr and google calendar dead-letter backlog readers return integers from clean state', function (): void {
    $svc = app(IntegrationOperationalReadModelService::class);

    expect($svc->emrDeadBacklogCount())->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($svc->emrFailedBacklogCount())->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($svc->googleCalendarDeadBacklogCount())->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($svc->googleCalendarFailedBacklogCount())->toBeInt()->toBeGreaterThanOrEqual(0);
});

it('emr dead-letter count reflects seeded dead status events', function (): void {
    $svc = app(IntegrationOperationalReadModelService::class);

    $before = $svc->emrDeadBacklogCount();

    \App\Models\EmrSyncEvent::query()->insert([
        'event_key' => 'test-dead-emr-'.uniqid(),
        'patient_id' => \App\Models\Patient::factory()->create()->id,
        'event_type' => 'patient_updated',
        'payload' => json_encode(['type' => 'test']),
        'payload_checksum' => sha1('test'),
        'status' => \App\Models\EmrSyncEvent::STATUS_DEAD,
        'attempts' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($svc->emrDeadBacklogCount())->toBe($before + 1);
});

it('google calendar dead-letter count reflects seeded dead status events', function (): void {
    $svc = app(IntegrationOperationalReadModelService::class);

    $before = $svc->googleCalendarDeadBacklogCount();

    \App\Models\GoogleCalendarSyncEvent::query()->insert([
        'event_key' => 'test-dead-gcal-'.uniqid(),
        'appointment_id' => \App\Models\Appointment::factory()->create()->id,
        'event_type' => 'created',
        'payload' => json_encode(['type' => 'test']),
        'payload_checksum' => sha1('test-gcal'),
        'status' => \App\Models\GoogleCalendarSyncEvent::STATUS_DEAD,
        'attempts' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($svc->googleCalendarDeadBacklogCount())->toBe($before + 1);
});

it('dead-letter count can be scoped to a specific patient or appointment', function (): void {
    $svc = app(IntegrationOperationalReadModelService::class);

    // Unscoped counts include all dead events
    $allEmr = $svc->emrDeadBacklogCount();
    $allGcal = $svc->googleCalendarDeadBacklogCount();

    // Scoped to non-existent IDs → zero
    $scopedEmr = $svc->emrDeadBacklogCount(patientId: 99_999_999);
    $scopedGcal = $svc->googleCalendarDeadBacklogCount(appointmentId: 99_999_999);

    expect($scopedEmr)->toBe(0)
        ->and($scopedGcal)->toBe(0)
        ->and($allEmr)->toBeGreaterThanOrEqual(0)
        ->and($allGcal)->toBeGreaterThanOrEqual(0);
});
