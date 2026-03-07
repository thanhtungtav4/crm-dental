<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;

it('prunes expired integration operational data by retention settings', function (): void {
    [$branch, $patient, $appointment] = seedIntegrationOperationalEntities();
    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    setIntegrationRetentionDays(30);

    $oldWebLead = WebLeadIngestion::factory()->create([
        'status' => WebLeadIngestion::STATUS_CREATED,
        'branch_id' => $branch->id,
        'payload' => ['full_name' => 'Old lead'],
        'response' => ['status' => WebLeadIngestion::STATUS_CREATED],
        'processed_at' => now()->subDays(45),
    ]);
    makeIntegrationRecordOld($oldWebLead, 45);

    $freshWebLead = WebLeadIngestion::factory()->create([
        'status' => WebLeadIngestion::STATUS_CREATED,
        'branch_id' => $branch->id,
        'payload' => ['full_name' => 'Fresh lead'],
        'response' => ['status' => WebLeadIngestion::STATUS_CREATED],
        'processed_at' => now()->subDays(5),
    ]);

    $oldWebhook = ZaloWebhookEvent::query()->create([
        'event_fingerprint' => 'old-webhook-fingerprint',
        'event_name' => 'user_send_text',
        'payload' => ['event_name' => 'user_send_text'],
        'received_at' => now()->subDays(45),
        'processed_at' => now()->subDays(45),
    ]);
    makeIntegrationRecordOld($oldWebhook, 45);

    $freshWebhook = ZaloWebhookEvent::query()->create([
        'event_fingerprint' => 'fresh-webhook-fingerprint',
        'event_name' => 'user_send_text',
        'payload' => ['event_name' => 'user_send_text'],
        'received_at' => now()->subDays(5),
        'processed_at' => now()->subDays(5),
    ]);

    $oldEmrEvent = EmrSyncEvent::query()->create([
        'event_key' => 'old-emr-event-key',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $patient->id],
        'payload_checksum' => hash('sha256', 'old-emr-event-key'),
        'status' => EmrSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(45),
    ]);
    makeIntegrationRecordOld($oldEmrEvent, 45);

    $oldEmrLog = EmrSyncLog::query()->create([
        'emr_sync_event_id' => $oldEmrEvent->id,
        'attempt' => 1,
        'status' => EmrSyncEvent::STATUS_SYNCED,
        'request_payload' => ['event_key' => 'old-emr-event-key'],
        'response_payload' => ['message' => 'ok'],
        'attempted_at' => now()->subDays(45),
    ]);
    makeIntegrationRecordOld($oldEmrLog, 45);

    $freshEmrEvent = EmrSyncEvent::query()->create([
        'event_key' => 'fresh-emr-event-key',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $patient->id],
        'payload_checksum' => hash('sha256', 'fresh-emr-event-key'),
        'status' => EmrSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(5),
    ]);

    $freshEmrLog = EmrSyncLog::query()->create([
        'emr_sync_event_id' => $freshEmrEvent->id,
        'attempt' => 1,
        'status' => EmrSyncEvent::STATUS_SYNCED,
        'request_payload' => ['event_key' => 'fresh-emr-event-key'],
        'response_payload' => ['message' => 'ok'],
        'attempted_at' => now()->subDays(5),
    ]);

    $oldGoogleEvent = GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'old-google-event-key',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $appointment->id],
        'payload_checksum' => hash('sha256', 'old-google-event-key'),
        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(45),
    ]);
    makeIntegrationRecordOld($oldGoogleEvent, 45);

    $oldGoogleLog = GoogleCalendarSyncLog::query()->create([
        'google_calendar_sync_event_id' => $oldGoogleEvent->id,
        'attempt' => 1,
        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
        'request_payload' => ['event_key' => 'old-google-event-key'],
        'response_payload' => ['message' => 'ok'],
        'attempted_at' => now()->subDays(45),
    ]);
    makeIntegrationRecordOld($oldGoogleLog, 45);

    $freshGoogleEvent = GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'fresh-google-event-key',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $appointment->id],
        'payload_checksum' => hash('sha256', 'fresh-google-event-key'),
        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(5),
    ]);

    $freshGoogleLog = GoogleCalendarSyncLog::query()->create([
        'google_calendar_sync_event_id' => $freshGoogleEvent->id,
        'attempt' => 1,
        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
        'request_payload' => ['event_key' => 'fresh-google-event-key'],
        'response_payload' => ['message' => 'ok'],
        'attempted_at' => now()->subDays(5),
    ]);

    $this->artisan('integrations:prune-operational-data')
        ->expectsOutputToContain('dry_run=no')
        ->assertSuccessful();

    expect(WebLeadIngestion::query()->whereKey($oldWebLead->id)->exists())->toBeFalse()
        ->and(WebLeadIngestion::query()->whereKey($freshWebLead->id)->exists())->toBeTrue()
        ->and(ZaloWebhookEvent::query()->whereKey($oldWebhook->id)->exists())->toBeFalse()
        ->and(ZaloWebhookEvent::query()->whereKey($freshWebhook->id)->exists())->toBeTrue()
        ->and(EmrSyncEvent::query()->whereKey($oldEmrEvent->id)->exists())->toBeFalse()
        ->and(EmrSyncLog::query()->whereKey($oldEmrLog->id)->exists())->toBeFalse()
        ->and(EmrSyncEvent::query()->whereKey($freshEmrEvent->id)->exists())->toBeTrue()
        ->and(EmrSyncLog::query()->whereKey($freshEmrLog->id)->exists())->toBeTrue()
        ->and(GoogleCalendarSyncEvent::query()->whereKey($oldGoogleEvent->id)->exists())->toBeFalse()
        ->and(GoogleCalendarSyncLog::query()->whereKey($oldGoogleLog->id)->exists())->toBeFalse()
        ->and(GoogleCalendarSyncEvent::query()->whereKey($freshGoogleEvent->id)->exists())->toBeTrue()
        ->and(GoogleCalendarSyncLog::query()->whereKey($freshGoogleLog->id)->exists())->toBeTrue();
});

it('supports dry run for integration operational data pruning', function (): void {
    [$branch] = seedIntegrationOperationalEntities();
    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $event = ZaloWebhookEvent::query()->create([
        'event_fingerprint' => 'dry-run-webhook',
        'event_name' => 'user_send_text',
        'payload' => ['event_name' => 'user_send_text'],
        'received_at' => now()->subDays(90),
        'processed_at' => now()->subDays(90),
    ]);
    makeIntegrationRecordOld($event, 90);

    $this->artisan('integrations:prune-operational-data', [
        '--days' => 30,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('dry_run=yes')
        ->assertSuccessful();

    expect(ZaloWebhookEvent::query()->whereKey($event->id)->exists())->toBeTrue();
});

function seedIntegrationOperationalEntities(): array
{
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);
    $appointment = Appointment::factory()->create([
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addDay(),
    ]);

    return [$branch, $patient, $appointment];
}

function setIntegrationRetentionDays(int $days): void
{
    ClinicSetting::setValue('web_lead.retention_days', $days, [
        'group' => 'web_lead',
        'label' => 'Giữ log web lead ingestion (ngày)',
        'value_type' => 'integer',
        'is_active' => true,
    ]);

    ClinicSetting::setValue('zalo.webhook_retention_days', $days, [
        'group' => 'zalo',
        'label' => 'Giữ webhook Zalo (ngày)',
        'value_type' => 'integer',
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.retention_days', $days, [
        'group' => 'emr',
        'label' => 'Giữ dữ liệu vận hành EMR (ngày)',
        'value_type' => 'integer',
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.retention_days', $days, [
        'group' => 'google_calendar',
        'label' => 'Giữ dữ liệu vận hành Google Calendar (ngày)',
        'value_type' => 'integer',
        'is_active' => true,
    ]);
}

function makeIntegrationRecordOld(object $model, int $days): void
{
    $model->forceFill([
        'created_at' => now()->subDays($days),
        'updated_at' => now()->subDays($days),
    ])->saveQuietly();
}
