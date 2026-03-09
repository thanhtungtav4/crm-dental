<?php

use App\Models\ClinicSetting;
use App\Models\EmrSyncEvent;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\User;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;
use Database\Seeders\IntegrationScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;

use function Pest\Laravel\seed;

it('creates integration prune scenarios that remove old operational records and keep fresh ones', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->firstOrFail();
    $this->actingAs($admin);

    $this->artisan('integrations:prune-operational-data')
        ->expectsOutputToContain('dry_run=no')
        ->assertSuccessful();

    expect(WebLeadIngestion::query()->where('request_id', IntegrationScenarioSeeder::OLD_WEB_LEAD_REQUEST_ID)->exists())->toBeFalse()
        ->and(WebLeadIngestion::query()->where('request_id', IntegrationScenarioSeeder::FRESH_WEB_LEAD_REQUEST_ID)->exists())->toBeTrue()
        ->and(WebLeadEmailDelivery::query()->where('dedupe_key', IntegrationScenarioSeeder::OLD_WEB_LEAD_EMAIL_DEDUPE_KEY)->exists())->toBeFalse()
        ->and(WebLeadEmailDelivery::query()->where('dedupe_key', IntegrationScenarioSeeder::FRESH_WEB_LEAD_EMAIL_DEDUPE_KEY)->exists())->toBeTrue()
        ->and(WebLeadEmailDelivery::query()->where('dedupe_key', IntegrationScenarioSeeder::RETRYABLE_WEB_LEAD_EMAIL_DEDUPE_KEY)->exists())->toBeTrue()
        ->and(WebLeadEmailDelivery::query()->where('dedupe_key', IntegrationScenarioSeeder::DEAD_WEB_LEAD_EMAIL_DEDUPE_KEY)->exists())->toBeTrue()
        ->and(ZaloWebhookEvent::query()->where('event_fingerprint', IntegrationScenarioSeeder::OLD_WEBHOOK_FINGERPRINT)->exists())->toBeFalse()
        ->and(ZaloWebhookEvent::query()->where('event_fingerprint', IntegrationScenarioSeeder::FRESH_WEBHOOK_FINGERPRINT)->exists())->toBeTrue()
        ->and(EmrSyncEvent::query()->where('event_key', IntegrationScenarioSeeder::OLD_EMR_EVENT_KEY)->exists())->toBeFalse()
        ->and(EmrSyncEvent::query()->where('event_key', IntegrationScenarioSeeder::FRESH_EMR_EVENT_KEY)->exists())->toBeTrue()
        ->and(GoogleCalendarSyncEvent::query()->where('event_key', IntegrationScenarioSeeder::OLD_GCAL_EVENT_KEY)->exists())->toBeFalse()
        ->and(GoogleCalendarSyncEvent::query()->where('event_key', IntegrationScenarioSeeder::FRESH_GCAL_EVENT_KEY)->exists())->toBeTrue();
});

it('creates an expired integration secret rotation scenario that can be dry-run and revoked', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->firstOrFail();
    $this->actingAs($admin);

    expect(ClinicSetting::getValue('web_lead.api_token_previous_secret'))->toBe(
        IntegrationScenarioSeeder::EXPIRED_WEB_LEAD_PREVIOUS_TOKEN,
    )->and(ClinicSetting::getValue('web_lead.api_token'))->toBe(IntegrationScenarioSeeder::ACTIVE_WEB_LEAD_TOKEN);

    $this->artisan('integrations:revoke-rotated-secrets', [
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('dry_run=yes')
        ->assertSuccessful();

    expect(ClinicSetting::getValue('web_lead.api_token_previous_secret'))->toBe(
        IntegrationScenarioSeeder::EXPIRED_WEB_LEAD_PREVIOUS_TOKEN,
    );

    $this->artisan('integrations:revoke-rotated-secrets')
        ->expectsOutputToContain('dry_run=no')
        ->assertSuccessful();

    expect(ClinicSetting::getValue('web_lead.api_token_previous_secret'))->toBeNull()
        ->and(ClinicSetting::getValue('web_lead.api_token'))->toBe(IntegrationScenarioSeeder::ACTIVE_WEB_LEAD_TOKEN);
});
