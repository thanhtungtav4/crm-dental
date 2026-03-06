<?php

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

    expect(fn () => $revoked->update([
        'status' => Consent::STATUS_PENDING,
    ]))->toThrow(ValidationException::class, 'CONSENT_STATE_INVALID');
});
