<?php

use App\Models\ClinicSetting;
use App\Support\ClinicRuntimeSettings;

it('uses default web lead realtime notification settings when custom settings are absent', function (): void {
    expect(ClinicRuntimeSettings::webLeadRealtimeNotificationEnabled())->toBeFalse()
        ->and(ClinicRuntimeSettings::webLeadRealtimeNotificationRoles())->toBe(['CSKH']);
});

it('reads web lead realtime notification settings from clinic settings', function (): void {
    ClinicSetting::setValue('web_lead.realtime_notification_enabled', true, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);
    ClinicSetting::setValue('web_lead.realtime_notification_roles', ['CSKH', 'Manager', 'CSKH'], [
        'group' => 'web_lead',
        'value_type' => 'json',
    ]);

    expect(ClinicRuntimeSettings::webLeadRealtimeNotificationEnabled())->toBeTrue()
        ->and(ClinicRuntimeSettings::webLeadRealtimeNotificationRoles())->toBe(['CSKH', 'Manager']);
});
