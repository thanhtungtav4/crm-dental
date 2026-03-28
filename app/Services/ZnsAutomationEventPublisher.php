<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\ZnsAutomationEvent;
use App\Support\ClinicRuntimeSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ZnsAutomationEventPublisher
{
    public function __construct(
        protected IntegrationProviderRuntimeGate $integrationProviderRuntimeGate,
    ) {}

    public function publishLeadWelcomeForWebLead(Customer $customer, string $requestId): ?ZnsAutomationEvent
    {
        if (! $this->shouldPublishLeadWelcome()) {
            return null;
        }

        if (! $this->isZnsRuntimeReady()) {
            return null;
        }

        $templateId = ClinicRuntimeSettings::znsTemplateLeadWelcome();
        if ($templateId === '') {
            return null;
        }

        $phone = trim((string) ($customer->phone ?: ''));
        $sourceIdentifier = $customer->exists
            ? 'customer:'.$customer->getKey()
            : 'customer-phone:'.$this->normalizePhone($phone);

        return $this->publishEvent(
            eventType: ZnsAutomationEvent::EVENT_LEAD_WELCOME,
            templateKey: 'lead_welcome',
            templateId: $templateId,
            sourceIdentifier: $sourceIdentifier,
            appointmentId: null,
            patientId: null,
            customerId: $customer->id ? (int) $customer->id : null,
            branchId: $customer->branch_id ? (int) $customer->branch_id : null,
            phone: $phone,
            payload: [
                'customer_name' => trim((string) $customer->full_name),
                'lead_request_id' => trim($requestId),
                'source' => 'website',
                'created_at' => now()->format('Y-m-d H:i:s'),
            ],
            nextRetryAt: now(),
        );
    }

    public function publishAppointmentReminder(Appointment $appointment): ?ZnsAutomationEvent
    {
        if (! $this->shouldPublishAppointmentReminder()) {
            $this->cancelAppointmentReminder(
                appointmentId: (int) $appointment->id,
                reason: 'ZNS appointment reminder automation đang tắt.',
            );

            return null;
        }

        if (! $this->isZnsRuntimeReady()) {
            return null;
        }

        $templateId = ClinicRuntimeSettings::znsTemplateAppointment();
        if ($templateId === '') {
            return null;
        }

        if (! $this->isAppointmentReminderEligible($appointment)) {
            $this->cancelAppointmentReminder(
                appointmentId: (int) $appointment->id,
                reason: 'Lịch hẹn không còn đủ điều kiện gửi nhắc hẹn ZNS.',
            );

            return null;
        }

        $appointment->loadMissing(['patient:id,full_name,phone,customer_id', 'customer:id,full_name,phone', 'doctor:id,name', 'branch:id,name']);

        $phone = $this->resolveAppointmentPhone($appointment);
        $reminderHours = $this->resolveReminderHours($appointment);
        $sendAt = $appointment->date?->copy()->subHours($reminderHours);
        $nextRetryAt = ($sendAt && $sendAt->isFuture()) ? $sendAt : now();

        return $this->publishEvent(
            eventType: ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER,
            templateKey: 'appointment',
            templateId: $templateId,
            sourceIdentifier: 'appointment:'.$appointment->id,
            appointmentId: (int) $appointment->id,
            patientId: $appointment->patient_id ? (int) $appointment->patient_id : null,
            customerId: $appointment->customer_id ? (int) $appointment->customer_id : null,
            branchId: $appointment->branch_id ? (int) $appointment->branch_id : null,
            phone: $phone,
            payload: [
                'appointment_id' => (int) $appointment->id,
                'recipient_name' => trim((string) ($appointment->patient?->full_name ?: $appointment->customer?->full_name ?: 'Khách hàng')),
                'appointment_at' => $appointment->date?->format('Y-m-d H:i:s'),
                'appointment_at_display' => $appointment->date?->format('d/m/Y H:i'),
                'doctor_name' => trim((string) ($appointment->doctor?->name ?? '')),
                'branch_name' => trim((string) ($appointment->branch?->name ?? '')),
                'reminder_hours' => $reminderHours,
            ],
            nextRetryAt: $nextRetryAt,
        );
    }

    public function publishBirthdayGreeting(Patient $patient, CarbonInterface $asOfDate): ?ZnsAutomationEvent
    {
        if (! $this->shouldPublishBirthdayGreeting()) {
            return null;
        }

        if (! $this->isZnsRuntimeReady()) {
            return null;
        }

        $templateId = ClinicRuntimeSettings::znsTemplateBirthday();
        if ($templateId === '') {
            return null;
        }

        $patient->loadMissing(['customer:id,phone']);

        $phone = trim((string) ($patient->phone ?: $patient->customer?->phone ?: ''));
        $year = (int) $asOfDate->format('Y');
        $nextRetryAt = now();

        if ($asOfDate->isToday()) {
            $scheduled = $asOfDate->copy()->startOfDay()->addMinutes(10);
            $nextRetryAt = $scheduled->isFuture() ? $scheduled : now();
        }

        return $this->publishEvent(
            eventType: ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING,
            templateKey: 'birthday',
            templateId: $templateId,
            sourceIdentifier: sprintf('patient:%d:birthday:%d', (int) $patient->id, $year),
            appointmentId: null,
            patientId: (int) $patient->id,
            customerId: $patient->customer_id ? (int) $patient->customer_id : null,
            branchId: $patient->first_branch_id ? (int) $patient->first_branch_id : null,
            phone: $phone,
            payload: [
                'patient_id' => (int) $patient->id,
                'recipient_name' => trim((string) $patient->full_name),
                'birthday' => $patient->birthday?->format('d/m'),
                'birthday_year' => $year,
                'message_date' => $asOfDate->format('Y-m-d'),
            ],
            nextRetryAt: $nextRetryAt,
        );
    }

    public function cancelAppointmentReminder(int $appointmentId, string $reason = 'Appointment reminder cancelled.'): int
    {
        if ($appointmentId <= 0) {
            return 0;
        }

        $events = ZnsAutomationEvent::query()
            ->where('appointment_id', $appointmentId)
            ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
            ->whereIn('status', [
                ZnsAutomationEvent::STATUS_PENDING,
                ZnsAutomationEvent::STATUS_FAILED,
                ZnsAutomationEvent::STATUS_PROCESSING,
            ])
            ->get();

        $events->each(fn (ZnsAutomationEvent $event): mixed => $event->markSuperseded($reason));

        return $events->count();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function publishEvent(
        string $eventType,
        string $templateKey,
        string $templateId,
        string $sourceIdentifier,
        ?int $appointmentId,
        ?int $patientId,
        ?int $customerId,
        ?int $branchId,
        string $phone,
        array $payload,
        ?CarbonInterface $nextRetryAt,
    ): ?ZnsAutomationEvent {
        $normalizedPhone = $this->normalizePhone($phone);
        $payloadChecksum = $this->checksum([
            'source' => $sourceIdentifier,
            'event_type' => $eventType,
            'template_key' => $templateKey,
            'template_id' => $templateId,
            'phone' => $normalizedPhone,
            'payload' => $payload,
        ]);
        $eventKey = $this->eventKey($sourceIdentifier, $eventType, $payloadChecksum);
        $readyAt = $this->normalizeReadyAt($nextRetryAt);

        return DB::transaction(function () use (
            $eventType,
            $templateKey,
            $templateId,
            $appointmentId,
            $patientId,
            $customerId,
            $branchId,
            $phone,
            $normalizedPhone,
            $payload,
            $payloadChecksum,
            $eventKey,
            $readyAt,
        ): ZnsAutomationEvent {
            if ($eventType === ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER && $appointmentId) {
                $this->supersedeAppointmentReminderEvents($appointmentId, $eventKey);
            }

            $existing = ZnsAutomationEvent::query()
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->refreshExistingEvent(
                    event: $existing,
                    templateKey: $templateKey,
                    templateId: $templateId,
                    appointmentId: $appointmentId,
                    patientId: $patientId,
                    customerId: $customerId,
                    branchId: $branchId,
                    phone: $phone,
                    normalizedPhone: $normalizedPhone,
                    payload: $payload,
                    payloadChecksum: $payloadChecksum,
                    nextRetryAt: $readyAt,
                );
            }

            try {
                return ZnsAutomationEvent::query()->create([
                    'event_key' => $eventKey,
                    'event_type' => $eventType,
                    'template_key' => $templateKey,
                    'template_id_snapshot' => $templateId,
                    'appointment_id' => $appointmentId,
                    'patient_id' => $patientId,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'phone' => trim($phone),
                    'normalized_phone' => $normalizedPhone,
                    'payload' => $payload,
                    'payload_checksum' => $payloadChecksum,
                    'status' => ZnsAutomationEvent::STATUS_PENDING,
                    'next_retry_at' => $readyAt,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $existing = ZnsAutomationEvent::query()
                    ->where('event_key', $eventKey)
                    ->lockForUpdate()
                    ->first();

                if (! $existing) {
                    throw $exception;
                }

                return $this->refreshExistingEvent(
                    event: $existing,
                    templateKey: $templateKey,
                    templateId: $templateId,
                    appointmentId: $appointmentId,
                    patientId: $patientId,
                    customerId: $customerId,
                    branchId: $branchId,
                    phone: $phone,
                    normalizedPhone: $normalizedPhone,
                    payload: $payload,
                    payloadChecksum: $payloadChecksum,
                    nextRetryAt: $readyAt,
                );
            }
        }, 3);
    }

    protected function shouldPublishLeadWelcome(): bool
    {
        return ClinicRuntimeSettings::boolean('zns.enabled', false)
            && ClinicRuntimeSettings::znsAutoSendLeadWelcome();
    }

    protected function shouldPublishAppointmentReminder(): bool
    {
        return ClinicRuntimeSettings::boolean('zns.enabled', false)
            && ClinicRuntimeSettings::znsAutoSendAppointmentReminder();
    }

    protected function shouldPublishBirthdayGreeting(): bool
    {
        return ClinicRuntimeSettings::boolean('zns.enabled', false)
            && ClinicRuntimeSettings::znsAutoSendBirthdayGreeting();
    }

    protected function isZnsRuntimeReady(): bool
    {
        return $this->integrationProviderRuntimeGate->allowsZnsPublish();
    }

    protected function isAppointmentReminderEligible(Appointment $appointment): bool
    {
        if ($appointment->trashed() || ! $appointment->date) {
            return false;
        }

        if ($appointment->date->lte(now())) {
            return false;
        }

        return in_array(
            $appointment->status,
            [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED, Appointment::STATUS_RESCHEDULED],
            true,
        );
    }

    protected function resolveReminderHours(Appointment $appointment): int
    {
        $appointmentHours = (int) ($appointment->reminder_hours ?? 0);
        if ($appointmentHours > 0) {
            return min(168, $appointmentHours);
        }

        return ClinicRuntimeSettings::znsAppointmentReminderDefaultHours();
    }

    protected function resolveAppointmentPhone(Appointment $appointment): string
    {
        $patientPhone = trim((string) ($appointment->patient?->phone ?? ''));
        if ($patientPhone !== '') {
            return $patientPhone;
        }

        return trim((string) ($appointment->customer?->phone ?? ''));
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (! is_string($digits)) {
            return '';
        }

        return trim($digits);
    }

    protected function normalizeReadyAt(?CarbonInterface $dateTime): \Illuminate\Support\Carbon
    {
        if ($dateTime === null) {
            return now();
        }

        $readyAt = now()->setTimestamp($dateTime->getTimestamp());

        return $readyAt->isFuture() ? $readyAt : now();
    }

    protected function eventKey(string $sourceIdentifier, string $eventType, string $payloadChecksum): string
    {
        return substr(hash('sha1', implode('|', [
            'zns-automation',
            $sourceIdentifier,
            $eventType,
            $payloadChecksum,
        ])), 0, 40);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function checksum(array $value): string
    {
        return hash('sha256', json_encode($this->normalizeForChecksum($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    protected function normalizeForChecksum(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeForChecksum($item);
        }

        if ($this->isAssoc($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $value
     */
    protected function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function refreshExistingEvent(
        ZnsAutomationEvent $event,
        string $templateKey,
        string $templateId,
        ?int $appointmentId,
        ?int $patientId,
        ?int $customerId,
        ?int $branchId,
        string $phone,
        string $normalizedPhone,
        array $payload,
        string $payloadChecksum,
        \Illuminate\Support\Carbon $nextRetryAt,
    ): ZnsAutomationEvent {
        if ($event->status === ZnsAutomationEvent::STATUS_PROCESSING) {
            return $event;
        }

        $event->resetForReplay([
            'template_key' => $templateKey,
            'template_id_snapshot' => $templateId,
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'phone' => trim($phone),
            'normalized_phone' => $normalizedPhone,
            'payload' => $payload,
            'payload_checksum' => $payloadChecksum,
            'next_retry_at' => $nextRetryAt,
        ]);

        return $event->fresh() ?? $event;
    }

    protected function supersedeAppointmentReminderEvents(int $appointmentId, string $keepEventKey): void
    {
        ZnsAutomationEvent::query()
            ->where('appointment_id', $appointmentId)
            ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
            ->where('event_key', '!=', $keepEventKey)
            ->whereIn('status', [
                ZnsAutomationEvent::STATUS_PENDING,
                ZnsAutomationEvent::STATUS_FAILED,
                ZnsAutomationEvent::STATUS_PROCESSING,
            ])
            ->get()
            ->each(fn (ZnsAutomationEvent $event): mixed => $event->markSuperseded('Superseded by newer appointment reminder payload.'));
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($sqlState, ['23000', '23505'], true)
            || $driverCode === 1062;
    }
}
