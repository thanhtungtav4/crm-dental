<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
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
            $lockedAppointment->fill($data);
            $lockedAppointment->save();

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
            ];

            if ($hasConflict) {
                $payload['overbooking_reason'] = 'Override từ màn hình calendar';
                $payload['overbooking_override_by'] = auth()->id();
                $payload['overbooking_override_at'] = now();
            }

            $lockedAppointment->fill($payload);
            $lockedAppointment->save();
            $this->recordRescheduleAudit(
                appointment: $lockedAppointment,
                originalStartAt: $originalStartAt,
                newStartAt: $normalizedStartAt,
                reason: $reason,
                force: $force,
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

    protected function recordRescheduleAudit(
        Appointment $appointment,
        ?CarbonInterface $originalStartAt,
        CarbonInterface $newStartAt,
        string $reason,
        bool $force,
    ): void {
        if (($originalStartAt?->format('Y-m-d H:i:s')) === $newStartAt->format('Y-m-d H:i:s')) {
            return;
        }

        $actorId = auth()->id();

        if (! $actorId) {
            return;
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_APPOINTMENT,
            entityId: (int) $appointment->id,
            action: AuditLog::ACTION_RESCHEDULE,
            actorId: $actorId,
            branchId: $appointment->branch_id ? (int) $appointment->branch_id : null,
            patientId: $appointment->patient_id ? (int) $appointment->patient_id : null,
            metadata: [
                'patient_id' => $appointment->patient_id,
                'customer_id' => $appointment->customer_id,
                'doctor_id' => $appointment->doctor_id,
                'branch_id' => $appointment->branch_id,
                'from_at' => $originalStartAt?->format('Y-m-d H:i:s'),
                'to_at' => $newStartAt->format('Y-m-d H:i:s'),
                'reason' => $reason,
                'force' => $force,
                'source' => 'calendar',
            ],
        );
    }
}
