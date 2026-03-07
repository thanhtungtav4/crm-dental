<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicSetting;
use App\Models\ClinicSettingLog;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\IntegrationSecretRotationService;
use App\Support\ActionPermission;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('keeps previous web lead token valid during the configured grace window after rotation', function (): void {
    $admin = createIntSecretRotationAdmin();
    $branch = Branch::factory()->create([
        'code' => 'BR-INT-ROTATE-WEB',
        'active' => true,
    ]);

    configureIntSecretRotationWebLeadRuntime(
        token: 'web-old-token-rotation',
        defaultBranchCode: $branch->code,
        graceMinutes: 60,
    );

    $component = Livewire::actingAs($admin)
        ->test(IntegrationSettings::class)
        ->set('settings.web_lead_api_token_grace_minutes', 60)
        ->set('settings.web_lead_api_token', 'web-new-token-rotation')
        ->call('save')
        ->assertHasNoErrors();

    $component->assertSee('Grace window đang hoạt động');

    $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Rotation Old Token Lead',
        'phone' => '0901234501',
    ], [
        'Authorization' => 'Bearer web-old-token-rotation',
        'X-Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Rotation New Token Lead',
        'phone' => '0901234502',
    ], [
        'Authorization' => 'Bearer web-new-token-rotation',
        'X-Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $rotationLog = ClinicSettingLog::query()
        ->where('setting_key', 'web_lead.api_token')
        ->latest('id')
        ->first();

    expect($rotationLog)->not->toBeNull()
        ->and((string) $rotationLog?->change_reason)->toBe('Secret rotated from IntegrationSettings.')
        ->and((string) data_get($rotationLog?->context, 'rotation_mode'))->toBe('grace_window_started')
        ->and(data_get($rotationLog?->context, 'grace_expires_at'))->not->toBeNull()
        ->and(app(IntegrationSecretRotationService::class)->activeGraceState('web_lead.api_token'))->not->toBeNull();
});

it('rejects expired grace emr api key while keeping the new token active', function (): void {
    $context = seedIntSecretRotationEmrContext();
    /** @var User $actor */
    $actor = $context['actor'];
    /** @var ClinicalNote $note */
    $note = $context['note'];
    $admin = createIntSecretRotationAdmin();

    configureIntSecretRotationEmrRuntime(
        actor: $actor,
        token: 'emr-old-token-rotation',
        graceMinutes: 5,
    );

    Livewire::actingAs($admin)
        ->test(IntegrationSettings::class)
        ->set('settings.emr_api_key_grace_minutes', 5)
        ->set('settings.emr_api_key', 'emr-new-token-rotation')
        ->call('save')
        ->assertHasNoErrors();

    $this->withHeaders([
        'Authorization' => 'Bearer emr-old-token-rotation',
        'X-Idempotency-Key' => 'int-rotation-emr-old-1',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 1,
        'general_exam_notes' => 'Grace token vẫn còn hiệu lực.',
    ])->assertOk();

    ClinicSetting::setValue('emr.api_key_grace_expires_at', now()->subMinute()->toISOString(), [
        'group' => 'emr',
        'value_type' => 'text',
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer emr-old-token-rotation',
        'X-Idempotency-Key' => 'int-rotation-emr-old-2',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 2,
        'general_exam_notes' => 'Token cũ đã hết hạn.',
    ])->assertUnauthorized();

    $this->withHeaders([
        'Authorization' => 'Bearer emr-new-token-rotation',
        'X-Idempotency-Key' => 'int-rotation-emr-new-1',
    ])->postJson(route('api.v1.emr.internal.clinical-notes.amend', [
        'clinicalNote' => $note->id,
    ]), [
        'expected_version' => 2,
        'general_exam_notes' => 'Token mới vẫn hoạt động.',
    ])->assertOk();
});

function createIntSecretRotationAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    test()->actingAs($admin);

    return $admin;
}

function configureIntSecretRotationWebLeadRuntime(string $token, string $defaultBranchCode, int $graceMinutes): void
{
    ClinicSetting::setValue('web_lead.enabled', true, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('web_lead.api_token', $token, [
        'group' => 'web_lead',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('web_lead.api_token_grace_minutes', $graceMinutes, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('web_lead.default_branch_code', $defaultBranchCode, [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);
}

/**
 * @return array{actor: User, note: ClinicalNote}
 */
function seedIntSecretRotationEmrContext(): array
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
        'scheduled_at' => '2026-03-21 09:00:00',
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $actor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-21',
        'general_exam_notes' => 'Clinical note before secret rotation',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    return [
        'actor' => $actor,
        'note' => $note,
    ];
}

function configureIntSecretRotationEmrRuntime(User $actor, string $token, int $graceMinutes): void
{
    $actor->assignRole('AutomationService');
    $actor->givePermissionTo(ActionPermission::EMR_CLINICAL_WRITE);

    ClinicSetting::setValue('emr.enabled', true, [
        'group' => 'emr',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('emr.api_key', $token, [
        'group' => 'emr',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('emr.api_key_grace_minutes', $graceMinutes, [
        'group' => 'emr',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('scheduler.automation_actor_user_id', $actor->id, [
        'group' => 'scheduler',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('scheduler.automation_actor_required_role', 'AutomationService', [
        'group' => 'scheduler',
        'value_type' => 'text',
    ]);
}
