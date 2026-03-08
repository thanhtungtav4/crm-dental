<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Models\Patient;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;
use App\Services\IntegrationSecretRotationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class IntegrationScenarioSeeder extends Seeder
{
    public const OLD_WEB_LEAD_REQUEST_ID = 'demo-int-weblead-old-created';

    public const FRESH_WEB_LEAD_REQUEST_ID = 'demo-int-weblead-fresh-created';

    public const OLD_WEBHOOK_FINGERPRINT = 'demo-int-webhook-old';

    public const FRESH_WEBHOOK_FINGERPRINT = 'demo-int-webhook-fresh';

    public const OLD_EMR_EVENT_KEY = 'demo-int-emr-old';

    public const FRESH_EMR_EVENT_KEY = 'demo-int-emr-fresh';

    public const OLD_GCAL_EVENT_KEY = 'demo-int-gcal-old';

    public const FRESH_GCAL_EVENT_KEY = 'demo-int-gcal-fresh';

    public const EXPIRED_WEB_LEAD_PREVIOUS_TOKEN = 'demo-int-expired-web-token-old';

    public const ACTIVE_WEB_LEAD_TOKEN = 'demo-int-expired-web-token-new';

    public function run(): void
    {
        $branch = Branch::query()->where('code', 'HCM-Q1')->first();

        if (! $branch instanceof Branch) {
            return;
        }

        $this->configureRetentionSettings();
        $this->seedExpiredSecretRotationScenario();

        $patient = Patient::query()
            ->where('first_branch_id', $branch->id)
            ->orderBy('id')
            ->first();

        if (! $patient instanceof Patient) {
            return;
        }

        $customer = Customer::query()->find($patient->customer_id);

        if (! $customer instanceof Customer) {
            return;
        }

        $appointment = Appointment::query()
            ->where('patient_id', $patient->id)
            ->orderBy('id')
            ->first();

        if (! $appointment instanceof Appointment) {
            return;
        }

        $this->seedWebLeadScenarios($branch, $customer);
        $this->seedWebhookScenarios();
        $this->seedEmrScenarios($branch, $patient);
        $this->seedGoogleCalendarScenarios($branch, $appointment);
    }

    protected function configureRetentionSettings(): void
    {
        ClinicSetting::setValue('web_lead.retention_days', 30, [
            'group' => 'web_lead',
            'label' => 'Giữ log web lead ingestion (ngày)',
            'value_type' => 'integer',
            'is_active' => true,
        ]);

        ClinicSetting::setValue('zalo.webhook_retention_days', 30, [
            'group' => 'zalo',
            'label' => 'Giữ webhook Zalo (ngày)',
            'value_type' => 'integer',
            'is_active' => true,
        ]);

        ClinicSetting::setValue('emr.retention_days', 30, [
            'group' => 'emr',
            'label' => 'Giữ dữ liệu vận hành EMR (ngày)',
            'value_type' => 'integer',
            'is_active' => true,
        ]);

        ClinicSetting::setValue('google_calendar.retention_days', 30, [
            'group' => 'google_calendar',
            'label' => 'Giữ dữ liệu vận hành Google Calendar (ngày)',
            'value_type' => 'integer',
            'is_active' => true,
        ]);
    }

    protected function seedExpiredSecretRotationScenario(): void
    {
        ClinicSetting::setValue('web_lead.api_token', self::EXPIRED_WEB_LEAD_PREVIOUS_TOKEN, [
            'group' => 'web_lead',
            'label' => 'API token web lead',
            'value_type' => 'text',
            'is_secret' => true,
            'is_active' => true,
        ]);

        ClinicSetting::setValue('web_lead.api_token_grace_minutes', 5, [
            'group' => 'web_lead',
            'label' => 'Grace window API token cũ (phút)',
            'value_type' => 'integer',
            'is_active' => true,
        ]);

        app(IntegrationSecretRotationService::class)->rotate(
            settingKey: 'web_lead.api_token',
            newSecret: self::ACTIVE_WEB_LEAD_TOKEN,
            reason: 'Local integration scenario seed.',
        );

        ClinicSetting::setValue('web_lead.api_token_grace_expires_at', now()->subMinute()->toISOString(), [
            'group' => 'web_lead',
            'label' => 'Web lead API token grace expiry',
            'value_type' => 'text',
            'is_active' => true,
        ]);
    }

    protected function seedWebLeadScenarios(Branch $branch, Customer $customer): void
    {
        $oldRecord = WebLeadIngestion::query()->updateOrCreate(
            ['request_id' => self::OLD_WEB_LEAD_REQUEST_ID],
            [
                'source' => 'website',
                'full_name' => 'Lead retention old',
                'phone' => '0909444001',
                'phone_normalized' => '84909444001',
                'branch_code' => $branch->code,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'status' => WebLeadIngestion::STATUS_CREATED,
                'payload' => ['scenario' => 'old_web_lead'],
                'response' => ['customer_id' => $customer->id],
                'processed_at' => now()->subDays(45),
                'received_at' => now()->subDays(45),
            ],
        );
        $this->ageModel($oldRecord, 45);

        $freshRecord = WebLeadIngestion::query()->updateOrCreate(
            ['request_id' => self::FRESH_WEB_LEAD_REQUEST_ID],
            [
                'source' => 'website',
                'full_name' => 'Lead retention fresh',
                'phone' => '0909444002',
                'phone_normalized' => '84909444002',
                'branch_code' => $branch->code,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'status' => WebLeadIngestion::STATUS_CREATED,
                'payload' => ['scenario' => 'fresh_web_lead'],
                'response' => ['customer_id' => $customer->id],
                'processed_at' => now()->subDays(5),
                'received_at' => now()->subDays(5),
            ],
        );
        $this->ageModel($freshRecord, 5);
    }

    protected function seedWebhookScenarios(): void
    {
        $oldRecord = ZaloWebhookEvent::query()->updateOrCreate(
            ['event_fingerprint' => self::OLD_WEBHOOK_FINGERPRINT],
            [
                'event_name' => 'user_send_text',
                'event_id' => 'demo-int-webhook-old-id',
                'oa_id' => 'demo-int-oa',
                'payload' => ['scenario' => 'old_webhook'],
                'received_at' => now()->subDays(45),
                'processed_at' => now()->subDays(45),
            ],
        );
        $this->ageModel($oldRecord, 45);

        $freshRecord = ZaloWebhookEvent::query()->updateOrCreate(
            ['event_fingerprint' => self::FRESH_WEBHOOK_FINGERPRINT],
            [
                'event_name' => 'user_send_text',
                'event_id' => 'demo-int-webhook-fresh-id',
                'oa_id' => 'demo-int-oa',
                'payload' => ['scenario' => 'fresh_webhook'],
                'received_at' => now()->subDays(5),
                'processed_at' => now()->subDays(5),
            ],
        );
        $this->ageModel($freshRecord, 5);
    }

    protected function seedEmrScenarios(Branch $branch, Patient $patient): void
    {
        $oldEvent = EmrSyncEvent::query()->updateOrCreate(
            ['event_key' => self::OLD_EMR_EVENT_KEY],
            [
                'patient_id' => $patient->id,
                'branch_id' => $branch->id,
                'event_type' => 'manual.sync',
                'payload' => ['patient_id' => $patient->id, 'scenario' => 'old_emr_event'],
                'payload_checksum' => hash('sha256', self::OLD_EMR_EVENT_KEY),
                'status' => EmrSyncEvent::STATUS_SYNCED,
                'processed_at' => now()->subDays(45),
                'attempts' => 1,
                'max_attempts' => 5,
                'last_http_status' => 200,
                'external_patient_id' => 'EMR-DEMO-OLD',
            ],
        );
        $this->ageModel($oldEvent, 45);
        EmrSyncLog::query()->where('emr_sync_event_id', $oldEvent->id)->delete();
        $oldLog = EmrSyncLog::query()->create([
            'emr_sync_event_id' => $oldEvent->id,
            'attempt' => 1,
            'status' => EmrSyncEvent::STATUS_SYNCED,
            'http_status' => 200,
            'request_payload' => ['event_key' => self::OLD_EMR_EVENT_KEY],
            'response_payload' => ['message' => 'ok'],
            'attempted_at' => now()->subDays(45),
        ]);
        $this->ageModel($oldLog, 45);

        $freshEvent = EmrSyncEvent::query()->updateOrCreate(
            ['event_key' => self::FRESH_EMR_EVENT_KEY],
            [
                'patient_id' => $patient->id,
                'branch_id' => $branch->id,
                'event_type' => 'manual.sync',
                'payload' => ['patient_id' => $patient->id, 'scenario' => 'fresh_emr_event'],
                'payload_checksum' => hash('sha256', self::FRESH_EMR_EVENT_KEY),
                'status' => EmrSyncEvent::STATUS_SYNCED,
                'processed_at' => now()->subDays(5),
                'attempts' => 1,
                'max_attempts' => 5,
                'last_http_status' => 200,
                'external_patient_id' => 'EMR-DEMO-FRESH',
            ],
        );
        $this->ageModel($freshEvent, 5);
        EmrSyncLog::query()->where('emr_sync_event_id', $freshEvent->id)->delete();
        $freshLog = EmrSyncLog::query()->create([
            'emr_sync_event_id' => $freshEvent->id,
            'attempt' => 1,
            'status' => EmrSyncEvent::STATUS_SYNCED,
            'http_status' => 200,
            'request_payload' => ['event_key' => self::FRESH_EMR_EVENT_KEY],
            'response_payload' => ['message' => 'ok'],
            'attempted_at' => now()->subDays(5),
        ]);
        $this->ageModel($freshLog, 5);
    }

    protected function seedGoogleCalendarScenarios(Branch $branch, Appointment $appointment): void
    {
        $oldEvent = GoogleCalendarSyncEvent::query()->updateOrCreate(
            ['event_key' => self::OLD_GCAL_EVENT_KEY],
            [
                'appointment_id' => $appointment->id,
                'branch_id' => $branch->id,
                'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
                'payload' => ['appointment_id' => $appointment->id, 'scenario' => 'old_gcal_event'],
                'payload_checksum' => hash('sha256', self::OLD_GCAL_EVENT_KEY),
                'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
                'processed_at' => now()->subDays(45),
                'attempts' => 1,
                'max_attempts' => 5,
                'last_http_status' => 200,
                'external_event_id' => 'GCAL-DEMO-OLD',
            ],
        );
        $this->ageModel($oldEvent, 45);
        GoogleCalendarSyncLog::query()->where('google_calendar_sync_event_id', $oldEvent->id)->delete();
        $oldLog = GoogleCalendarSyncLog::query()->create([
            'google_calendar_sync_event_id' => $oldEvent->id,
            'attempt' => 1,
            'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
            'http_status' => 200,
            'request_payload' => ['event_key' => self::OLD_GCAL_EVENT_KEY],
            'response_payload' => ['message' => 'ok'],
            'attempted_at' => now()->subDays(45),
        ]);
        $this->ageModel($oldLog, 45);

        $freshEvent = GoogleCalendarSyncEvent::query()->updateOrCreate(
            ['event_key' => self::FRESH_GCAL_EVENT_KEY],
            [
                'appointment_id' => $appointment->id,
                'branch_id' => $branch->id,
                'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
                'payload' => ['appointment_id' => $appointment->id, 'scenario' => 'fresh_gcal_event'],
                'payload_checksum' => hash('sha256', self::FRESH_GCAL_EVENT_KEY),
                'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
                'processed_at' => now()->subDays(5),
                'attempts' => 1,
                'max_attempts' => 5,
                'last_http_status' => 200,
                'external_event_id' => 'GCAL-DEMO-FRESH',
            ],
        );
        $this->ageModel($freshEvent, 5);
        GoogleCalendarSyncLog::query()->where('google_calendar_sync_event_id', $freshEvent->id)->delete();
        $freshLog = GoogleCalendarSyncLog::query()->create([
            'google_calendar_sync_event_id' => $freshEvent->id,
            'attempt' => 1,
            'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
            'http_status' => 200,
            'request_payload' => ['event_key' => self::FRESH_GCAL_EVENT_KEY],
            'response_payload' => ['message' => 'ok'],
            'attempted_at' => now()->subDays(5),
        ]);
        $this->ageModel($freshLog, 5);
    }

    protected function ageModel(Model $model, int $days): void
    {
        $timestamp = now()->subDays($days);

        $model->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
    }
}
