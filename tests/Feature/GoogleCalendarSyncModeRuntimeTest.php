<?php

use App\Models\ClinicSetting;
use App\Support\ClinicRuntimeSettings;

it('normalizes legacy google calendar sync modes to the supported mode', function (string $storedMode): void {
    ClinicSetting::setValue('google_calendar.sync_mode', $storedMode, [
        'group' => 'google_calendar',
        'label' => 'Chế độ đồng bộ',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    expect(ClinicRuntimeSettings::googleCalendarSyncMode())->toBe('one_way_to_google')
        ->and(ClinicRuntimeSettings::googleCalendarAllowsPushToGoogle())->toBeTrue()
        ->and(ClinicRuntimeSettings::googleCalendarAllowsPullFromGoogle())->toBeFalse();
})->with([
    'legacy two_way' => 'two_way',
    'legacy one_way_to_crm' => 'one_way_to_crm',
    'supported one_way_to_google' => 'one_way_to_google',
    'unknown mode' => 'legacy_mode',
]);
