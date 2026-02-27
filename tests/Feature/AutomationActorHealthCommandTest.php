<?php

use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Support\Str;

function seedSchedulerActorSetting(string $key, mixed $value, string $type = 'text'): void
{
    ClinicSetting::setValue($key, $value, [
        'group' => 'scheduler',
        'label' => $key,
        'value_type' => $type,
        'is_secret' => false,
        'is_active' => true,
        'sort_order' => 900,
    ]);
}

it('fails automation actor health check when actor is not configured', function () {
    seedSchedulerActorSetting('scheduler.automation_actor_user_id', 'invalid', 'text');
    seedSchedulerActorSetting('scheduler.automation_actor_required_role', 'AutomationService', 'text');

    $this->artisan('security:check-automation-actor')
        ->expectsOutputToContain('Automation actor health: FAIL.')
        ->assertExitCode(1);

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_FAIL)
        ->where('metadata->channel', 'automation_actor_health')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
});

it('passes automation actor health check for valid service account', function () {
    $actor = User::factory()->create([
        'email' => 'automation.'.Str::random(10).'@example.test',
    ]);
    $actor->assignRole('AutomationService');

    seedSchedulerActorSetting('scheduler.automation_actor_user_id', $actor->id, 'integer');
    seedSchedulerActorSetting('scheduler.automation_actor_required_role', 'AutomationService', 'text');

    $this->artisan('security:check-automation-actor')
        ->expectsOutputToContain('Automation actor health: OK.')
        ->assertSuccessful();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_RUN)
        ->where('metadata->channel', 'automation_actor_health')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) data_get($audit?->metadata, 'error_count'))->toBe(0);
});

it('fails automation actor health check when actor lacks required role', function () {
    $actor = User::factory()->create([
        'email' => 'ops.'.Str::random(10).'@example.test',
    ]);
    $actor->assignRole('Manager');

    seedSchedulerActorSetting('scheduler.automation_actor_user_id', $actor->id, 'integer');
    seedSchedulerActorSetting('scheduler.automation_actor_required_role', 'AutomationService', 'text');

    $this->artisan('security:check-automation-actor')
        ->expectsOutputToContain('missing_required_role')
        ->assertExitCode(1);
});
