<?php

use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Patient;
use App\Models\User;
use App\Services\ConsentLifecycleService;
use Illuminate\Validation\ValidationException;

it('signs consent with signature context and locks content changes afterwards', function (): void {
    $patient = Patient::factory()->create();
    $signer = User::factory()->create();

    $consent = Consent::query()->create([
        'patient_id' => $patient->id,
        'consent_type' => 'high_risk',
        'consent_version' => 'v1',
        'status' => Consent::STATUS_PENDING,
        'note' => 'Đã giải thích rủi ro trước khi ký.',
    ]);

    $signed = app(ConsentLifecycleService::class)->sign(
        consent: $consent,
        signedBy: $signer->id,
        signatureContext: [
            'source' => 'staff_confirmed',
            'ip_address' => '127.0.0.1',
        ],
    );

    expect($signed->status)->toBe(Consent::STATUS_SIGNED)
        ->and((int) $signed->signed_by)->toBe($signer->id)
        ->and($signed->signed_at)->not->toBeNull()
        ->and((array) $signed->signature_context)->toMatchArray([
            'source' => 'staff_confirmed',
            'ip_address' => '127.0.0.1',
        ]);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_CONSENT)
        ->where('entity_id', $signed->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->actor_id)->toBe($signer->id)
        ->and($auditLog->patient_id)->toBe($patient->id)
        ->and(data_get($auditLog, 'metadata.status_from'))->toBe(Consent::STATUS_PENDING)
        ->and(data_get($auditLog, 'metadata.status_to'))->toBe(Consent::STATUS_SIGNED)
        ->and(data_get($auditLog, 'metadata.trigger'))->toBe('manual_sign')
        ->and(data_get($auditLog, 'metadata.signature_source'))->toBe('staff_confirmed');

    expect(fn () => $signed->update([
        'note' => 'Co gang sua lai noi dung sau khi da ky.',
    ]))->toThrow(ValidationException::class, 'CONSENT_CONTENT_LOCKED');
});

it('allows only valid consent transitions through lifecycle service', function (): void {
    $patient = Patient::factory()->create();
    $signer = User::factory()->create();

    $consent = Consent::query()->create([
        'patient_id' => $patient->id,
        'consent_type' => 'treatment',
        'consent_version' => 'v1',
        'status' => Consent::STATUS_PENDING,
    ]);

    $signed = app(ConsentLifecycleService::class)->sign($consent, $signer->id, ['source' => 'staff_confirmed']);
    $revoked = app(ConsentLifecycleService::class)->revoke($signed);

    expect($revoked->status)->toBe(Consent::STATUS_REVOKED)
        ->and($revoked->revoked_at)->not->toBeNull();

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_CONSENT)
        ->where('entity_id', $revoked->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->actor_id)->toBe($signer->id)
        ->and($auditLog->patient_id)->toBe($patient->id)
        ->and(data_get($auditLog, 'metadata.status_from'))->toBe(Consent::STATUS_SIGNED)
        ->and(data_get($auditLog, 'metadata.status_to'))->toBe(Consent::STATUS_REVOKED)
        ->and(data_get($auditLog, 'metadata.trigger'))->toBe('manual_revoke');

    expect(fn () => $revoked->update([
        'status' => Consent::STATUS_PENDING,
    ]))->toThrow(ValidationException::class, 'ConsentLifecycleService');
});
