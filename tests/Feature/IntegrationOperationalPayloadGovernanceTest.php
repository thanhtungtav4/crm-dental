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
use App\Models\PatientMedicalRecord;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;
use App\Services\EmrSyncEventPublisher;
use App\Services\GoogleCalendarSyncEventPublisher;
use Illuminate\Support\Facades\Http;

it('encrypts integration operational payload columns at rest', function (): void {
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

    $webLead = WebLeadIngestion::factory()->create([
        'payload' => [
            'full_name' => 'Nguyen Payload',
            'phone' => '0901234567',
        ],
        'response' => [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ],
    ]);

    $emrEvent = EmrSyncEvent::query()->create([
        'event_key' => 'emr-payload-governance-001',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => [
            'patient' => [
                'full_name' => 'Nguyen Payload',
                'phone' => '0901234567',
            ],
        ],
        'payload_checksum' => hash('sha256', 'emr-payload-governance-001'),
        'status' => EmrSyncEvent::STATUS_PENDING,
    ]);

    $emrLog = EmrSyncLog::query()->create([
        'emr_sync_event_id' => $emrEvent->id,
        'attempt' => 1,
        'status' => EmrSyncEvent::STATUS_FAILED,
        'request_payload' => ['patient' => ['full_name' => 'Nguyen Payload']],
        'response_payload' => ['message' => 'remote error'],
        'attempted_at' => now(),
    ]);

    $googleEvent = GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'gcal-payload-governance-001',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => [
            'summary' => 'Lịch hẹn Nguyen Payload',
            'description' => 'Bệnh nhân: Nguyen Payload',
        ],
        'payload_checksum' => hash('sha256', 'gcal-payload-governance-001'),
        'status' => GoogleCalendarSyncEvent::STATUS_PENDING,
    ]);

    $googleLog = GoogleCalendarSyncLog::query()->create([
        'google_calendar_sync_event_id' => $googleEvent->id,
        'attempt' => 1,
        'status' => GoogleCalendarSyncEvent::STATUS_FAILED,
        'request_payload' => ['summary' => 'Lịch hẹn Nguyen Payload'],
        'response_payload' => ['message' => 'remote error'],
        'attempted_at' => now(),
    ]);

    expect($webLead->payload)->toBeArray()
        ->and($webLead->response)->toBeArray()
        ->and($webLead->getRawOriginal('payload'))->not->toBe(json_encode([
            'full_name' => 'Nguyen Payload',
            'phone' => '0901234567',
        ]))
        ->and($emrEvent->payload)->toBeArray()
        ->and($emrEvent->getRawOriginal('payload'))->not->toBe(json_encode([
            'patient' => ['full_name' => 'Nguyen Payload', 'phone' => '0901234567'],
        ]))
        ->and($emrLog->request_payload)->toBeArray()
        ->and($emrLog->getRawOriginal('request_payload'))->not->toBe(json_encode([
            'patient' => ['full_name' => 'Nguyen Payload'],
        ]))
        ->and($googleEvent->payload)->toBeArray()
        ->and($googleEvent->getRawOriginal('payload'))->not->toBe(json_encode([
            'summary' => 'Lịch hẹn Nguyen Payload',
            'description' => 'Bệnh nhân: Nguyen Payload',
        ]))
        ->and($googleLog->response_payload)->toBeArray()
        ->and($googleLog->getRawOriginal('response_payload'))->not->toBe(json_encode([
            'message' => 'remote error',
        ]));
});

it('stores sanitized encrypted webhook payload summaries', function (): void {
    configureIntPayloadGovernanceZaloWebhookRuntime();

    $payload = [
        'event_name' => 'user_send_text',
        'event_id' => 'zalo-webhook-governance-001',
        'oa_id' => 'oa-governance',
        'timestamp' => '1710000000',
        'sender' => ['id' => 'user-001'],
        'message' => [
            'msg_id' => 'msg-governance-001',
            'text' => 'Nội dung nhạy cảm không nên lưu raw',
            'attachments' => [
                ['type' => 'image'],
            ],
        ],
    ];

    $this->withHeaders([
        'X-Zalo-Signature' => signIntPayloadGovernanceZaloWebhookPayload($payload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $payload)
        ->assertSuccessful();

    $event = ZaloWebhookEvent::query()->firstOrFail();

    expect($event->payload)->toMatchArray([
        'event_name' => 'user_send_text',
        'event_id' => 'zalo-webhook-governance-001',
        'message_id' => 'msg-governance-001',
        'message_text_present' => true,
    ])
        ->and($event->payload)->not->toHaveKey('message')
        ->and($event->payload)->not->toHaveKey('text')
        ->and($event->getRawOriginal('payload'))->not->toBe(json_encode($payload));
});

it('stores sanitized encrypted emr and google sync log summaries', function (): void {
    [$patient] = seedIntPayloadGovernanceEmrPatientAggregate();
    configureIntPayloadGovernanceEmrRuntime();

    app(EmrSyncEventPublisher::class)->publishForPatient($patient, 'manual.sync');

    Http::fake([
        'https://emr.example.test/api/emr/patients/sync' => Http::response([
            'external_patient_id' => 'EMR-PAYLOAD-0001',
            'message' => 'ok',
        ], 200),
    ]);

    $this->artisan('emr:sync-events')->assertSuccessful();

    $emrLog = EmrSyncLog::query()->latest('id')->firstOrFail();

    expect($emrLog->request_payload)->toHaveKeys([
        'event_key',
        'event_type',
        'patient_id',
        'payload_checksum',
    ])
        ->and($emrLog->request_payload)->not->toHaveKey('patient')
        ->and($emrLog->response_payload)->toMatchArray([
            'message' => 'ok',
            'external_patient_id' => 'EMR-PAYLOAD-0001',
        ])
        ->and($emrLog->getRawOriginal('request_payload'))->not->toBe(json_encode($emrLog->request_payload));

    configureIntPayloadGovernanceGoogleCalendarRuntime();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addDay(),
        'duration_minutes' => 45,
    ]);

    app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'gcal-int-governance',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
            'id' => 'gcal-int-payload-0001',
            'updated' => '2026-03-07T10:00:00Z',
        ], 200),
    ]);

    $this->artisan('google-calendar:sync-events')->assertSuccessful();

    $googleLog = GoogleCalendarSyncLog::query()->latest('id')->firstOrFail();

    expect($googleLog->request_payload)->toHaveKeys([
        'event_key',
        'event_type',
        'appointment_id',
        'payload_checksum',
    ])
        ->and($googleLog->request_payload)->toHaveKey('summary_present')
        ->and($googleLog->request_payload)->not->toHaveKey('summary')
        ->and($googleLog->response_payload)->toMatchArray([
            'id' => 'gcal-int-payload-0001',
            'updated' => '2026-03-07T10:00:00Z',
        ])
        ->and($googleLog->getRawOriginal('request_payload'))->not->toBe(json_encode($googleLog->request_payload));
});

function configureIntPayloadGovernanceZaloWebhookRuntime(): void
{
    ClinicSetting::setValue('zalo.enabled', true, [
        'group' => 'zalo',
        'label' => 'Bật tích hợp Zalo OA',
        'value_type' => 'boolean',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('zalo.app_secret', 'app_secret_for_webhook_signature_001', [
        'group' => 'zalo',
        'label' => 'App Secret',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('zalo.webhook_token', 'verify_token_for_payload_governance', [
        'group' => 'zalo',
        'label' => 'Webhook Verify Token',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function signIntPayloadGovernanceZaloWebhookPayload(array $payload): string
{
    $normalize = function (mixed $value) use (&$normalize): mixed {
        if (! is_array($value)) {
            return $value;
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if (! $isAssoc) {
            return array_map($normalize, $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $normalize($item);
        }

        return $value;
    };

    $payloadJson = json_encode($normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (! is_string($payloadJson) || trim($payloadJson) === '') {
        $payloadJson = '{}';
    }

    return hash_hmac('sha256', $payloadJson, 'app_secret_for_webhook_signature_001');
}

function seedIntPayloadGovernanceEmrPatientAggregate(): array
{
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'primary_doctor_id' => $doctor->id,
        'owner_staff_id' => $doctor->id,
    ]);

    PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'allergies' => ['Lidocaine'],
        'chronic_diseases' => ['Tiểu đường'],
        'updated_by' => $doctor->id,
    ]);

    $treatmentPlan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
    ]);

    PlanItem::factory()->create([
        'treatment_plan_id' => $treatmentPlan->id,
        'name' => 'Cấy Implant',
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    $prescription = Prescription::factory()
        ->forPatient($patient)
        ->byDoctor($doctor)
        ->create();

    PrescriptionItem::factory()
        ->forPrescription($prescription)
        ->antibiotic()
        ->create();

    return [$patient->fresh(), $doctor];
}

function configureIntPayloadGovernanceEmrRuntime(): void
{
    ClinicSetting::setValue('emr.enabled', true, [
        'group' => 'emr',
        'label' => 'Bật EMR',
        'value_type' => 'boolean',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.provider', 'external', [
        'group' => 'emr',
        'label' => 'Nhà cung cấp EMR',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.base_url', 'https://emr.example.test', [
        'group' => 'emr',
        'label' => 'EMR Base URL',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.api_key', 'test-api-key', [
        'group' => 'emr',
        'label' => 'EMR API Key',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.clinic_code', 'CLINIC-HQ', [
        'group' => 'emr',
        'label' => 'Mã cơ sở',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);
}

function configureIntPayloadGovernanceGoogleCalendarRuntime(): void
{
    ClinicSetting::setValue('google_calendar.enabled', true, [
        'group' => 'google_calendar',
        'label' => 'Bật Google Calendar',
        'value_type' => 'boolean',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.client_id', 'gcal-client-id', [
        'group' => 'google_calendar',
        'label' => 'Google Client ID',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.client_secret', 'gcal-client-secret', [
        'group' => 'google_calendar',
        'label' => 'Google Client Secret',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.refresh_token', 'gcal-refresh-token', [
        'group' => 'google_calendar',
        'label' => 'Google Refresh Token',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.calendar_id', 'crm-calendar@example.com', [
        'group' => 'google_calendar',
        'label' => 'Google Calendar ID',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.sync_mode', 'one_way_to_google', [
        'group' => 'google_calendar',
        'label' => 'Chế độ đồng bộ',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);
}
