<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentSchedulingService
{
    public function create(array $data): Appointment
    {
        return DB::transaction(function () use ($data): Appointment {
            $appointment = new Appointment;
            $appointment->fill($data);
            $appointment->save();

            return $appointment->fresh() ?? $appointment;
        }, 5);
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data): Appointment {
            $lockedAppointment = $this->lockAppointment($appointment);
            $normalizedStartAt = $this->extractNormalizedStartAt($data);
            $originalStartAt = $lockedAppointment->date?->copy();
            $isSlotChanged = $normalizedStartAt !== null
                && ($originalStartAt?->format('Y-m-d H:i:s') !== $normalizedStartAt->format('Y-m-d H:i:s'));

            if ($normalizedStartAt !== null) {
                $data['date'] = $normalizedStartAt;
            }

            $reason = trim((string) ($data['reschedule_reason'] ?? ''));

            if ($isSlotChanged && $reason === '') {
                throw ValidationException::withMessages([
                    'reschedule_reason' => 'Vui lòng nhập lý do đổi lịch.',
                ]);
            }

            if ($isSlotChanged) {
                $data['status'] = Appointment::STATUS_RESCHEDULED;
            }

            if ($isSlotChanged && $normalizedStartAt !== null) {
                $this->saveWithinManagedWorkflow(
                    appointment: $lockedAppointment,
                    payload: $data,
                    context: $this->rescheduleAuditContext(
                        originalStartAt: $originalStartAt,
                        newStartAt: $normalizedStartAt,
                        reason: $reason,
                        force: false,
                        source: 'form',
                    ),
                );
            } else {
                $lockedAppointment->fill($data);
                $lockedAppointment->save();
            }

            return $lockedAppointment->fresh() ?? $lockedAppointment;
        }, 5);
    }

    public function reschedule(
        Appointment $appointment,
        CarbonInterface|string $startAt,
        bool $force = false,
        ?string $reason = null,
    ): Appointment {
        return DB::transaction(function () use ($appointment, $startAt, $force, $reason): Appointment {
            $lockedAppointment = $this->lockAppointment($appointment);
            $normalizedStartAt = $this->normalizeDateTime($startAt);
            $reason = trim((string) $reason);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reschedule_reason' => 'Vui lòng nhập lý do đổi lịch.',
                ]);
            }

            $originalStartAt = $lockedAppointment->date?->copy();
            $hasConflict = $this->hasDoctorConflict($lockedAppointment, $normalizedStartAt, true);

            if ($hasConflict && ! $force) {
                throw ValidationException::withMessages([
                    'date' => 'Khung giờ bị trùng lịch bác sĩ. Xác nhận override để tiếp tục.',
                ]);
            }

            $payload = [
                'date' => $normalizedStartAt,
                'status' => Appointment::STATUS_RESCHEDULED,
                'reschedule_reason' => $reason,
            ];

            if ($hasConflict) {
                $payload['overbooking_reason'] = 'Override từ màn hình calendar';
                $payload['overbooking_override_by'] = auth()->id();
                $payload['overbooking_override_at'] = now();
            }

            $this->saveWithinManagedWorkflow(
                appointment: $lockedAppointment,
                payload: $payload,
                context: $this->rescheduleAuditContext(
                    originalStartAt: $originalStartAt,
                    newStartAt: $normalizedStartAt,
                    reason: $reason,
                    force: $force,
                    source: 'calendar',
                ),
            );

            return $lockedAppointment->fresh() ?? $lockedAppointment;
        }, 5);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function transitionStatus(Appointment $appointment, string $status, array $context = []): Appointment
    {
        return DB::transaction(function () use ($appointment, $status, $context): Appointment {
            $lockedAppointment = $this->lockAppointment($appointment);
            $normalizedStatus = Appointment::normalizeStatus($status);

            if ($normalizedStatus === null) {
                throw ValidationException::withMessages([
                    'status' => 'Trạng thái lịch hẹn không hợp lệ.',
                ]);
            }

            $payload = [
                'status' => $normalizedStatus,
            ];

            if ($normalizedStatus === Appointment::STATUS_CONFIRMED) {
                $payload['confirmed_at'] = $context['confirmed_at'] ?? ($lockedAppointment->confirmed_at ?: now());
                $payload['confirmed_by'] = $context['confirmed_by'] ?? (auth()->id() ?: $lockedAppointment->confirmed_by);
            }

            if ($normalizedStatus === Appointment::STATUS_CANCELLED) {
                $payload['cancellation_reason'] = trim((string) ($context['reason'] ?? ''));
            }

            if (array_key_exists('note', $context)) {
                $payload['note'] = $this->appendOperationalNote(
                    currentNote: $lockedAppointment->note,
                    prefix: (string) ($context['note_prefix'] ?? ''),
                    appendedNote: trim((string) ($context['note'] ?? '')),
                );
            }

            $this->saveWithinManagedWorkflow(
                appointment: $lockedAppointment,
                payload: $payload,
                context: $this->statusTransitionAuditContext($normalizedStatus, $context),
            );

            return $lockedAppointment->fresh() ?? $lockedAppointment;
        }, 5);
    }

    public function hasDoctorConflict(
        Appointment $appointment,
        CarbonInterface|string $startAt,
        bool $lockForUpdate = false,
    ): bool {
        $normalizedStartAt = $this->normalizeDateTime($startAt);
        $duration = max(1, (int) ($appointment->duration_minutes ?? 30));
        $endAt = $normalizedStartAt->copy()->addMinutes($duration);

        return Appointment::query()
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->when($appointment->exists, fn ($query) => $query->where('id', '!=', $appointment->getKey()))
            ->where('doctor_id', $appointment->doctor_id)
            ->where('branch_id', $appointment->branch_id)
            ->whereIn('status', Appointment::statusesForQuery(Appointment::statusesOccupyingCapacity()))
            ->where('date', '<', $endAt->format('Y-m-d H:i:s'))
            ->where('date', '>=', $normalizedStartAt->copy()->subDay()->format('Y-m-d H:i:s'))
            ->get(['id', 'date', 'duration_minutes'])
            ->contains(function (Appointment $existingAppointment) use ($normalizedStartAt): bool {
                if (! $existingAppointment->date) {
                    return false;
                }

                $existingStartAt = $existingAppointment->date->copy();
                $existingEndAt = $existingStartAt
                    ->copy()
                    ->addMinutes(max(1, (int) ($existingAppointment->duration_minutes ?? 30)));

                return $existingEndAt->gt($normalizedStartAt);
            });
    }

    protected function lockAppointment(Appointment $appointment): Appointment
    {
        if (! $appointment->exists) {
            throw new ModelNotFoundException('Khong tim thay lich hen de khoa giao dich.');
        }

        return Appointment::query()
            ->lockForUpdate()
            ->findOrFail($appointment->getKey());
    }

    protected function normalizeDateTime(CarbonInterface|string $value): Carbon
    {
        return ($value instanceof CarbonInterface
            ? $value->toImmutable()->toMutable()
            : Carbon::parse($value))
            ->setTimezone(config('app.timezone'))
            ->seconds(0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractNormalizedStartAt(array $data): ?Carbon
    {
        if (! array_key_exists('date', $data) || blank($data['date'])) {
            return null;
        }

        return $this->normalizeDateTime($data['date']);
    }

    protected function appendOperationalNote(?string $currentNote, string $prefix, string $appendedNote): ?string
    {
        if ($appendedNote === '') {
            return $currentNote;
        }

        $normalizedPrefix = trim($prefix);
        $line = $normalizedPrefix !== '' ? $normalizedPrefix.': '.$appendedNote : $appendedNote;
        $existing = trim((string) $currentNote);

        return $existing === '' ? $line : $existing.PHP_EOL.$line;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    protected function rescheduleAuditContext(
        ?CarbonInterface $originalStartAt,
        CarbonInterface $newStartAt,
        string $reason,
        bool $force,
        string $source,
    ): array {
        return array_filter([
            'actor_id' => $this->resolveActorId(),
            'reason' => $reason,
            'trigger' => 'manual_reschedule',
            'audit_action' => \App\Models\AuditLog::ACTION_RESCHEDULE,
            'from_at' => $originalStartAt?->format('Y-m-d H:i:s'),
            'to_at' => $newStartAt->format('Y-m-d H:i:s'),
            'force' => $force,
            'source' => $source,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    protected function saveWithinManagedWorkflow(
        Appointment $appointment,
        array $payload,
        array $context = [],
    ): void {
        Appointment::runWithinManagedWorkflow(function () use ($appointment, $payload): void {
            $appointment->fill($payload);
            $appointment->save();
        }, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function statusTransitionAuditContext(string $status, array $context = []): array
    {
        $reason = trim((string) ($context['reason'] ?? ''));
        $trigger = data_get($context, 'trigger');

        return array_filter([
            'actor_id' => $this->resolveActorId($context),
            'reason' => $reason !== '' ? $reason : null,
            'trigger' => is_string($trigger) && trim($trigger) !== ''
                ? trim($trigger)
                : $this->defaultTriggerForStatus($status),
            'audit_action' => match ($status) {
                Appointment::STATUS_CANCELLED => \App\Models\AuditLog::ACTION_CANCEL,
                Appointment::STATUS_RESCHEDULED => \App\Models\AuditLog::ACTION_RESCHEDULE,
                Appointment::STATUS_NO_SHOW => \App\Models\AuditLog::ACTION_NO_SHOW,
                Appointment::STATUS_COMPLETED => \App\Models\AuditLog::ACTION_COMPLETE,
                default => null,
            },
        ], static fn (mixed $value): bool => $value !== null);
    }

    protected function defaultTriggerForStatus(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_CONFIRMED => 'manual_confirm',
            Appointment::STATUS_IN_PROGRESS => 'manual_start',
            Appointment::STATUS_COMPLETED => 'manual_complete',
            Appointment::STATUS_NO_SHOW => 'manual_no_show',
            Appointment::STATUS_CANCELLED => 'manual_cancel',
            Appointment::STATUS_RESCHEDULED => 'manual_reschedule',
            default => 'manual_transition',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveActorId(array $context = []): ?int
    {
        $candidate = $context['actor_id'] ?? auth()->id() ?? $context['confirmed_by'] ?? null;

        return is_numeric($candidate) ? (int) $candidate : null;
    }
}
