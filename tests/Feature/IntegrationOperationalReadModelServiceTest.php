<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;
use App\Services\IntegrationOperationalReadModelService;
use App\Services\IntegrationSecretRotationService;
use Carbon\Carbon;

it('computes web lead retention and mail backlog counts from shared integration reader', function (): void {
    [$branch] = seedIntegrationOperationalReadModelEntities();

    setIntegrationOperationalRetentionDays(30);

    $oldCreatedLead = WebLeadIngestion::factory()->create([
        'branch_id' => $branch->id,
        'branch_code' => $branch->code,
        'status' => WebLeadIngestion::STATUS_CREATED,
        'processed_at' => now()->subDays(45),
    ]);
    markIntegrationOperationalRecordOld($oldCreatedLead, 45);

    $oldMergedLead = WebLeadIngestion::factory()->create([
        'branch_id' => $branch->id,
        'branch_code' => $branch->code,
        'status' => WebLeadIngestion::STATUS_MERGED,
        'processed_at' => null,
    ]);
    markIntegrationOperationalRecordOld($oldMergedLead, 45);

    WebLeadIngestion::factory()->create([
        'branch_id' => $branch->id,
        'branch_code' => $branch->code,
        'status' => WebLeadIngestion::STATUS_FAILED,
        'processed_at' => now()->subDays(5),
    ]);

    $oldSentDelivery = WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
        'web_lead_ingestion_id' => $oldCreatedLead->id,
        'customer_id' => $oldCreatedLead->customer_id,
        'status' => WebLeadEmailDelivery::STATUS_SENT,
        'sent_at' => now()->subDays(45),
    ]);
    markIntegrationOperationalRecordOld($oldSentDelivery, 45);

    $oldDeadDelivery = WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
        'web_lead_ingestion_id' => $oldMergedLead->id,
        'customer_id' => $oldMergedLead->customer_id,
        'status' => WebLeadEmailDelivery::STATUS_DEAD,
        'next_retry_at' => null,
    ]);
    markIntegrationOperationalRecordOld($oldDeadDelivery, 45);

    $oldSkippedDelivery = WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
        'status' => WebLeadEmailDelivery::STATUS_SKIPPED,
    ]);
    markIntegrationOperationalRecordOld($oldSkippedDelivery, 45);

    WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
        'status' => WebLeadEmailDelivery::STATUS_RETRYABLE,
        'next_retry_at' => now()->subMinute(),
    ]);

    WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
        'status' => WebLeadEmailDelivery::STATUS_DEAD,
        'next_retry_at' => null,
    ]);

    $service = app(IntegrationOperationalReadModelService::class);

    expect($service->webLeadIngestionRetentionCandidateCount(30))->toBe(2)
        ->and($service->webLeadTerminalEmailRetentionCandidateCount(30))->toBe(3)
        ->and($service->webLeadRetryableEmailCount())->toBe(1)
        ->and($service->webLeadDeadEmailCount())->toBe(2);
});

it('computes webhook, emr, and google retention counts from shared integration reader', function (): void {
    [$branch, $patient, $appointment] = seedIntegrationOperationalReadModelEntities();

    setIntegrationOperationalRetentionDays(30);

    ZaloWebhookEvent::query()->create([
        'event_fingerprint' => 'webhook-old-received',
        'event_name' => 'user_send_text',
        'payload' => ['event_name' => 'user_send_text'],
        'received_at' => now()->subDays(45),
        'processed_at' => now()->subDays(45),
    ]);

    $fallbackWebhook = ZaloWebhookEvent::query()->create([
        'event_fingerprint' => 'webhook-old-fallback',
        'event_name' => 'user_send_text',
        'payload' => ['event_name' => 'user_send_text'],
        'received_at' => null,
        'processed_at' => null,
    ]);
    markIntegrationOperationalRecordOld($fallbackWebhook, 45);

    ZaloWebhookEvent::query()->create([
        'event_fingerprint' => 'webhook-fresh',
        'event_name' => 'user_send_text',
        'payload' => ['event_name' => 'user_send_text'],
        'received_at' => now()->subDays(5),
        'processed_at' => now()->subDays(5),
    ]);

    $oldSyncedEmrEvent = EmrSyncEvent::query()->create([
        'event_key' => 'emr-synced-old',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $patient->id],
        'payload_checksum' => hash('sha256', 'emr-synced-old'),
        'status' => EmrSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(45),
    ]);

    EmrSyncLog::query()->create([
        'emr_sync_event_id' => $oldSyncedEmrEvent->id,
        'attempt' => 1,
        'status' => EmrSyncEvent::STATUS_SYNCED,
        'request_payload' => ['event_key' => 'emr-synced-old'],
        'response_payload' => ['message' => 'ok'],
        'attempted_at' => now()->subDays(45),
    ]);

    $oldDeadEmrEvent = EmrSyncEvent::query()->create([
        'event_key' => 'emr-dead-old',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $patient->id],
        'payload_checksum' => hash('sha256', 'emr-dead-old'),
        'status' => EmrSyncEvent::STATUS_DEAD,
        'processed_at' => null,
    ]);
    markIntegrationOperationalRecordOld($oldDeadEmrEvent, 45);

    EmrSyncEvent::query()->create([
        'event_key' => 'emr-failed',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $patient->id],
        'payload_checksum' => hash('sha256', 'emr-failed'),
        'status' => EmrSyncEvent::STATUS_FAILED,
        'processed_at' => null,
    ]);

    EmrSyncEvent::query()->create([
        'event_key' => 'emr-fresh',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $patient->id],
        'payload_checksum' => hash('sha256', 'emr-fresh'),
        'status' => EmrSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(5),
    ]);

    $oldSyncedGoogleEvent = GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'google-synced-old',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $appointment->id],
        'payload_checksum' => hash('sha256', 'google-synced-old'),
        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(45),
    ]);

    GoogleCalendarSyncLog::query()->create([
        'google_calendar_sync_event_id' => $oldSyncedGoogleEvent->id,
        'attempt' => 1,
        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
        'request_payload' => ['event_key' => 'google-synced-old'],
        'response_payload' => ['message' => 'ok'],
        'attempted_at' => now()->subDays(45),
    ]);

    $oldDeadGoogleEvent = GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'google-dead-old',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $appointment->id],
        'payload_checksum' => hash('sha256', 'google-dead-old'),
        'status' => GoogleCalendarSyncEvent::STATUS_DEAD,
        'processed_at' => null,
    ]);
    markIntegrationOperationalRecordOld($oldDeadGoogleEvent, 45);

    GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'google-failed',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $appointment->id],
        'payload_checksum' => hash('sha256', 'google-failed'),
        'status' => GoogleCalendarSyncEvent::STATUS_FAILED,
        'processed_at' => null,
    ]);

    GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'google-fresh',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $appointment->id],
        'payload_checksum' => hash('sha256', 'google-fresh'),
        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
        'processed_at' => now()->subDays(5),
    ]);

    $service = app(IntegrationOperationalReadModelService::class);

    expect($service->zaloWebhookRetentionCandidateCount(30))->toBe(2)
        ->and($service->emrRetentionCandidateCount(30))->toBe(3)
        ->and($service->emrDeadBacklogCount())->toBe(1)
        ->and($service->emrFailedBacklogCount())->toBe(1)
        ->and($service->googleCalendarRetentionCandidateCount(30))->toBe(3)
        ->and($service->googleCalendarDeadBacklogCount())->toBe(1)
        ->and($service->googleCalendarFailedBacklogCount())->toBe(1);
});

it('computes clinical media retention counts from the shared integration reader', function (): void {
    [$branch, $patient] = seedIntegrationOperationalReadModelEntities();

    ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'captured_at' => now()->subDays(45),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'temp-old'),
        'storage_disk' => 'public',
        'storage_path' => 'clinical-media/temp-old.jpg',
        'retention_class' => ClinicalMediaAsset::RETENTION_TEMPORARY,
        'legal_hold' => false,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'captured_at' => now()->subDays(45),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'operational-old'),
        'storage_disk' => 'public',
        'storage_path' => 'clinical-media/operational-old.jpg',
        'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
        'legal_hold' => false,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    ClinicalMediaAsset::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'captured_at' => now()->subDays(45),
        'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
        'phase' => 'pre',
        'mime_type' => 'image/jpeg',
        'checksum_sha256' => hash('sha256', 'legal-hold'),
        'storage_disk' => 'public',
        'storage_path' => 'clinical-media/legal-hold.jpg',
        'retention_class' => ClinicalMediaAsset::RETENTION_TEMPORARY,
        'legal_hold' => true,
        'status' => ClinicalMediaAsset::STATUS_ACTIVE,
    ]);

    $service = app(IntegrationOperationalReadModelService::class);

    expect($service->clinicalMediaRetentionCandidateCount(ClinicalMediaAsset::RETENTION_TEMPORARY, 30))->toBe(1)
        ->and($service->clinicalMediaRetentionCandidateCount(ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL, 30))->toBe(1);
});

it('supports scoped dead-letter counts for emr and google sync lanes', function (): void {
    [$branch, $patient, $appointment] = seedIntegrationOperationalReadModelEntities();

    $otherPatient = Patient::factory()->create([
        'customer_id' => Customer::factory()->create(['branch_id' => $branch->id])->id,
        'first_branch_id' => $branch->id,
    ]);
    $otherAppointment = Appointment::factory()->create([
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addDays(2),
    ]);

    EmrSyncEvent::query()->create([
        'event_key' => 'emr-dead-scoped-a',
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $patient->id],
        'payload_checksum' => hash('sha256', 'emr-dead-scoped-a'),
        'status' => EmrSyncEvent::STATUS_DEAD,
        'processed_at' => null,
    ]);

    EmrSyncEvent::query()->create([
        'event_key' => 'emr-dead-scoped-b',
        'patient_id' => $otherPatient->id,
        'branch_id' => $branch->id,
        'event_type' => 'manual.sync',
        'payload' => ['patient_id' => $otherPatient->id],
        'payload_checksum' => hash('sha256', 'emr-dead-scoped-b'),
        'status' => EmrSyncEvent::STATUS_DEAD,
        'processed_at' => null,
    ]);

    GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'google-dead-scoped-a',
        'appointment_id' => $appointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $appointment->id],
        'payload_checksum' => hash('sha256', 'google-dead-scoped-a'),
        'status' => GoogleCalendarSyncEvent::STATUS_DEAD,
        'processed_at' => null,
    ]);

    GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'google-dead-scoped-b',
        'appointment_id' => $otherAppointment->id,
        'branch_id' => $branch->id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => ['appointment_id' => $otherAppointment->id],
        'payload_checksum' => hash('sha256', 'google-dead-scoped-b'),
        'status' => GoogleCalendarSyncEvent::STATUS_DEAD,
        'processed_at' => null,
    ]);

    $service = app(IntegrationOperationalReadModelService::class);

    expect($service->emrDeadBacklogCount($patient->id))->toBe(1)
        ->and($service->emrDeadBacklogCount($otherPatient->id))->toBe(1)
        ->and($service->googleCalendarDeadBacklogCount($appointment->id))->toBe(1)
        ->and($service->googleCalendarDeadBacklogCount($otherAppointment->id))->toBe(1);
});

it('reads active and expired grace rotations from the shared integration reader', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-28 09:00:00'));
    try {
        ClinicSetting::setValue('web_lead.api_token', 'web-lead-old-token', [
            'group' => 'web_lead',
            'value_type' => 'text',
            'is_secret' => true,
        ]);
        ClinicSetting::setValue('emr.api_key', 'emr-old-token', [
            'group' => 'emr',
            'value_type' => 'text',
            'is_secret' => true,
        ]);
        ClinicSetting::setValue('web_lead.api_token_grace_minutes', 60, [
            'group' => 'web_lead',
            'value_type' => 'integer',
        ]);
        ClinicSetting::setValue('emr.api_key_grace_minutes', 10, [
            'group' => 'emr',
            'value_type' => 'integer',
        ]);

        $rotationService = app(IntegrationSecretRotationService::class);

        $rotationService->rotate(
            settingKey: 'web_lead.api_token',
            newSecret: 'web-lead-new-token',
            reason: 'Shared reader active grace test.',
        );
        $rotationService->rotate(
            settingKey: 'emr.api_key',
            newSecret: 'emr-new-token',
            reason: 'Shared reader expired grace test.',
        );

        ClinicSetting::setValue('emr.api_key_grace_expires_at', now()->subMinutes(5)->toISOString(), [
            'group' => 'emr',
            'value_type' => 'text',
        ]);

        $service = app(IntegrationOperationalReadModelService::class);
        $activeRotations = $service->activeGraceRotations();
        $renderedActiveRotations = $service->renderedActiveGraceRotations();
        $expiredRotations = $service->expiredGraceRotations();
        $renderedExpiredRotations = $service->renderedExpiredGraceRotations();
        $expiredSummary = $service->expiredGraceRotationSummary();

        expect($activeRotations)->toHaveCount(1)
            ->and($renderedActiveRotations)->toHaveCount(1)
            ->and($expiredRotations)->toHaveCount(1)
            ->and($renderedExpiredRotations)->toHaveCount(1)
            ->and($activeRotations->firstWhere('key', 'web_lead.api_token'))->not->toBeNull()
            ->and($renderedActiveRotations->firstWhere('key', 'web_lead.api_token'))->toMatchArray([
                'display_name' => 'Web Lead API Token',
                'rotation_reason' => 'Shared reader active grace test.',
            ])
            ->and($renderedActiveRotations->firstWhere('key', 'web_lead.api_token')['grace_expires_at_label'])->not->toBeEmpty()
            ->and($renderedActiveRotations->firstWhere('key', 'web_lead.api_token')['remaining_minutes_label'])->toContain('Còn lại khoảng')
            ->and($expiredRotations->firstWhere('key', 'emr.api_key'))->not->toBeNull()
            ->and($renderedExpiredRotations->firstWhere('key', 'emr.api_key'))->toMatchArray([
                'display_name' => 'EMR API Key',
            ])
            ->and($renderedExpiredRotations->firstWhere('key', 'emr.api_key')['grace_expires_at_label'])->not->toBeEmpty()
            ->and($renderedExpiredRotations->firstWhere('key', 'emr.api_key')['expired_minutes_label'])->toContain('Quá hạn')
            ->and((int) $activeRotations->firstWhere('key', 'web_lead.api_token')['remaining_minutes'])->toBeGreaterThan(0)
            ->and((int) $expiredRotations->firstWhere('key', 'emr.api_key')['expired_minutes'])->toBeGreaterThan(0)
            ->and($expiredSummary['total'])->toBe(1)
            ->and($expiredSummary['keys'])->toContain('emr.api_key')
            ->and($expiredSummary['display_names'])->toContain('EMR API Key')
            ->and($expiredSummary['max_expired_minutes'])->toBeGreaterThan(0);
    } finally {
        Carbon::setTestNow();
    }
});

it('computes popup and patient photo retention counts from the shared integration reader', function (): void {
    [$branch, $patient] = seedIntegrationOperationalReadModelEntities();

    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $oldAnnouncement = PopupAnnouncement::query()->create([
        'title' => 'Popup old cancelled',
        'message' => 'Popup cũ đã hủy',
        'status' => PopupAnnouncement::STATUS_CANCELLED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
    ]);
    markIntegrationOperationalRecordOld($oldAnnouncement, 45);

    $announcementWithDelivery = PopupAnnouncement::query()->create([
        'title' => 'Popup expired with delivery',
        'message' => 'Popup có delivery nên chưa được xóa record cha',
        'status' => PopupAnnouncement::STATUS_EXPIRED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
    ]);
    markIntegrationOperationalRecordOld($announcementWithDelivery, 45);

    $announcementOnlyParent = PopupAnnouncement::query()->create([
        'title' => 'Popup old without delivery',
        'message' => 'Popup cũ không còn delivery',
        'status' => PopupAnnouncement::STATUS_EXPIRED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
    ]);
    markIntegrationOperationalRecordOld($announcementOnlyParent, 45);

    PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcementWithDelivery->id,
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'status' => PopupAnnouncementDelivery::STATUS_DISMISSED,
        'dismissed_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $oldAnnouncement->id,
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'status' => PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED,
        'acknowledged_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_EXTERNAL,
        'date' => now()->subDays(60)->toDateString(),
        'title' => 'Old extra oral',
        'content' => ['patient-photos/ext/old.jpg'],
    ]);

    PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_EXTERNAL,
        'date' => now()->subDays(5)->toDateString(),
        'title' => 'Fresh extra oral',
        'content' => ['patient-photos/ext/fresh.jpg'],
    ]);

    PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_XRAY,
        'date' => now()->subDays(90)->toDateString(),
        'title' => 'Old xray',
        'content' => ['patient-photos/xray/old.jpg'],
    ]);

    $service = app(IntegrationOperationalReadModelService::class);
    $retentionCandidates = collect($service->retentionCandidates())->keyBy('label');
    $popupRetentionDays = \App\Support\ClinicRuntimeSettings::popupAnnouncementRetentionDays();
    $patientPhotoRetentionDays = \App\Support\ClinicRuntimeSettings::patientPhotoRetentionDays();
    $patientPhotoRetentionEnabled = \App\Support\ClinicRuntimeSettings::patientPhotoRetentionEnabled();
    $patientPhotoRetentionIncludeXray = \App\Support\ClinicRuntimeSettings::patientPhotoRetentionIncludeXray();

    expect($service->popupDeliveryRetentionCandidateCount(30))->toBe(2)
        ->and($service->popupAnnouncementRetentionCandidateCount(30))->toBe(1)
        ->and($service->patientPhotoRetentionCandidateCount(30))->toBe(1)
        ->and($service->patientPhotoRetentionCandidateCount(30, true))->toBe(2)
        ->and($retentionCandidates->get('Popup announcement logs'))->toMatchArray([
            'retention_days' => $popupRetentionDays,
            'total' => $service->popupDeliveryRetentionCandidateCount($popupRetentionDays)
                + $service->popupAnnouncementRetentionCandidateCount($popupRetentionDays),
            'tone' => $service->popupDeliveryRetentionCandidateCount($popupRetentionDays)
                + $service->popupAnnouncementRetentionCandidateCount($popupRetentionDays) > 0 ? 'warning' : 'success',
        ])
        ->and($retentionCandidates->get('Patient photos'))->toMatchArray([
            'retention_days' => $patientPhotoRetentionDays,
            'total' => $patientPhotoRetentionEnabled
                ? $service->patientPhotoRetentionCandidateCount(
                    $patientPhotoRetentionDays,
                    $patientPhotoRetentionIncludeXray
                )
                : 0,
            'tone' => $patientPhotoRetentionEnabled
                && $service->patientPhotoRetentionCandidateCount(
                    $patientPhotoRetentionDays,
                    $patientPhotoRetentionIncludeXray
                ) > 0 ? 'warning' : 'success',
        ])
        ->and($retentionCandidates->get('Clinical media temporary')['label'])->toBe('Clinical media temporary');
});

function seedIntegrationOperationalReadModelEntities(): array
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

function setIntegrationOperationalRetentionDays(int $days): void
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

function markIntegrationOperationalRecordOld(object $model, int $days): void
{
    $model->forceFill([
        'created_at' => now()->subDays($days),
        'updated_at' => now()->subDays($days),
    ])->saveQuietly();
}
