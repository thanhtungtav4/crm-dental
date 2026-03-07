<?php

namespace App\Services;

use App\Models\EmrSyncEvent;
use App\Models\GoogleCalendarSyncEvent;

class IntegrationOperationalPayloadSanitizer
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitizeZaloWebhookPayload(?array $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $attachmentTypes = collect(data_get($payload, 'message.attachments', []))
            ->map(static fn (mixed $attachment): ?string => is_array($attachment)
                ? self::trimString(data_get($attachment, 'type'))
                : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $topLevelKeys = collect(array_keys($payload))
            ->filter(static fn (mixed $key): bool => is_string($key) && trim($key) !== '')
            ->map(static fn (string $key): string => trim($key))
            ->values()
            ->all();

        return $this->filterNulls([
            'event_name' => self::trimString(data_get($payload, 'event_name')),
            'event_id' => self::trimString(data_get($payload, 'event_id')),
            'oa_id' => self::trimString(data_get($payload, 'oa_id')),
            'timestamp' => self::trimString(data_get($payload, 'timestamp')),
            'sender_id' => self::trimString(data_get($payload, 'sender.id') ?? data_get($payload, 'from.uid')),
            'recipient_id' => self::trimString(data_get($payload, 'recipient.id') ?? data_get($payload, 'to.uid')),
            'message_id' => self::trimString(
                data_get($payload, 'message.msg_id')
                    ?? data_get($payload, 'message.message_id')
                    ?? data_get($payload, 'msg_id')
            ),
            'message_text_present' => filled(data_get($payload, 'message.text')) ? true : null,
            'attachment_types' => $attachmentTypes === [] ? null : $attachmentTypes,
            'top_level_keys' => $topLevelKeys === [] ? null : $topLevelKeys,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitizeWebLeadPayload(?array $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $this->filterNulls([
            'full_name_present' => filled(data_get($payload, 'full_name')) ? true : null,
            'phone_present' => filled(data_get($payload, 'phone')) ? true : null,
            'branch_code' => self::trimString(data_get($payload, 'branch_code')),
            'source' => self::trimString(data_get($payload, 'source') ?? 'website'),
            'note_present' => filled(data_get($payload, 'note')) ? true : null,
            'payload_keys' => collect(array_keys($payload))
                ->filter(static fn (mixed $key): bool => is_string($key) && trim($key) !== '')
                ->map(static fn (string $key): string => trim($key))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitizeWebLeadResponse(?array $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $this->filterNulls([
            'customer_id' => data_get($payload, 'customer_id'),
            'branch_id' => data_get($payload, 'branch_id'),
            'status' => self::trimString(data_get($payload, 'status')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeGoogleCalendarLogRequest(GoogleCalendarSyncEvent $event): array
    {
        $payload = is_array($event->payload) ? $event->payload : [];

        return $this->filterNulls([
            'event_key' => self::trimString($event->event_key),
            'event_type' => self::trimString($event->event_type),
            'appointment_id' => $event->appointment_id ? (int) $event->appointment_id : null,
            'branch_id' => $event->branch_id ? (int) $event->branch_id : null,
            'payload_checksum' => self::trimString($event->payload_checksum),
            'start_at' => self::trimString(data_get($payload, 'start.dateTime')),
            'end_at' => self::trimString(data_get($payload, 'end.dateTime')),
            'crm_status' => self::trimString(data_get($payload, 'extendedProperties.private.crm_status')),
            'summary_present' => filled(data_get($payload, 'summary')) ? true : null,
            'description_present' => filled(data_get($payload, 'description')) ? true : null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitizeGoogleCalendarLogResponse(?array $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $this->filterNulls([
            'id' => self::trimString(data_get($payload, 'id')),
            'status' => self::trimString(data_get($payload, 'status')),
            'updated' => self::trimString(data_get($payload, 'updated')),
            'error' => self::trimString(data_get($payload, 'error.message') ?? data_get($payload, 'message')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeEmrSyncLogRequest(EmrSyncEvent $event): array
    {
        $payload = is_array($event->payload) ? $event->payload : [];

        return $this->filterNulls([
            'event_key' => self::trimString($event->event_key),
            'event_type' => self::trimString($event->event_type),
            'patient_id' => $event->patient_id ? (int) $event->patient_id : null,
            'branch_id' => $event->branch_id ? (int) $event->branch_id : null,
            'payload_checksum' => self::trimString($event->payload_checksum),
            'schema_version' => self::trimString(data_get($payload, 'meta.schema_version')),
            'encounter_count' => count((array) data_get($payload, 'encounter.records', [])),
            'exam_session_count' => count((array) data_get($payload, 'exam_session.records', [])),
            'treatment_plan_count' => count((array) data_get($payload, 'treatment.plans', [])),
            'clinical_order_count' => count((array) data_get($payload, 'order.records', [])),
            'clinical_result_count' => count((array) data_get($payload, 'result.records', [])),
            'prescription_count' => count((array) data_get($payload, 'prescription.records', [])),
            'media_asset_count' => is_numeric(data_get($payload, 'media.summary.total_assets'))
                ? (int) data_get($payload, 'media.summary.total_assets')
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitizeEmrSyncLogResponse(?array $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $this->filterNulls([
            'message' => self::trimString(data_get($payload, 'message')),
            'status' => self::trimString(data_get($payload, 'status') ?? data_get($payload, 'code')),
            'external_patient_id' => self::trimString(
                data_get($payload, 'external_patient_id')
                    ?? data_get($payload, 'patient_id')
            ),
            'error' => self::trimString(data_get($payload, 'error.message') ?? data_get($payload, 'error')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function filterNulls(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => ! ($value === null || $value === [] || $value === ''));
    }

    protected static function trimString(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
