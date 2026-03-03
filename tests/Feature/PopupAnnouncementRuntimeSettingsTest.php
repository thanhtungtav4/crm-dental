<?php

use App\Models\ClinicSetting;
use App\Support\ClinicRuntimeSettings;

it('uses default popup runtime settings when custom settings are absent', function (): void {
    expect(ClinicRuntimeSettings::popupAnnouncementsEnabled())->toBeFalse()
        ->and(ClinicRuntimeSettings::popupAnnouncementsPollingSeconds())->toBe(10)
        ->and(ClinicRuntimeSettings::popupAnnouncementRetentionDays())->toBe(180)
        ->and(ClinicRuntimeSettings::popupAnnouncementSenderRoles())->toBe(['Admin', 'Manager']);
});

it('reads popup runtime settings from clinic settings and normalizes them', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);
    ClinicSetting::setValue('popup.polling_seconds', 2, [
        'group' => 'popup',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('popup.retention_days', 365, [
        'group' => 'popup',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('popup.sender_roles', ['Manager', 'Admin', 'Manager'], [
        'group' => 'popup',
        'value_type' => 'json',
    ]);

    expect(ClinicRuntimeSettings::popupAnnouncementsEnabled())->toBeTrue()
        ->and(ClinicRuntimeSettings::popupAnnouncementsPollingSeconds())->toBe(5)
        ->and(ClinicRuntimeSettings::popupAnnouncementRetentionDays())->toBe(365)
        ->and(ClinicRuntimeSettings::popupAnnouncementSenderRoles())->toBe(['Manager', 'Admin']);
});
