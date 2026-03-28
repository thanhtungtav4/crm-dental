<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use App\Support\Exports\ExportsCsv;
use Carbon\CarbonInterface;
use Filament\Actions\Action as HeaderAction;
use Filament\Actions\Action as TableAction;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerCare extends Page implements HasTable
{
    use ExportsCsv;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Chăm sóc khách hàng';

    protected static string|\UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'customer-care';

    protected string $view = 'filament.pages.customer-care';

    public string $activeTab = 'care_schedule';

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->can('ViewAny:Note')
            && $authUser->hasAnyAccessibleBranch();
    }

    public function getHeading(): string
    {
        return 'Chăm sóc khách hàng';
    }

    public function getSubheading(): string
    {
        return 'Theo dõi lịch chăm sóc, nhắc lịch hẹn và các luồng CSKH tự động.';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function mount(): void
    {
        $tab = request()->get('tab');
        if (is_string($tab) && array_key_exists($tab, $this->getTabs())) {
            $this->activeTab = $tab;
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->getTabs())) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('export')
                ->label('Xuất CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'care_schedule' => 'Lịch chăm sóc',
            'priority_queue' => 'Queue ưu tiên',
            'appointment_reminder' => 'Nhắc lịch hẹn',
            'prescription_reminder' => 'Nhắc lịch uống thuốc',
            'post_treatment_followup' => 'Hỏi thăm sau điều trị',
            'birthday' => 'Ngày sinh nhật',
        ];
    }

    /**
     * @return array{
     *   total_open:int,
     *   overdue:int,
     *   due_today:int,
     *   unassigned:int,
     *   owned_by_me:int,
     *   priority_no_show:int,
     *   priority_recall:int,
     *   priority_follow_up:int,
     *   by_channel:array<int, array{label:string,total:int}>,
     *   by_branch:array<int, array{label:string,total:int}>,
     *   by_staff:array<int, array{label:string,total:int}>
     * }
     */
    public function getSlaSummaryProperty(): array
    {
        $baseQuery = $this->baseCareTicketQuery();
        $now = now();
        $today = $now->toDateString();

        $totalOpen = (clone $baseQuery)->count();
        $overdue = (clone $baseQuery)
            ->whereNotNull('care_at')
            ->where('care_at', '<', $now)
            ->count();
        $dueToday = (clone $baseQuery)
            ->whereDate('care_at', $today)
            ->count();
        $unassigned = (clone $baseQuery)
            ->whereNull('user_id')
            ->count();
        $ownedByMe = $this->careSummaryOwnedByCurrentUser($baseQuery);

        $priorityNoShow = (clone $baseQuery)->where('care_type', 'no_show_recovery')->count();
        $priorityRecall = (clone $baseQuery)->where('care_type', 'recall_recare')->count();
        $priorityFollowUp = (clone $baseQuery)->where('care_type', 'treatment_plan_follow_up')->count();

        $byChannelRows = (clone $baseQuery)
            ->selectRaw('COALESCE(care_channel, "other") as metric_key, COUNT(*) as total')
            ->groupBy('metric_key')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byBranchRows = (clone $baseQuery)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id as metric_key, COUNT(*) as total')
            ->groupBy('metric_key')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $byStaffRows = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->selectRaw('user_id as metric_key, COUNT(*) as total')
            ->groupBy('metric_key')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $branchNames = Branch::query()
            ->whereIn('id', $byBranchRows->pluck('metric_key')->filter()->map(static fn ($id): int => (int) $id)->all())
            ->pluck('name', 'id');

        $staffNames = User::query()
            ->whereIn('id', $byStaffRows->pluck('metric_key')->filter()->map(static fn ($id): int => (int) $id)->all())
            ->pluck('name', 'id');

        return [
            'total_open' => (int) $totalOpen,
            'overdue' => (int) $overdue,
            'due_today' => (int) $dueToday,
            'unassigned' => (int) $unassigned,
            'owned_by_me' => (int) $ownedByMe,
            'priority_no_show' => (int) $priorityNoShow,
            'priority_recall' => (int) $priorityRecall,
            'priority_follow_up' => (int) $priorityFollowUp,
            'by_channel' => $byChannelRows
                ->map(fn ($row): array => [
                    'label' => $this->formatCareChannel((string) $row->metric_key),
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all(),
            'by_branch' => $byBranchRows
                ->map(fn ($row): array => [
                    'label' => (string) ($branchNames[(int) $row->metric_key] ?? 'Không xác định'),
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all(),
            'by_staff' => $byStaffRows
                ->map(fn ($row): array => [
                    'label' => (string) ($staffNames[(int) $row->metric_key] ?? 'Chưa phân công'),
                    'total' => (int) $row->total,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return match ($this->activeTab) {
            'appointment_reminder' => $this->careTicketQueryByType('appointment_reminder', Appointment::class),
            'prescription_reminder' => $this->careTicketQueryByType('medication_reminder', Prescription::class),
            'post_treatment_followup' => $this->careTicketQueryByType('post_treatment_follow_up', TreatmentSession::class),
            'birthday' => $this->careTicketQueryByType('birthday_care', Patient::class),
            'priority_queue' => $this->baseCareTicketQuery()
                ->with($this->careTicketRelations())
                ->whereIn('care_type', array_keys($this->priorityCareTypeOptions())),
            default => $this->applyDirectBranchScope(Note::query(), 'branch_id')
                ->with($this->careTicketRelations())
                ->whereNotNull('patient_id')
                ->where(function (Builder $query): void {
                    $query->whereNull('care_type');

                    $careScheduleTypes = $this->defaultCareScheduleTypes();

                    if ($careScheduleTypes !== []) {
                        $query->orWhereIn('care_type', $careScheduleTypes);
                    }
                }),
        };
    }

    protected function getTableColumns(): array
    {
        return match ($this->activeTab) {
            'priority_queue' => $this->getPriorityQueueColumns(),
            'appointment_reminder', 'prescription_reminder', 'post_treatment_followup', 'birthday' => $this->getCareScheduleColumns(),
            default => $this->getCareScheduleColumns(),
        };
    }

    protected function getTableFilters(): array
    {
        return match ($this->activeTab) {
            'priority_queue' => $this->getPriorityQueueFilters(),
            'appointment_reminder', 'prescription_reminder', 'post_treatment_followup', 'birthday' => $this->getCareScheduleFilters(),
            default => $this->getCareScheduleFilters(),
        };
    }

    public function exportCsv(): StreamedResponse
    {
        return $this->streamCsv(
            $this->getExportFileName(),
            $this->getExportColumns(),
            $this->getTableQueryForExport(),
        );
    }

    protected function getExportFileName(): string
    {
        return 'cskh_'.$this->activeTab.'_'.now()->format('Ymd_His').'.csv';
    }

    protected function getExportColumns(): array
    {
        return match ($this->activeTab) {
            'priority_queue' => $this->getPriorityQueueExportColumns(),
            'appointment_reminder', 'prescription_reminder', 'post_treatment_followup', 'birthday' => $this->getCareScheduleExportColumns(),
            default => $this->getCareScheduleExportColumns(),
        };
    }

    protected function getTableActions(): array
    {
        return [
            TableAction::make('view_patient')
                ->label('Xem hồ sơ')
                ->icon('heroicon-o-user')
                ->url(fn ($record) => $this->getPatientUrl($record))
                ->visible(fn ($record) => (bool) $this->resolvePatientId($record))
                ->openUrlInNewTab(),
        ];
    }

    protected function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->actions($this->getTableActions())
            ->defaultSort(
                $this->activeTab === 'priority_queue' ? 'care_at' : 'created_at',
                $this->activeTab === 'priority_queue' ? 'asc' : 'desc',
            )
            ->emptyStateHeading('Chưa có dữ liệu')
            ->emptyStateDescription('Dữ liệu chăm sóc khách hàng sẽ hiển thị tại đây.');
    }

    protected function getCareScheduleExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->patient?->patient_code],
            ['label' => 'Họ tên', 'value' => fn ($record) => $record->patient?->full_name],
            ['label' => 'Điện thoại', 'value' => fn ($record) => $record->patient?->phone],
            ['label' => 'Loại chăm sóc', 'value' => fn ($record) => $this->formatCareType($record->care_type)],
            ['label' => 'Trạng thái chăm sóc', 'value' => fn ($record) => $this->formatCareStatus($record->care_status)],
            ['label' => 'Kênh chăm sóc', 'value' => fn ($record) => $this->formatCareChannel($record->care_channel)],
            ['label' => 'Thời gian chăm sóc', 'value' => fn ($record) => $record->care_at ?? $record->created_at],
            ['label' => 'Nhân viên chăm sóc', 'value' => fn ($record) => $record->user?->name],
            ['label' => 'Nội dung', 'value' => fn ($record) => $record->content],
        ];
    }

    protected function getPriorityQueueExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->patient?->patient_code],
            ['label' => 'Họ tên', 'value' => fn ($record) => $record->patient?->full_name],
            ['label' => 'Điện thoại', 'value' => fn ($record) => $record->patient?->phone],
            ['label' => 'Chi nhánh', 'value' => fn ($record) => $record->branch?->name],
            ['label' => 'Loại ưu tiên', 'value' => fn ($record) => $this->formatCareType($record->care_type)],
            ['label' => 'Trạng thái', 'value' => fn ($record) => $this->formatCareStatus($record->care_status)],
            ['label' => 'Ưu tiên xử lý', 'value' => fn ($record) => $this->formatPriorityQueueBucket($this->resolvePriorityQueueBucket($record))],
            ['label' => 'Kênh', 'value' => fn ($record) => $this->formatCareChannel($record->care_channel)],
            ['label' => 'Deadline', 'value' => fn ($record) => $record->care_at],
            ['label' => 'SLA', 'value' => fn ($record) => $this->formatPriorityQueueSla($record)],
            ['label' => 'Nhân viên', 'value' => fn ($record) => $record->user?->name],
            ['label' => 'Nội dung', 'value' => fn ($record) => $record->content],
        ];
    }

    protected function getAppointmentExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->patient?->patient_code],
            ['label' => 'Họ tên', 'value' => fn ($record) => $record->patient?->full_name],
            ['label' => 'Điện thoại', 'value' => fn ($record) => $record->patient?->phone],
            ['label' => 'Loại chăm sóc', 'value' => fn () => $this->formatCareType('appointment_reminder')],
            ['label' => 'Trạng thái chăm sóc', 'value' => fn ($record) => $this->formatAppointmentStatus($record->status)],
            ['label' => 'Kênh chăm sóc', 'value' => fn () => $this->formatCareChannel($this->defaultCareChannel())],
            ['label' => 'Thời gian chăm sóc', 'value' => fn ($record) => $record->date],
            ['label' => 'Nhân viên chăm sóc', 'value' => fn ($record) => $record->assignedTo?->name],
            ['label' => 'Nội dung', 'value' => fn ($record) => $record->note],
            ['label' => 'Thời gian hẹn', 'value' => fn ($record) => $record->time_range_label],
            ['label' => 'Bác sĩ', 'value' => fn ($record) => $record->doctor?->name],
            ['label' => 'Trạng thái lịch', 'value' => fn ($record) => $this->formatAppointmentStatus($record->status)],
        ];
    }

    protected function getPrescriptionExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->patient?->patient_code],
            ['label' => 'Họ tên', 'value' => fn ($record) => $record->patient?->full_name],
            ['label' => 'Điện thoại', 'value' => fn ($record) => $record->patient?->phone],
            ['label' => 'Loại chăm sóc', 'value' => fn () => $this->formatCareType('medication_reminder')],
            ['label' => 'Trạng thái chăm sóc', 'value' => fn () => $this->formatCareStatus(Note::CARE_STATUS_NOT_STARTED)],
            ['label' => 'Kênh chăm sóc', 'value' => fn () => $this->formatCareChannel($this->defaultCareChannel())],
            ['label' => 'Thời gian chăm sóc', 'value' => fn ($record) => $record->treatment_date],
            ['label' => 'Nhân viên chăm sóc', 'value' => fn ($record) => $record->doctor?->name],
            ['label' => 'Nội dung', 'value' => fn ($record) => $record->notes],
            ['label' => 'Ngày tạo đơn thuốc', 'value' => fn ($record) => $record->created_at],
            ['label' => 'Tên đơn thuốc', 'value' => fn ($record) => $record->prescription_name],
        ];
    }

    protected function getFollowupExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->treatmentPlan?->patient?->patient_code],
            ['label' => 'Họ tên', 'value' => fn ($record) => $record->treatmentPlan?->patient?->full_name],
            ['label' => 'Điện thoại', 'value' => fn ($record) => $record->treatmentPlan?->patient?->phone],
            ['label' => 'Loại chăm sóc', 'value' => fn () => $this->formatCareType('post_treatment_follow_up')],
            ['label' => 'Trạng thái chăm sóc', 'value' => fn () => $this->formatCareStatus(Note::CARE_STATUS_NOT_STARTED)],
            ['label' => 'Kênh chăm sóc', 'value' => fn () => $this->formatCareChannel($this->defaultCareChannel())],
            ['label' => 'Thời gian chăm sóc', 'value' => fn ($record) => $record->performed_at],
            ['label' => 'Nhân viên chăm sóc', 'value' => fn ($record) => $record->doctor?->name],
            ['label' => 'Nội dung', 'value' => fn ($record) => $record->notes],
            ['label' => 'Ngày điều trị', 'value' => fn ($record) => $record->performed_at],
            ['label' => 'Tên thủ thuật', 'value' => fn ($record) => $record->planItem?->service?->name],
            ['label' => 'Bác sĩ thực hiện', 'value' => fn ($record) => $record->doctor?->name],
            ['label' => 'Thời gian dự kiến chăm sóc', 'value' => fn ($record) => $record->performed_at?->copy()?->addDays(3)],
        ];
    }

    protected function getBirthdayExportColumns(): array
    {
        return [
            ['label' => 'Mã hồ sơ', 'value' => fn ($record) => $record->patient_code],
            ['label' => 'Họ tên', 'value' => fn ($record) => $record->full_name],
            ['label' => 'Điện thoại', 'value' => fn ($record) => $record->phone],
            ['label' => 'Loại chăm sóc', 'value' => fn () => $this->formatCareType('birthday_care')],
            ['label' => 'Trạng thái chăm sóc', 'value' => fn () => $this->formatCareStatus(Note::CARE_STATUS_NOT_STARTED)],
            ['label' => 'Kênh chăm sóc', 'value' => fn () => $this->formatCareChannel($this->defaultCareChannel())],
            ['label' => 'Thời gian chăm sóc', 'value' => fn ($record) => $record->birthday],
            ['label' => 'Nhân viên chăm sóc', 'value' => fn ($record) => $record->ownerStaff?->name],
            ['label' => 'Nội dung', 'value' => fn ($record) => $record->note],
            ['label' => 'Ngày sinh nhật', 'value' => fn ($record) => $record->birthday],
        ];
    }

    protected function getCareScheduleColumns(): array
    {
        return [
            TextColumn::make('patient.patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.full_name')
                ->label('Họ tên')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.phone')
                ->label('Điện thoại')
                ->searchable(query: fn (Builder $query, string $search): Builder => $this->applyPatientPhoneSearch($query, $search)),
            TextColumn::make('care_type')
                ->label('Loại chăm sóc')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatCareType($state)),
            TextColumn::make('care_status')
                ->label('Trạng thái chăm sóc')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatCareStatus($state))
                ->color(fn ($state) => $this->getCareStatusColor($state)),
            TextColumn::make('care_channel')
                ->label('Kênh chăm sóc')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatCareChannel($state)),
            TextColumn::make('care_at')
                ->label('Thời gian chăm sóc')
                ->getStateUsing(fn ($record) => $record->care_at ?? $record->created_at)
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('user.name')
                ->label('Nhân viên chăm sóc')
                ->sortable(),
            TextColumn::make('content')
                ->label('Nội dung')
                ->limit(80)
                ->wrap(),
        ];
    }

    protected function getPriorityQueueColumns(): array
    {
        return [
            TextColumn::make('patient.patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.full_name')
                ->label('Họ tên')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.phone')
                ->label('Điện thoại')
                ->searchable(query: fn (Builder $query, string $search): Builder => $this->applyPatientPhoneSearch($query, $search)),
            TextColumn::make('branch.name')
                ->label('Chi nhánh')
                ->default('-')
                ->sortable(),
            TextColumn::make('care_type')
                ->label('Loại ưu tiên')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatCareType($state)),
            TextColumn::make('care_status')
                ->label('Trạng thái')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatCareStatus($state))
                ->color(fn ($state) => $this->getCareStatusColor($state)),
            TextColumn::make('ownership_status')
                ->label('Phụ trách')
                ->state(fn ($record): string => $this->resolvePriorityQueueOwnershipStatus($record))
                ->badge()
                ->formatStateUsing(fn (string $state): string => $this->formatPriorityQueueOwnershipStatus($state))
                ->color(fn (string $state): string => $this->getPriorityQueueOwnershipStatusColor($state)),
            TextColumn::make('triage_bucket')
                ->label('Ưu tiên xử lý')
                ->state(fn ($record): string => $this->resolvePriorityQueueBucket($record))
                ->badge()
                ->formatStateUsing(fn (string $state): string => $this->formatPriorityQueueBucket($state))
                ->color(fn (string $state): string => $this->getPriorityQueueBucketColor($state)),
            TextColumn::make('care_channel')
                ->label('Kênh')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatCareChannel($state)),
            TextColumn::make('care_at')
                ->label('Deadline')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('overdue_by')
                ->label('SLA')
                ->getStateUsing(fn ($record): string => $this->formatPriorityQueueSla($record))
                ->badge()
                ->color(fn ($record): string => $this->getPriorityQueueSlaColor($record)),
            TextColumn::make('user.name')
                ->label('Nhân viên')
                ->default('Chưa phân công')
                ->sortable(),
            TextColumn::make('content')
                ->label('Nội dung')
                ->limit(80)
                ->wrap(),
        ];
    }

    protected function getAppointmentColumns(): array
    {
        return [
            TextColumn::make('patient.patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.full_name')
                ->label('Họ tên')
                ->searchable(),
            TextColumn::make('patient.phone')
                ->label('Điện thoại')
                ->searchable(),
            TextColumn::make('care_type')
                ->label('Loại chăm sóc')
                ->badge()
                ->getStateUsing(fn () => 'appointment_reminder')
                ->formatStateUsing(fn ($state) => $this->formatCareType($state)),
            TextColumn::make('status')
                ->label('Trạng thái chăm sóc')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatAppointmentStatus($state))
                ->color(fn ($state) => $this->getAppointmentStatusColor($state)),
            TextColumn::make('care_channel')
                ->label('Kênh chăm sóc')
                ->badge()
                ->getStateUsing(fn () => $this->defaultCareChannel())
                ->formatStateUsing(fn ($state) => $this->formatCareChannel($state)),
            TextColumn::make('date')
                ->label('Thời gian chăm sóc')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('assignedTo.name')
                ->label('Nhân viên chăm sóc')
                ->sortable(),
            TextColumn::make('note')
                ->label('Nội dung')
                ->limit(80)
                ->wrap(),
            TextColumn::make('time_range_label')
                ->label('Thời gian hẹn'),
            TextColumn::make('doctor.name')
                ->label('Bác sĩ')
                ->sortable(),
            TextColumn::make('status')
                ->label('Trạng thái lịch')
                ->badge()
                ->formatStateUsing(fn ($state) => $this->formatAppointmentStatus($state))
                ->color(fn ($state) => $this->getAppointmentStatusColor($state)),
        ];
    }

    protected function getPrescriptionColumns(): array
    {
        return [
            TextColumn::make('patient.patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('patient.full_name')
                ->label('Họ tên')
                ->searchable(),
            TextColumn::make('patient.phone')
                ->label('Điện thoại')
                ->searchable(),
            TextColumn::make('care_type')
                ->label('Loại chăm sóc')
                ->badge()
                ->getStateUsing(fn () => 'medication_reminder')
                ->formatStateUsing(fn ($state) => $this->formatCareType($state)),
            TextColumn::make('care_status')
                ->label('Trạng thái chăm sóc')
                ->badge()
                ->getStateUsing(fn () => Note::CARE_STATUS_NOT_STARTED)
                ->formatStateUsing(fn ($state) => $this->formatCareStatus($state))
                ->color(fn ($state) => $this->getCareStatusColor($state)),
            TextColumn::make('care_channel')
                ->label('Kênh chăm sóc')
                ->badge()
                ->getStateUsing(fn () => $this->defaultCareChannel())
                ->formatStateUsing(fn ($state) => $this->formatCareChannel($state)),
            TextColumn::make('treatment_date')
                ->label('Thời gian chăm sóc')
                ->date('d/m/Y')
                ->sortable(),
            TextColumn::make('doctor.name')
                ->label('Nhân viên chăm sóc')
                ->sortable(),
            TextColumn::make('notes')
                ->label('Nội dung')
                ->limit(80)
                ->wrap(),
            TextColumn::make('created_at')
                ->label('Ngày tạo đơn thuốc')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('prescription_name')
                ->label('Tên đơn thuốc')
                ->searchable(),
        ];
    }

    protected function getFollowupColumns(): array
    {
        return [
            TextColumn::make('treatmentPlan.patient.patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('treatmentPlan.patient.full_name')
                ->label('Họ tên')
                ->searchable(),
            TextColumn::make('treatmentPlan.patient.phone')
                ->label('Điện thoại')
                ->searchable(),
            TextColumn::make('care_type')
                ->label('Loại chăm sóc')
                ->badge()
                ->getStateUsing(fn () => 'post_treatment_follow_up')
                ->formatStateUsing(fn ($state) => $this->formatCareType($state)),
            TextColumn::make('care_status')
                ->label('Trạng thái chăm sóc')
                ->badge()
                ->getStateUsing(fn () => Note::CARE_STATUS_NOT_STARTED)
                ->formatStateUsing(fn ($state) => $this->formatCareStatus($state))
                ->color(fn ($state) => $this->getCareStatusColor($state)),
            TextColumn::make('care_channel')
                ->label('Kênh chăm sóc')
                ->badge()
                ->getStateUsing(fn () => $this->defaultCareChannel())
                ->formatStateUsing(fn ($state) => $this->formatCareChannel($state)),
            TextColumn::make('performed_at')
                ->label('Thời gian chăm sóc')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
            TextColumn::make('doctor.name')
                ->label('Nhân viên chăm sóc')
                ->sortable(),
            TextColumn::make('notes')
                ->label('Nội dung')
                ->limit(80)
                ->wrap(),
            TextColumn::make('performed_at')
                ->label('Ngày điều trị')
                ->dateTime('d/m/Y H:i'),
            TextColumn::make('planItem.service.name')
                ->label('Tên thủ thuật')
                ->default('-')
                ->wrap(),
            TextColumn::make('doctor.name')
                ->label('Bác sĩ thực hiện'),
            TextColumn::make('performed_at')
                ->label('Thời gian dự kiến chăm sóc')
                ->getStateUsing(fn ($record) => optional($record->performed_at)?->addDays(3))
                ->date('d/m/Y'),
        ];
    }

    protected function getBirthdayColumns(): array
    {
        return [
            TextColumn::make('patient_code')
                ->label('Mã hồ sơ')
                ->searchable()
                ->sortable(),
            TextColumn::make('full_name')
                ->label('Họ tên')
                ->searchable(),
            TextColumn::make('phone')
                ->label('Điện thoại')
                ->searchable(),
            TextColumn::make('care_type')
                ->label('Loại chăm sóc')
                ->badge()
                ->getStateUsing(fn () => 'birthday_care')
                ->formatStateUsing(fn ($state) => $this->formatCareType($state)),
            TextColumn::make('care_status')
                ->label('Trạng thái chăm sóc')
                ->badge()
                ->getStateUsing(fn () => Note::CARE_STATUS_NOT_STARTED)
                ->formatStateUsing(fn ($state) => $this->formatCareStatus($state))
                ->color(fn ($state) => $this->getCareStatusColor($state)),
            TextColumn::make('care_channel')
                ->label('Kênh chăm sóc')
                ->badge()
                ->getStateUsing(fn () => $this->defaultCareChannel())
                ->formatStateUsing(fn ($state) => $this->formatCareChannel($state)),
            TextColumn::make('birthday')
                ->label('Thời gian chăm sóc')
                ->date('d/m'),
            TextColumn::make('ownerStaff.name')
                ->label('Nhân viên chăm sóc'),
            TextColumn::make('note')
                ->label('Nội dung')
                ->default('-')
                ->wrap(),
            TextColumn::make('birthday')
                ->label('Ngày sinh nhật')
                ->date('d/m/Y'),
        ];
    }

    protected function getCareScheduleFilters(): array
    {
        return [
            Filter::make('care_at')
                ->form([
                    DatePicker::make('from')->label('Từ ngày'),
                    DatePicker::make('until')->label('Đến ngày'),
                ])
                ->query(fn (Builder $query, array $data) => $this->applyDateRangeFilter($query, $data, 'care_at')),
            SelectFilter::make('care_type')
                ->label('Loại chăm sóc')
                ->options($this->getCareTypeOptions()),
            SelectFilter::make('care_status')
                ->label('Trạng thái chăm sóc')
                ->options($this->getCareStatusOptions())
                ->query(function (Builder $query, array $data): Builder {
                    $value = $data['value'] ?? null;

                    if (! $value) {
                        return $query;
                    }

                    return $query->whereIn('care_status', Note::statusesForQuery([$value]));
                }),
            SelectFilter::make('care_channel')
                ->label('Kênh chăm sóc')
                ->options($this->getCareChannelOptions()),
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->relationship('branch', 'name'),
            SelectFilter::make('user_id')
                ->label('Nhân viên chăm sóc')
                ->relationship('user', 'name', fn (Builder $query): Builder => $this->scopeCareStaffFilterOptions($query)),
        ];
    }

    protected function getPriorityQueueFilters(): array
    {
        return [
            Filter::make('care_at')
                ->form([
                    DatePicker::make('from')->label('Từ ngày'),
                    DatePicker::make('until')->label('Đến ngày'),
                ])
                ->query(fn (Builder $query, array $data) => $this->applyDateRangeFilter($query, $data, 'care_at')),
            SelectFilter::make('triage_bucket')
                ->label('Ưu tiên xử lý')
                ->options($this->getPriorityQueueBucketOptions())
                ->query(function (Builder $query, array $data): Builder {
                    $bucket = $data['value'] ?? null;

                    return $this->applyPriorityQueueBucketFilter($query, is_string($bucket) ? $bucket : null);
                }),
            SelectFilter::make('ownership_status')
                ->label('Phụ trách')
                ->options($this->getPriorityQueueOwnershipStatusOptions())
                ->query(function (Builder $query, array $data): Builder {
                    $status = $data['value'] ?? null;

                    return $this->applyPriorityQueueOwnershipFilter($query, is_string($status) ? $status : null);
                }),
            SelectFilter::make('care_type')
                ->label('Loại ưu tiên')
                ->options($this->priorityCareTypeOptions()),
            SelectFilter::make('care_status')
                ->label('Trạng thái')
                ->options($this->getCareStatusOptions())
                ->query(function (Builder $query, array $data): Builder {
                    $value = $data['value'] ?? null;

                    if (! $value) {
                        return $query;
                    }

                    return $query->whereIn('care_status', Note::statusesForQuery([$value]));
                }),
            SelectFilter::make('care_channel')
                ->label('Kênh')
                ->options($this->getCareChannelOptions()),
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->relationship('branch', 'name'),
            SelectFilter::make('user_id')
                ->label('Nhân viên')
                ->relationship('user', 'name', fn (Builder $query): Builder => $this->scopeCareStaffFilterOptions($query)),
        ];
    }

    protected function getAppointmentFilters(): array
    {
        return [
            Filter::make('date')
                ->form([
                    DatePicker::make('from')->label('Từ ngày'),
                    DatePicker::make('until')->label('Đến ngày'),
                ])
                ->query(fn (Builder $query, array $data) => $this->applyDateRangeFilter($query, $data, 'date')),
            SelectFilter::make('status')
                ->label('Trạng thái lịch')
                ->options($this->getAppointmentStatusOptions())
                ->query(function (Builder $query, array $data) {
                    $value = $data['value'] ?? null;

                    if (! $value) {
                        return $query;
                    }

                    return $query->whereIn('status', Appointment::statusesForQuery([$value]));
                }),
            SelectFilter::make('doctor_id')
                ->label('Bác sĩ')
                ->relationship('doctor', 'name', fn (Builder $query): Builder => $this->scopeDoctorFilterOptions($query)),
            SelectFilter::make('assigned_to')
                ->label('Nhân viên chăm sóc')
                ->relationship('assignedTo', 'name', fn (Builder $query): Builder => $this->scopeCareStaffFilterOptions($query)),
        ];
    }

    protected function getPrescriptionFilters(): array
    {
        return [
            Filter::make('treatment_date')
                ->form([
                    DatePicker::make('from')->label('Từ ngày'),
                    DatePicker::make('until')->label('Đến ngày'),
                ])
                ->query(fn (Builder $query, array $data) => $this->applyDateRangeFilter($query, $data, 'treatment_date')),
            SelectFilter::make('doctor_id')
                ->label('Bác sĩ')
                ->relationship('doctor', 'name', fn (Builder $query): Builder => $this->scopeDoctorFilterOptions($query)),
        ];
    }

    protected function getFollowupFilters(): array
    {
        return [
            Filter::make('performed_at')
                ->form([
                    DatePicker::make('from')->label('Từ ngày'),
                    DatePicker::make('until')->label('Đến ngày'),
                ])
                ->query(fn (Builder $query, array $data) => $this->applyDateRangeFilter($query, $data, 'performed_at')),
            SelectFilter::make('doctor_id')
                ->label('Bác sĩ')
                ->relationship('doctor', 'name', fn (Builder $query): Builder => $this->scopeDoctorFilterOptions($query)),
        ];
    }

    protected function getBirthdayFilters(): array
    {
        return [
            SelectFilter::make('birthday_month')
                ->label('Tháng sinh')
                ->options($this->getMonthOptions())
                ->query(function (Builder $query, array $data) {
                    $month = $data['value'] ?? null;
                    if ($month) {
                        $query->whereMonth('birthday', $month);
                    }
                }),
        ];
    }

    protected function getPatientUrl($record): ?string
    {
        $patientId = $this->resolvePatientId($record);
        if (! $patientId) {
            return null;
        }

        return PatientResource::getUrl('view', ['record' => $patientId]);
    }

    protected function resolvePatientId($record): ?int
    {
        if ($record instanceof Patient) {
            return $record->id;
        }

        if (property_exists($record, 'patient_id')) {
            return $record->patient_id;
        }

        if (method_exists($record, 'treatmentPlan') && $record->treatmentPlan) {
            return $record->treatmentPlan->patient_id;
        }

        return null;
    }

    protected function applyDateRangeFilter(Builder $query, array $data, string $column): Builder
    {
        if (! empty($data['from'])) {
            $query->whereDate($column, '>=', $data['from']);
        }

        if (! empty($data['until'])) {
            $query->whereDate($column, '<=', $data['until']);
        }

        return $query;
    }

    protected function formatCareType(?string $state): string
    {
        return Arr::get($this->getCareTypeOptions(), $state, 'Chăm sóc chung');
    }

    protected function formatCareChannel(?string $state): string
    {
        return Arr::get($this->getCareChannelOptions(), $state, 'Khác');
    }

    protected function formatCareStatus(?string $state): string
    {
        return Note::careStatusLabel($state);
    }

    protected function getCareStatusColor(?string $state): string
    {
        return Note::careStatusColor($state);
    }

    /**
     * @return array<string, string>
     */
    protected function getPriorityQueueBucketOptions(): array
    {
        return [
            'overdue' => 'Quá hạn',
            'due_today' => 'Đến hạn hôm nay',
            'upcoming' => 'Sắp tới',
            'unscheduled' => 'Chưa đặt lịch',
        ];
    }

    protected function resolvePriorityQueueBucket($record): string
    {
        if (! $record->care_at instanceof CarbonInterface) {
            return 'unscheduled';
        }

        if ($record->care_at->isPast()) {
            return 'overdue';
        }

        if ($record->care_at->isToday()) {
            return 'due_today';
        }

        return 'upcoming';
    }

    protected function formatPriorityQueueBucket(?string $bucket): string
    {
        return Arr::get($this->getPriorityQueueBucketOptions(), $bucket, 'Chưa xác định');
    }

    protected function getPriorityQueueBucketColor(?string $bucket): string
    {
        return match ($bucket) {
            'overdue' => 'danger',
            'due_today' => 'warning',
            'upcoming' => 'success',
            'unscheduled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function getPriorityQueueOwnershipStatusOptions(): array
    {
        return [
            'mine' => 'Tôi đang phụ trách',
            'assigned' => 'Đã phân công',
            'unassigned' => 'Chưa phân công',
        ];
    }

    protected function resolvePriorityQueueOwnershipStatus($record): string
    {
        $authUser = auth()->user();

        if (! $record->user_id) {
            return 'unassigned';
        }

        if ($authUser instanceof User && (int) $record->user_id === $authUser->id) {
            return 'mine';
        }

        return 'assigned';
    }

    protected function formatPriorityQueueOwnershipStatus(?string $status): string
    {
        return Arr::get($this->getPriorityQueueOwnershipStatusOptions(), $status, 'Chưa xác định');
    }

    protected function getPriorityQueueOwnershipStatusColor(?string $status): string
    {
        return match ($status) {
            'mine' => 'primary',
            'assigned' => 'success',
            'unassigned' => 'gray',
            default => 'gray',
        };
    }

    protected function formatPriorityQueueSla($record): string
    {
        $careAt = $record->care_at;
        $bucket = $this->resolvePriorityQueueBucket($record);

        if (! $careAt instanceof CarbonInterface) {
            return 'Chưa đặt lịch';
        }

        $referenceTime = now();

        return match ($bucket) {
            'overdue' => 'Quá hạn '.$careAt->diffForHumans($referenceTime, true),
            'due_today' => 'Đến hạn trong '.$careAt->diffForHumans($referenceTime, true),
            'upcoming' => 'Còn '.$careAt->diffForHumans($referenceTime, true),
            default => 'Chưa đặt lịch',
        };
    }

    protected function getPriorityQueueSlaColor($record): string
    {
        return $this->getPriorityQueueBucketColor($this->resolvePriorityQueueBucket($record));
    }

    protected function applyPriorityQueueBucketFilter(Builder $query, ?string $bucket): Builder
    {
        if (! in_array($bucket, array_keys($this->getPriorityQueueBucketOptions()), true)) {
            return $query;
        }

        $referenceTime = now();
        $endOfToday = $referenceTime->copy()->endOfDay();

        return match ($bucket) {
            'overdue' => $query
                ->whereNotNull('care_at')
                ->where('care_at', '<', $referenceTime),
            'due_today' => $query
                ->whereNotNull('care_at')
                ->where('care_at', '>=', $referenceTime)
                ->whereDate('care_at', $referenceTime->toDateString()),
            'upcoming' => $query
                ->whereNotNull('care_at')
                ->where('care_at', '>', $endOfToday),
            'unscheduled' => $query->whereNull('care_at'),
            default => $query,
        };
    }

    protected function applyPriorityQueueOwnershipFilter(Builder $query, ?string $status): Builder
    {
        if (! in_array($status, array_keys($this->getPriorityQueueOwnershipStatusOptions()), true)) {
            return $query;
        }

        $authUser = auth()->user();

        return match ($status) {
            'mine' => $authUser instanceof User
                ? $query->where('user_id', $authUser->id)
                : $query->whereRaw('1 = 0'),
            'assigned' => $query->whereNotNull('user_id'),
            'unassigned' => $query->whereNull('user_id'),
            default => $query,
        };
    }

    protected function careSummaryOwnedByCurrentUser(Builder $baseQuery): int
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User) {
            return 0;
        }

        return (clone $baseQuery)
            ->where('user_id', $authUser->id)
            ->count();
    }

    protected function formatAppointmentStatus(?string $state): string
    {
        return Appointment::statusLabel($state);
    }

    protected function getAppointmentStatusColor(?string $state): string
    {
        return Appointment::statusColor($state);
    }

    protected function getCareTypeOptions(): array
    {
        return ClinicRuntimeSettings::careTypeDisplayOptions();
    }

    protected function getCareChannelOptions(): array
    {
        return ClinicRuntimeSettings::careChannelOptions();
    }

    protected function defaultCareChannel(): string
    {
        return ClinicRuntimeSettings::defaultCareChannel();
    }

    protected function getCareStatusOptions(): array
    {
        return Note::careStatusOptions();
    }

    protected function getAppointmentStatusOptions(): array
    {
        return Appointment::statusOptions();
    }

    protected function getMonthOptions(): array
    {
        return [
            1 => 'Tháng 1',
            2 => 'Tháng 2',
            3 => 'Tháng 3',
            4 => 'Tháng 4',
            5 => 'Tháng 5',
            6 => 'Tháng 6',
            7 => 'Tháng 7',
            8 => 'Tháng 8',
            9 => 'Tháng 9',
            10 => 'Tháng 10',
            11 => 'Tháng 11',
            12 => 'Tháng 12',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function priorityCareTypeOptions(): array
    {
        $allTypes = $this->getCareTypeOptions();
        $priorityKeys = [
            'no_show_recovery',
            'recall_recare',
            'treatment_plan_follow_up',
        ];

        return collect($priorityKeys)
            ->filter(fn (string $type): bool => array_key_exists($type, $allTypes))
            ->mapWithKeys(fn (string $type): array => [$type => $allTypes[$type]])
            ->all();
    }

    protected function baseCareTicketQuery(): Builder
    {
        return $this->applyDirectBranchScope(Note::query(), 'branch_id')
            ->whereNotNull('patient_id')
            ->whereNotNull('care_type')
            ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()));
    }

    protected function careTicketQueryByType(string $careType, string $sourceType): Builder
    {
        return $this->baseCareTicketQuery()
            ->with($this->careTicketRelations())
            ->where('care_type', $careType)
            ->where('source_type', $sourceType);
    }

    protected function applyDirectBranchScope(Builder $query, string $column): Builder
    {
        $authUser = auth()->user();
        if ($authUser instanceof User && $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    protected function applyPatientBranchScope(Builder $query, string $patientRelationPath): Builder
    {
        $authUser = auth()->user();
        if ($authUser instanceof User && $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($patientRelationPath, function (Builder $patientQuery) use ($branchIds): void {
            $patientQuery->whereIn('first_branch_id', $branchIds);
        });
    }

    /**
     * @return list<string>
     */
    protected function defaultCareScheduleTypes(): array
    {
        $candidateTypes = [
            'general_care',
            'treatment_plan_follow_up',
            'reactivation_follow_up',
            'risk_high_follow_up',
        ];

        $availableTypes = $this->getCareTypeOptions();

        return collect($candidateTypes)
            ->filter(fn (string $type): bool => array_key_exists($type, $availableTypes))
            ->values()
            ->all();
    }

    protected function scopeCareStaffFilterOptions(Builder $query): Builder
    {
        $authUser = auth()->user();

        return app(PatientAssignmentAuthorizer::class)->scopeAssignableStaff(
            query: $query,
            actor: $authUser instanceof User ? $authUser : null,
            branchId: null,
        );
    }

    protected function scopeDoctorFilterOptions(Builder $query): Builder
    {
        $authUser = auth()->user();

        return app(PatientAssignmentAuthorizer::class)->scopeAssignableDoctors(
            query: $query,
            actor: $authUser instanceof User ? $authUser : null,
            branchId: null,
        );
    }

    protected function applyPatientPhoneSearch(Builder $query, string $search): Builder
    {
        return $query->whereHas('patient', fn (Builder $patientQuery): Builder => $patientQuery->wherePhoneMatches($search));
    }

    /**
     * @return array<int, string>
     */
    protected function careTicketRelations(): array
    {
        return [
            'patient:id,patient_code,full_name,phone,phone_search_hash,first_branch_id',
            'user:id,name',
            'branch:id,name',
        ];
    }
}
