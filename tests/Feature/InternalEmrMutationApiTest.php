<?php

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\EmrApiMutation;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Support\ActionPermission;

it('rejects emr internal mutation when token is invalid', function () {
    $context = seedInternalEmrApiContext();
    /** @var User $actor */
    $actor = $context['actor'];
    $note = $context['note'];
    configureInternalEmrApiRuntime($actor, 'expected-token');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer invalid-token',
        'X-Idempotency-Key' => 'emr-api-invalid-1',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 1,
        'general_exam_notes' => 'Updated from API',
    ]);

    $response->assertUnauthorized();
});

it('replays idempotent mutation for clinical note amend endpoint', function () {
    $context = seedInternalEmrApiContext();
    /** @var User $actor */
    $actor = $context['actor'];
    /** @var ClinicalNote $note */
    $note = $context['note'];
    $token = 'internal-emr-token-1';

    configureInternalEmrApiRuntime($actor, $token);

    $payload = [
        'expected_version' => 1,
        'general_exam_notes' => 'API cập nhật khám tổng quát.',
        'reason' => 'integration_sync',
    ];

    $first = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'X-Idempotency-Key' => 'emr-api-idem-1',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), $payload);

    $first->assertOk()
        ->assertJsonPath('data.replayed', false)
        ->assertJsonPath('data.clinical_note.lock_version', 2);

    $second = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'X-Idempotency-Key' => 'emr-api-idem-1',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), $payload);

    $second->assertOk()
        ->assertJsonPath('data.replayed', true)
        ->assertJsonPath('data.clinical_note.lock_version', 2);

    $note->refresh();

    expect((int) $note->lock_version)->toBe(2)
        ->and((string) $note->general_exam_notes)->toBe('API cập nhật khám tổng quát.')
        ->and(EmrApiMutation::query()->where('request_id', 'emr-api-idem-1')->count())->toBe(1);
});

it('returns idempotency conflict when same key is reused with different payload', function () {
    $context = seedInternalEmrApiContext();
    /** @var User $actor */
    $actor = $context['actor'];
    /** @var ClinicalNote $note */
    $note = $context['note'];
    $token = 'internal-emr-token-2';

    configureInternalEmrApiRuntime($actor, $token);

    $headers = [
        'Authorization' => 'Bearer '.$token,
        'X-Idempotency-Key' => 'emr-api-conflict-1',
    ];

    $this->withHeaders($headers)->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 1,
        'general_exam_notes' => 'Payload A',
    ])->assertOk();

    $this->withHeaders($headers)->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 1,
        'general_exam_notes' => 'Payload B',
    ])->assertStatus(409);
});

it('returns validation error when expected version is stale', function () {
    $context = seedInternalEmrApiContext();
    /** @var User $actor */
    $actor = $context['actor'];
    /** @var ClinicalNote $note */
    $note = $context['note'];
    $token = 'internal-emr-token-3';

    configureInternalEmrApiRuntime($actor, $token);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'X-Idempotency-Key' => 'emr-api-stale-1',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 1,
        'general_exam_notes' => 'Phiên bản hợp lệ',
    ])->assertOk();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'X-Idempotency-Key' => 'emr-api-stale-2',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 1,
        'general_exam_notes' => 'Phiên bản stale',
    ])->assertUnprocessable();
});

/**
 * @return array{actor: User, note: ClinicalNote}
 */
function seedInternalEmrApiContext(): array
{
    $branch = Branch::factory()->create();
    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $actor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_SCHEDULED,
        'scheduled_at' => '2026-03-13 09:00:00',
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $actor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-13',
        'general_exam_notes' => 'Khám ban đầu',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    return [
        'actor' => $actor,
        'note' => $note,
    ];
}

function configureInternalEmrApiRuntime(User $actor, string $token): void
{
    $actor->assignRole('AutomationService');
    $actor->givePermissionTo(ActionPermission::EMR_CLINICAL_WRITE);

    ClinicSetting::setValue('emr.enabled', true, [
        'group' => 'emr',
        'label' => 'Bật EMR',
        'value_type' => 'boolean',
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.api_key', $token, [
        'group' => 'emr',
        'label' => 'EMR API Key',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('scheduler.automation_actor_user_id', $actor->id, [
        'group' => 'scheduler',
        'label' => 'Automation actor',
        'value_type' => 'number',
        'is_active' => true,
    ]);

    ClinicSetting::setValue('scheduler.automation_actor_required_role', 'AutomationService', [
        'group' => 'scheduler',
        'label' => 'Automation required role',
        'value_type' => 'text',
        'is_active' => true,
    ]);
}
