<?php

use App\Models\ClinicSettingLog;
use App\Models\User;
use App\Services\IntegrationSettingsAuditReadModelService;

it('builds rendered recent logs payload for integration settings audit surfaces', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Reader',
    ]);
    $admin->assignRole('Admin');

    ClinicSettingLog::query()->create([
        'setting_group' => 'web_lead',
        'setting_key' => 'web_lead.api_token',
        'setting_label' => 'Web Lead API Token',
        'old_value' => 'old-token',
        'new_value' => 'new-token',
        'change_reason' => 'Reader test',
        'context' => ['grace_expires_at' => now()->addMinutes(15)->toISOString()],
        'is_secret' => true,
        'changed_by' => $admin->id,
        'changed_at' => now(),
    ]);

    $payload = app(IntegrationSettingsAuditReadModelService::class)->renderedRecentLogs();

    expect($payload)->toHaveCount(1)
        ->and($payload->first())->toMatchArray([
            'changed_by_name' => 'Admin Reader',
            'setting_label' => 'Web Lead API Token',
            'setting_key' => 'web_lead.api_token',
            'change_reason' => 'Reader test',
            'old_value' => 'old-token',
            'new_value' => 'new-token',
        ])
        ->and($payload->first()['changed_at_label'])->not->toBeEmpty()
        ->and($payload->first()['grace_expires_at_label'])->not->toBeEmpty();
});
