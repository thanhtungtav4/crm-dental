<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CalendarAppointments extends Page
{
    protected static string $resource = AppointmentResource::class;

    protected string $view = 'filament.appointments.calendar';

    protected static ?string $navigationLabel = 'Lịch hẹn tổng';

    protected static string|\UnitEnum|null $navigationGroup = 'Hoạt động hàng ngày';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'calendar';

    public function getHeading(): string
    {
        return 'Lịch hẹn tổng';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function isGoogleCalendarEnabled(): bool
    {
        return ClinicRuntimeSettings::isGoogleCalendarEnabled();
    }

    public function getGoogleCalendarSyncModeLabel(): string
    {
        return match (ClinicRuntimeSettings::googleCalendarSyncMode()) {
            'one_way_to_google' => 'Một chiều: CRM -> Google',
            'one_way_to_crm' => 'Một chiều: Google -> CRM',
            default => 'Hai chiều',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Thêm lịch hẹn')
                ->icon('heroicon-o-plus')
                ->url(AppointmentResource::getUrl('create')),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getOperationalStatusMetrics(): array
    {
        $baseQuery = $this->scopeBranchAccess(
            Appointment::query()->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
        );

        return [
            'total' => (int) (clone $baseQuery)->count(),
            'scheduled' => (int) (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_SCHEDULED]))->count(),
            'confirmed' => (int) (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_CONFIRMED]))->count(),
            'in_progress' => (int) (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_IN_PROGRESS]))->count(),
            'completed' => (int) (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_COMPLETED]))->count(),
            'no_show' => (int) (clone $baseQuery)->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_NO_SHOW]))->count(),
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function rescheduleAppointmentFromCalendar(int $appointmentId, string $startAtIso, bool $force = false): array
    {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('update', $appointment);

        $startAt = Carbon::parse($startAtIso)->seconds(0);
        $duration = max(1, (int) ($appointment->duration_minutes ?? 30));
        $endAt = $startAt->copy()->addMinutes($duration);

        $hasConflict = Appointment::query()
            ->where('id', '!=', $appointment->id)
            ->where('doctor_id', $appointment->doctor_id)
            ->where('branch_id', $appointment->branch_id)
            ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
            ->where('date', '<', $endAt->format('Y-m-d H:i:s'))
            ->where('date', '>=', $startAt->copy()->subDay()->format('Y-m-d H:i:s'))
            ->get(['id', 'date', 'duration_minutes'])
            ->contains(function (Appointment $existing) use ($startAt): bool {
                if (! $existing->date) {
                    return false;
                }

                $existingStart = $existing->date->copy();
                $existingEnd = $existingStart->copy()->addMinutes(max(1, (int) ($existing->duration_minutes ?? 30)));

                return $existingEnd->gt($startAt);
            });

        if ($hasConflict && ! $force) {
            return [
                'ok' => false,
                'message' => 'Khung giờ bị trùng lịch bác sĩ. Xác nhận override để tiếp tục.',
            ];
        }

        try {
            $appointment->forceFill([
                'date' => $startAt,
                'status' => Appointment::STATUS_RESCHEDULED,
                'reschedule_reason' => $hasConflict
                    ? 'Điều phối từ lịch ngày/tuần (override conflict)'
                    : 'Điều phối từ lịch ngày/tuần',
                'overbooking_reason' => $hasConflict ? 'Override từ màn hình calendar' : $appointment->overbooking_reason,
                'overbooking_override_by' => $hasConflict ? auth()->id() : $appointment->overbooking_override_by,
                'overbooking_override_at' => $hasConflict ? now() : $appointment->overbooking_override_at,
            ])->save();
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'message' => (string) Arr::first(Arr::flatten($exception->errors())),
            ];
        }

        Notification::make()
            ->title('Đã dời lịch hẹn')
            ->success()
            ->send();

        return [
            'ok' => true,
            'message' => 'Đã cập nhật lịch hẹn.',
        ];
    }

    protected function scopeBranchAccess(Builder $query): Builder
    {
        $authUser = auth()->user();
        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }
}
