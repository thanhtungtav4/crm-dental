<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use App\Services\AppointmentReportReadModelService;
use App\Services\AppointmentSchedulingService;
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
        $options = ClinicRuntimeSettings::googleCalendarSyncModeOptions();
        $fallback = reset($options);

        return $options[ClinicRuntimeSettings::googleCalendarSyncMode()] ?? (is_string($fallback) ? $fallback : '');
    }

    /**
     * @return array{
     *     metrics: array<string, int>,
     *     status_colors: array<string, array<int, string>>,
     *     branches: array<int|string, string>,
     *     doctors: array<int|string, string>
     * }
     */
    public function calendarViewState(): array
    {
        return [
            'metrics' => $this->getOperationalStatusMetrics(),
            'status_colors' => $this->getStatusColors(),
            'branches' => $this->branchOptions(),
            'doctors' => $this->doctorOptions(),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getStatusColors(): array
    {
        return [
            Appointment::STATUS_SCHEDULED => ['#3b82f6', '#1d4ed8'],
            Appointment::STATUS_CONFIRMED => ['#10b981', '#059669'],
            Appointment::STATUS_IN_PROGRESS => ['#f59e0b', '#d97706'],
            Appointment::STATUS_COMPLETED => ['#6b7280', '#4b5563'],
            Appointment::STATUS_RESCHEDULED => ['#8b5cf6', '#7c3aed'],
            Appointment::STATUS_CANCELLED => ['#ef4444', '#b91c1c'],
            Appointment::STATUS_NO_SHOW => ['#9ca3af', '#6b7280'],
        ];
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
        return app(AppointmentReportReadModelService::class)->operationalStatusMetrics(
            $this->accessibleBranchIds(),
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString(),
        );
    }

    /**
     * @param  array{status?:string,branchId?:string|int,doctorId?:string|int}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarEvents(string $startAtIso, string $endAtIso, array $filters = []): array
    {
        $startAt = Carbon::parse($startAtIso)->setTimezone(config('app.timezone'))->startOfMinute();
        $endAt = Carbon::parse($endAtIso)->setTimezone(config('app.timezone'))->startOfMinute();

        $query = $this->scopeBranchAccess(
            Appointment::query()
                ->with(['patient:id,full_name,phone,email', 'doctor:id,name,phone,specialty', 'branch:id,name'])
                ->where('date', '>=', $startAt->format('Y-m-d H:i:s'))
                ->where('date', '<', $endAt->format('Y-m-d H:i:s'))
                ->orderBy('date')
        );

        $statusFilter = Appointment::normalizeStatus((string) ($filters['status'] ?? ''));
        if ($statusFilter !== null && $statusFilter !== '') {
            $query->whereIn('status', Appointment::statusesForQuery([$statusFilter]));
        }

        $branchFilter = is_numeric($filters['branchId'] ?? null) ? (int) $filters['branchId'] : null;
        if ($branchFilter !== null) {
            $query->where('branch_id', $branchFilter);
        }

        $doctorFilter = is_numeric($filters['doctorId'] ?? null) ? (int) $filters['doctorId'] : null;
        if ($doctorFilter !== null) {
            $query->where('doctor_id', $doctorFilter);
        }

        $statusColors = $this->getStatusColors();

        return $query
            ->get()
            ->map(fn (Appointment $appointment): array => $this->mapAppointmentToEvent($appointment, $statusColors))
            ->all();
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function rescheduleAppointmentFromCalendar(
        int $appointmentId,
        string $startAtIso,
        bool $force = false,
        ?string $reason = null,
    ): array {
        $appointment = Appointment::query()->findOrFail($appointmentId);
        $this->authorize('update', $appointment);

        $startAt = Carbon::parse($startAtIso)
            ->setTimezone(config('app.timezone'))
            ->seconds(0);

        try {
            app(AppointmentSchedulingService::class)->reschedule(
                appointment: $appointment,
                startAt: $startAt,
                force: $force,
                reason: $reason,
            );
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
        $branchIds = $this->accessibleBranchIds();

        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }

    /**
     * @return array<int, int>|null
     */
    protected function accessibleBranchIds(): ?array
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return null;
        }

        return BranchAccess::accessibleBranchIds($authUser);
    }

    /**
     * @return array<int|string, string>
     */
    protected function branchOptions(): array
    {
        $branchIds = $this->accessibleBranchIds();

        return Branch::query()
            ->when(
                $branchIds !== null,
                fn (Builder $query) => $branchIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('id', $branchIds),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected function doctorOptions(): array
    {
        $branchIds = $this->accessibleBranchIds();

        return User::query()
            ->role('Doctor')
            ->when(
                $branchIds !== null,
                function (Builder $query) use ($branchIds): Builder {
                    if ($branchIds === []) {
                        return $query->whereRaw('1 = 0');
                    }

                    return $query->where(function (Builder $doctorQuery) use ($branchIds): void {
                        $doctorQuery
                            ->whereIn('branch_id', $branchIds)
                            ->orWhereHas('activeDoctorBranchAssignments', fn (Builder $assignmentQuery) => $assignmentQuery->whereIn('branch_id', $branchIds));
                    });
                }
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<string, array<int, string>>  $statusColors
     * @return array<string, mixed>
     */
    protected function mapAppointmentToEvent(Appointment $appointment, array $statusColors): array
    {
        $status = Appointment::normalizeStatus($appointment->status) ?? Appointment::DEFAULT_STATUS;
        $statusLabel = Appointment::statusLabel($status);
        [$bg, $bd] = $statusColors[$status] ?? $statusColors[Appointment::DEFAULT_STATUS];

        $startAt = $appointment->date?->copy();
        $endAt = $startAt?->copy()->addMinutes(max(1, (int) ($appointment->duration_minutes ?? 30)));

        return [
            'id' => $appointment->id,
            'title' => $appointment->patient?->full_name ?: 'Chưa rõ bệnh nhân',
            'start' => $startAt?->toIso8601String(),
            'end' => $endAt?->toIso8601String(),
            'url' => AppointmentResource::getUrl('edit', ['record' => $appointment->id]),
            'backgroundColor' => $bg,
            'borderColor' => $bd,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'status' => $status,
                'statusLabel' => $statusLabel,
                'note' => $appointment->note,
                'patient' => $appointment->patient?->full_name,
                'doctor' => $appointment->doctor?->name,
                'branch' => $appointment->branch?->name,
                'doctorPhone' => $appointment->doctor?->phone,
                'branch_id' => $appointment->branch_id,
                'doctor_id' => $appointment->doctor_id,
            ],
        ];
    }
}
