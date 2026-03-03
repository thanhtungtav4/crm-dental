<?php

use App\Filament\Pages\IntegrationSettings;
use Livewire\Livewire;

it('validates required google calendar fields when integration is enabled', function (): void {
    Livewire::test(IntegrationSettings::class)
        ->set('settings.google_calendar_enabled', true)
        ->set('settings.google_calendar_client_id', '')
        ->set('settings.google_calendar_client_secret', '')
        ->set('settings.google_calendar_refresh_token', '')
        ->set('settings.google_calendar_calendar_id', '')
        ->call('save')
        ->assertHasErrors([
            'settings.google_calendar_client_id',
            'settings.google_calendar_client_secret',
            'settings.google_calendar_refresh_token',
            'settings.google_calendar_calendar_id',
        ]);
});

it('allows saving google calendar settings when required fields are provided', function (): void {
    Livewire::test(IntegrationSettings::class)
        ->set('settings.google_calendar_enabled', true)
        ->set('settings.google_calendar_client_id', 'gcal-client-id')
        ->set('settings.google_calendar_client_secret', 'gcal-client-secret')
        ->set('settings.google_calendar_refresh_token', 'gcal-refresh-token')
        ->set('settings.google_calendar_calendar_id', 'crm-calendar@example.com')
        ->set('settings.google_calendar_sync_mode', 'one_way_to_google')
        ->call('save')
        ->assertHasNoErrors([
            'settings.google_calendar_client_id',
            'settings.google_calendar_client_secret',
            'settings.google_calendar_refresh_token',
            'settings.google_calendar_calendar_id',
        ]);
});
