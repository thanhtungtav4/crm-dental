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

    public function calendarViewState(): array
    {
        $metrics = $this->operationalStatusMetrics();
        $branches = $this->branchOptions();
        $doctors = $this->doctorOptions();

        return [
            'heading' => $this->getHeading(),
            'google_calendar_panel' => $this->googleCalendarPanel(),
            'metric_cards' => $this->metricCards($metrics),
            'filters_panel' => $this->filtersPanel($branches, $doctors),
            'shell_panel' => $this->shellPanel(),
            'reschedule_modal_panel' => $this->rescheduleModalPanel(),
            'metrics' => $metrics,
            'status_colors' => $this->statusColors(),
            'branches' => $branches,
            'doctors' => $doctors,
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function statusColors(): array
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
    protected function operationalStatusMetrics(): array
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

        $statusColors = $this->statusColors();

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

    /**
     * @return array{
     *     enabled: bool,
     *     label: string,
     *     badge_label: string,
     *     badge_classes: string
     * }
     */
    protected function googleCalendarPanel(): array
    {
        $isEnabled = $this->googleCalendarEnabled();

        return [
            'enabled' => $isEnabled,
            'label' => $this->googleCalendarSyncModeLabel(),
            'badge_label' => $isEnabled ? 'Đang bật' : 'Đang tắt',
            'badge_classes' => $isEnabled
                ? 'inline-flex items-center rounded-md bg-success-50 px-2 py-0.5 text-success-700 dark:bg-success-500/20 dark:text-success-300'
                : 'inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        ];
    }

    /**
     * @return array{
     *     create_url: string,
     *     modal_id: string,
     *     empty_appointment_title: string,
     *     empty_start_label: string,
     *     connection_error_message: string,
     *     reason_required_message: string,
     *     default_error_message: string,
     *     conflict_keyword: string,
     *     calendar_region_label: string,
     *     calendar_loading_label: string,
     *     status_filter_id: string,
     *     branch_filter_id: string,
     *     doctor_filter_id: string,
     *     all_status_label: string,
     *     all_branch_label: string,
     *     all_doctor_label: string
     * }
     */
    protected function shellPanel(): array
    {
        return [
            'create_url' => AppointmentResource::getUrl('create'),
            'modal_id' => 'appointment-calendar-reschedule-modal',
            'empty_appointment_title' => 'Lịch hẹn bệnh nhân',
            'empty_start_label' => 'Chưa chọn',
            'connection_error_message' => 'Không thể kết nối tới phiên làm việc.',
            'reason_required_message' => 'Vui lòng nhập lý do dời lịch hẹn.',
            'default_error_message' => 'Không thể dời lịch hẹn.',
            'conflict_keyword' => 'trùng lịch',
            'calendar_region_label' => 'Lưới lịch hẹn theo tuần',
            'calendar_loading_label' => 'Đang tải lại lịch hẹn',
            'status_filter_id' => 'calendar-filter-status',
            'branch_filter_id' => 'calendar-filter-branch',
            'doctor_filter_id' => 'calendar-filter-doctor',
            'all_status_label' => 'Tất cả',
            'all_branch_label' => 'Tất cả chi nhánh',
            'all_doctor_label' => 'Tất cả bác sĩ',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function rescheduleModalPanel(): array
    {
        return [
            'id' => 'appointment-calendar-reschedule-modal',
            'heading' => 'Dời lịch hẹn',
            'description' => 'Xác nhận thời gian mới và ghi lại lý do thay đổi trước khi cập nhật lịch hẹn.',
            'appointment_label' => 'Lịch hẹn',
            'start_label' => 'Thời gian mới',
            'empty_appointment_title' => 'Lịch hẹn bệnh nhân',
            'empty_start_label' => 'Chưa chọn',
            'doctor_label' => 'Bác sĩ',
            'branch_label' => 'Chi nhánh',
            'conflict_heading' => 'Khung giờ mới đang bị trùng lịch.',
            'conflict_note' => 'Nếu vẫn cần lưu, hệ thống sẽ ghi nhận đây là thao tác override.',
            'conflict_message_id' => 'calendar-reschedule-conflict-message',
            'conflict_note_id' => 'calendar-reschedule-conflict-note',
            'reason_label' => 'Lý do dời lịch',
            'reason_help' => 'Lý do sẽ được lưu vào lịch sử dời lịch để đội vận hành tra cứu lại khi cần.',
            'reason_help_id' => 'calendar-reschedule-reason-help',
            'reason_error_id' => 'calendar-reschedule-reason-error',
            'reason_placeholder' => 'Ví dụ: bệnh nhân xin đổi giờ, bác sĩ thay đổi lịch, điều phối qua chi nhánh khác...',
            'cancel_label' => 'Hủy',
            'submit_label' => 'Lưu thay đổi',
            'submit_override_label' => 'Override và lưu',
            'submitting_label' => 'Đang cập nhật...',
        ];
    }

    protected function googleCalendarEnabled(): bool
    {
        return ClinicRuntimeSettings::isGoogleCalendarEnabled();
    }

    protected function googleCalendarSyncModeLabel(): string
    {
        $options = ClinicRuntimeSettings::googleCalendarSyncModeOptions();
        $fallback = reset($options);

        return $options[ClinicRuntimeSettings::googleCalendarSyncMode()] ?? (is_string($fallback) ? $fallback : '');
    }

    /**
     * @param  array<string, int>  $metrics
     * @return array<int, array{label:string,value:string,container_classes:string,label_classes:string,value_classes:string}>
     */
    protected function metricCards(array $metrics): array
    {
        return [
            [
                'label' => 'Tổng lịch tuần',
                'value' => number_format($metrics['total']),
                'container_classes' => 'rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60',
                'label_classes' => 'text-xs text-gray-500',
                'value_classes' => 'text-xl font-semibold',
            ],
            [
                'label' => 'Đã đặt',
                'value' => number_format($metrics['scheduled']),
                'container_classes' => 'rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10',
                'label_classes' => 'text-xs text-amber-700 dark:text-amber-300',
                'value_classes' => 'text-xl font-semibold text-amber-700 dark:text-amber-300',
            ],
            [
                'label' => 'Đã xác nhận',
                'value' => number_format($metrics['confirmed']),
                'container_classes' => 'rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-400/30 dark:bg-blue-500/10',
                'label_classes' => 'text-xs text-blue-700 dark:text-blue-300',
                'value_classes' => 'text-xl font-semibold text-blue-700 dark:text-blue-300',
            ],
            [
                'label' => 'Đang khám',
                'value' => number_format($metrics['in_progress']),
                'container_classes' => 'rounded-xl border border-cyan-200 bg-cyan-50 p-3 dark:border-cyan-400/30 dark:bg-cyan-500/10',
                'label_classes' => 'text-xs text-cyan-700 dark:text-cyan-300',
                'value_classes' => 'text-xl font-semibold text-cyan-700 dark:text-cyan-300',
            ],
            [
                'label' => 'Hoàn thành',
                'value' => number_format($metrics['completed']),
                'container_classes' => 'rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10',
                'label_classes' => 'text-xs text-emerald-700 dark:text-emerald-300',
                'value_classes' => 'text-xl font-semibold text-emerald-700 dark:text-emerald-300',
            ],
            [
                'label' => 'No-show',
                'value' => number_format($metrics['no_show']),
                'container_classes' => 'rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10',
                'label_classes' => 'text-xs text-rose-700 dark:text-rose-300',
                'value_classes' => 'text-xl font-semibold text-rose-700 dark:text-rose-300',
            ],
            [
                'label' => 'Đã hủy',
                'value' => number_format($metrics['cancelled'] ?? 0),
                'container_classes' => 'rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-600/30 dark:bg-gray-500/10',
                'label_classes' => 'text-xs text-gray-600 dark:text-gray-400',
                'value_classes' => 'text-xl font-semibold text-gray-600 dark:text-gray-400',
            ],
        ];
    }

    /**
     * @param  array<int|string, string>  $branches
     * @param  array<int|string, string>  $doctors
     * @return array{
     *     heading: string,
     *     description: string,
     *     labelled_by: string,
     *     described_by: string,
     *     active_summary_label: string,
     *     reset_label: string,
     *     status_filter: array{id:string,label:string},
     *     branch_filter: array{id:string,label:string},
     *     doctor_filter: array{id:string,label:string},
     *     status_options: array<int, array{value:string,label:string}>,
     *     branch_options: array<int, array{value:string,label:string}>,
     *     doctor_options: array<int, array{value:string,label:string}>
     * }
     */
    protected function filtersPanel(array $branches, array $doctors): array
    {
        return [
            'heading' => 'Bộ lọc lịch hẹn',
            'description' => 'Lọc nhanh theo trạng thái, chi nhánh hoặc bác sĩ mà không làm thay đổi các chỉ số tổng quan tuần.',
            'labelled_by' => 'calendar-filters-heading',
            'described_by' => 'calendar-filters-description',
            'active_summary_label' => 'Đang xem',
            'reset_label' => 'Đặt lại',
            'status_filter' => [
                'id' => 'calendar-filter-status',
                'label' => 'Trạng thái',
            ],
            'branch_filter' => [
                'id' => 'calendar-filter-branch',
                'label' => 'Chi nhánh',
            ],
            'doctor_filter' => [
                'id' => 'calendar-filter-doctor',
                'label' => 'Bác sĩ',
            ],
            'status_options' => [
                ['value' => '', 'label' => 'Tất cả'],
                ['value' => Appointment::STATUS_SCHEDULED, 'label' => 'Đã đặt'],
                ['value' => Appointment::STATUS_CONFIRMED, 'label' => 'Đã xác nhận'],
                ['value' => Appointment::STATUS_IN_PROGRESS, 'label' => 'Đang khám'],
                ['value' => Appointment::STATUS_COMPLETED, 'label' => 'Hoàn thành'],
                ['value' => Appointment::STATUS_NO_SHOW, 'label' => 'No-show'],
                ['value' => Appointment::STATUS_CANCELLED, 'label' => 'Đã hủy'],
                ['value' => Appointment::STATUS_RESCHEDULED, 'label' => 'Đã hẹn lại'],
            ],
            'branch_options' => [
                ['value' => '', 'label' => 'Tất cả chi nhánh'],
                ...array_map(
                    fn (int|string $branchId, string $branchName): array => [
                        'value' => (string) $branchId,
                        'label' => $branchName,
                    ],
                    array_keys($branches),
                    $branches,
                ),
            ],
            'doctor_options' => [
                ['value' => '', 'label' => 'Tất cả bác sĩ'],
                ...array_map(
                    fn (int|string $doctorId, string $doctorName): array => [
                        'value' => (string) $doctorId,
                        'label' => $doctorName,
                    ],
                    array_keys($doctors),
                    $doctors,
                ),
            ],
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
