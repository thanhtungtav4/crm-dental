<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Models\ZnsCampaign;
use App\Models\ZnsCampaignDelivery;
use App\Support\PatientIdentityNormalizer;
use Illuminate\Database\Seeder;

class ZnsAutomationScenarioSeeder extends Seeder
{
    public const RECLAIMABLE_EVENT_KEY = 'demo-zns-reclaimable-reminder';

    public const DEAD_OLD_EVENT_KEY = 'demo-zns-dead-old';

    public const SENT_OLD_EVENT_KEY = 'demo-zns-sent-old';

    public const SENT_FRESH_EVENT_KEY = 'demo-zns-sent-fresh';

    public const OLD_CAMPAIGN_NAME = 'Demo ZNS old prune campaign';

    public const FRESH_CAMPAIGN_NAME = 'Demo ZNS fresh keep campaign';

    public const OLD_CAMPAIGN_CODE = 'ZNS-DEMO-OLD-001';

    public const FRESH_CAMPAIGN_CODE = 'ZNS-DEMO-FRESH-001';

    public const OLD_SENT_DELIVERY_KEY = 'demo-zns-old-sent-delivery';

    public const OLD_FAILED_DELIVERY_KEY = 'demo-zns-old-failed-delivery';

    public const FRESH_SENT_DELIVERY_KEY = 'demo-zns-fresh-sent-delivery';

    public function run(): void
    {
        $branch = Branch::query()->where('code', 'HCM-Q1')->first();

        if (! $branch instanceof Branch) {
            return;
        }

        $this->configureRuntimeSettings();

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

        $this->seedAutomationEvents($branch, $customer, $patient, $appointment);
        $this->seedCampaignDeliveries($branch, $customer, $patient);
    }

    protected function configureRuntimeSettings(): void
    {
        ClinicSetting::setValue('zns.enabled', true, [
            'group' => 'zns',
            'label' => 'Bật ZNS',
            'value_type' => 'boolean',
            'is_active' => true,
        ]);

        ClinicSetting::setValue('zns.access_token', 'demo-zns-access-token', [
            'group' => 'zns',
            'label' => 'ZNS access token',
            'value_type' => 'text',
            'is_secret' => true,
            'is_active' => true,
        ]);

        ClinicSetting::setValue('zns.refresh_token', 'demo-zns-refresh-token', [
            'group' => 'zns',
            'label' => 'ZNS refresh token',
            'value_type' => 'text',
            'is_secret' => true,
            'is_active' => true,
        ]);

        ClinicSetting::setValue('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', [
            'group' => 'zns',
            'label' => 'ZNS send endpoint',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        ClinicSetting::setValue('zns.retention_days', 30, [
            'group' => 'zns',
            'label' => 'Giữ dữ liệu vận hành ZNS (ngày)',
            'value_type' => 'integer',
            'is_active' => true,
        ]);
    }

    protected function seedAutomationEvents(
        Branch $branch,
        Customer $customer,
        Patient $patient,
        Appointment $appointment,
    ): void {
        $normalizedPhone = PatientIdentityNormalizer::normalizePhone($patient->phone);
        $phoneSearchHash = ZnsAutomationEvent::phoneSearchHash($patient->phone);

        $reclaimableEvent = ZnsAutomationEvent::query()->updateOrCreate(
            ['event_key' => self::RECLAIMABLE_EVENT_KEY],
            [
                'event_type' => ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER,
                'template_key' => 'appointment',
                'template_id_snapshot' => 'tpl_demo_reminder',
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'phone' => $patient->phone,
                'normalized_phone' => $normalizedPhone,
                'phone_search_hash' => $phoneSearchHash,
                'payload' => [
                    'recipient_name' => $patient->full_name,
                    'appointment_at_display' => '10/03/2026 09:00',
                ],
                'payload_checksum' => hash('sha256', self::RECLAIMABLE_EVENT_KEY),
                'status' => ZnsAutomationEvent::STATUS_PROCESSING,
                'attempts' => 1,
                'max_attempts' => 3,
                'next_retry_at' => now()->addMinutes(15),
                'locked_at' => now()->subMinutes(30),
                'processing_token' => 'seed-processing-token',
                'last_error' => 'Simulated stale worker lock.',
            ],
        );
        ZnsAutomationLog::query()->where('zns_automation_event_id', $reclaimableEvent->id)->delete();

        $deadEvent = ZnsAutomationEvent::query()->updateOrCreate(
            ['event_key' => self::DEAD_OLD_EVENT_KEY],
            [
                'event_type' => ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER,
                'template_key' => 'appointment',
                'template_id_snapshot' => 'tpl_demo_dead_old',
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'phone' => $patient->phone,
                'normalized_phone' => $normalizedPhone,
                'phone_search_hash' => $phoneSearchHash,
                'payload' => ['scenario' => 'dead_old_event'],
                'payload_checksum' => hash('sha256', self::DEAD_OLD_EVENT_KEY),
                'status' => ZnsAutomationEvent::STATUS_DEAD,
                'attempts' => 2,
                'max_attempts' => 2,
                'processed_at' => now()->subDays(45),
                'last_http_status' => 500,
                'last_error' => 'Seeded dead letter for prune scenario.',
            ],
        );
        $this->ageEventAndLog($deadEvent, ZnsAutomationEvent::STATUS_DEAD, 45);

        $sentOldEvent = ZnsAutomationEvent::query()->updateOrCreate(
            ['event_key' => self::SENT_OLD_EVENT_KEY],
            [
                'event_type' => ZnsAutomationEvent::EVENT_LEAD_WELCOME,
                'template_key' => 'lead_welcome',
                'template_id_snapshot' => 'tpl_demo_sent_old',
                'patient_id' => $patient->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'phone' => $patient->phone,
                'normalized_phone' => $normalizedPhone,
                'phone_search_hash' => $phoneSearchHash,
                'payload' => ['scenario' => 'sent_old_event'],
                'payload_checksum' => hash('sha256', self::SENT_OLD_EVENT_KEY),
                'status' => ZnsAutomationEvent::STATUS_SENT,
                'attempts' => 1,
                'max_attempts' => 3,
                'processed_at' => now()->subDays(40),
                'last_http_status' => 200,
                'provider_message_id' => 'zns-old-001',
                'provider_status_code' => 'sent',
                'provider_response' => ['message' => 'Success'],
            ],
        );
        $this->ageEventAndLog($sentOldEvent, ZnsAutomationEvent::STATUS_SENT, 40);

        $sentFreshEvent = ZnsAutomationEvent::query()->updateOrCreate(
            ['event_key' => self::SENT_FRESH_EVENT_KEY],
            [
                'event_type' => ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING,
                'template_key' => 'birthday',
                'template_id_snapshot' => 'tpl_demo_sent_fresh',
                'patient_id' => $patient->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'phone' => $patient->phone,
                'normalized_phone' => $normalizedPhone,
                'phone_search_hash' => $phoneSearchHash,
                'payload' => ['scenario' => 'sent_fresh_event'],
                'payload_checksum' => hash('sha256', self::SENT_FRESH_EVENT_KEY),
                'status' => ZnsAutomationEvent::STATUS_SENT,
                'attempts' => 1,
                'max_attempts' => 3,
                'processed_at' => now()->subDays(5),
                'last_http_status' => 200,
                'provider_message_id' => 'zns-fresh-001',
                'provider_status_code' => 'sent',
                'provider_response' => ['message' => 'Success'],
            ],
        );
        $this->ageEventAndLog($sentFreshEvent, ZnsAutomationEvent::STATUS_SENT, 5);
    }

    protected function seedCampaignDeliveries(Branch $branch, Customer $customer, Patient $patient): void
    {
        $normalizedPhone = PatientIdentityNormalizer::normalizePhone($patient->phone);
        $phoneSearchHash = ZnsCampaignDelivery::phoneSearchHash($patient->phone);

        $oldCampaign = ZnsCampaign::query()->updateOrCreate(
            ['name' => self::OLD_CAMPAIGN_NAME],
            [
                'code' => self::OLD_CAMPAIGN_CODE,
                'branch_id' => $branch->id,
                'status' => ZnsCampaign::STATUS_COMPLETED,
                'template_key' => 'appointment',
                'template_id' => 'tpl_demo_campaign_old',
                'message_payload' => ['scenario' => 'old_campaign'],
            ],
        );

        $freshCampaign = ZnsCampaign::query()->updateOrCreate(
            ['name' => self::FRESH_CAMPAIGN_NAME],
            [
                'code' => self::FRESH_CAMPAIGN_CODE,
                'branch_id' => $branch->id,
                'status' => ZnsCampaign::STATUS_COMPLETED,
                'template_key' => 'appointment',
                'template_id' => 'tpl_demo_campaign_fresh',
                'message_payload' => ['scenario' => 'fresh_campaign'],
            ],
        );

        $oldSentDelivery = ZnsCampaignDelivery::query()->updateOrCreate(
            ['idempotency_key' => hash('sha256', self::OLD_SENT_DELIVERY_KEY)],
            [
                'zns_campaign_id' => $oldCampaign->id,
                'patient_id' => $patient->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'phone' => $patient->phone,
                'normalized_phone' => $normalizedPhone,
                'phone_search_hash' => $phoneSearchHash,
                'status' => ZnsCampaignDelivery::STATUS_SENT,
                'attempt_count' => 1,
                'provider_message_id' => 'delivery-old-sent',
                'provider_status_code' => 'sent',
                'provider_response' => ['message' => 'Success'],
                'sent_at' => now()->subDays(40),
                'payload' => ['scenario' => 'old_sent_delivery'],
                'template_key_snapshot' => 'appointment',
                'template_id_snapshot' => 'tpl_demo_campaign_old',
            ],
        );
        $this->ageDelivery($oldSentDelivery, 40);

        $oldFailedDelivery = ZnsCampaignDelivery::query()->updateOrCreate(
            ['idempotency_key' => hash('sha256', self::OLD_FAILED_DELIVERY_KEY)],
            [
                'zns_campaign_id' => $oldCampaign->id,
                'patient_id' => $patient->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'phone' => $patient->phone,
                'normalized_phone' => $normalizedPhone,
                'phone_search_hash' => $phoneSearchHash,
                'status' => ZnsCampaignDelivery::STATUS_FAILED,
                'attempt_count' => 2,
                'provider_status_code' => 'failed',
                'error_message' => 'Seeded failed final delivery.',
                'next_retry_at' => null,
                'payload' => ['scenario' => 'old_failed_delivery'],
                'template_key_snapshot' => 'appointment',
                'template_id_snapshot' => 'tpl_demo_campaign_old',
            ],
        );
        $this->ageDelivery($oldFailedDelivery, 40);

        $freshDelivery = ZnsCampaignDelivery::query()->updateOrCreate(
            ['idempotency_key' => hash('sha256', self::FRESH_SENT_DELIVERY_KEY)],
            [
                'zns_campaign_id' => $freshCampaign->id,
                'patient_id' => $patient->id,
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'phone' => $patient->phone,
                'normalized_phone' => $normalizedPhone,
                'phone_search_hash' => $phoneSearchHash,
                'status' => ZnsCampaignDelivery::STATUS_SENT,
                'attempt_count' => 1,
                'provider_message_id' => 'delivery-fresh-sent',
                'provider_status_code' => 'sent',
                'provider_response' => ['message' => 'Success'],
                'sent_at' => now()->subDays(5),
                'payload' => ['scenario' => 'fresh_sent_delivery'],
                'template_key_snapshot' => 'appointment',
                'template_id_snapshot' => 'tpl_demo_campaign_fresh',
            ],
        );
        $this->ageDelivery($freshDelivery, 5);
    }

    protected function ageEventAndLog(ZnsAutomationEvent $event, string $status, int $days): void
    {
        $timestamp = now()->subDays($days);

        $event->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();

        ZnsAutomationLog::query()->where('zns_automation_event_id', $event->id)->delete();

        $log = ZnsAutomationLog::query()->create([
            'zns_automation_event_id' => $event->id,
            'attempt' => max(1, (int) $event->attempts),
            'status' => $status,
            'http_status' => $event->last_http_status,
            'request_payload' => ['event_key' => $event->event_key],
            'response_payload' => ['status' => $status],
            'error_message' => $event->last_error,
            'attempted_at' => $timestamp,
        ]);

        $log->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
    }

    protected function ageDelivery(ZnsCampaignDelivery $delivery, int $days): void
    {
        $timestamp = now()->subDays($days);

        $delivery->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
    }
}
