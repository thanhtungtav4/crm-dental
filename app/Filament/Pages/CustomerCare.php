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
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use App\Support\Exports\ExportsCsv;
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
            'appointment_reminder' => $this->applyDirectBranchScope(Appointment::query(), 'branch_id')
                ->with(['patient', 'doctor', 'assignedTo'])
                ->whereNotNull('patient_id')
                ->whereIn('status', Appointment::statusesForQuery([
                    Appointment::STATUS_NO_SHOW,
                    Appointment::STATUS_RESCHEDULED,
                ])),
            'prescription_reminder' => $this->applyPatientBranchScope(Prescription::query(), 'patient')
                ->with(['patient', 'doctor'])
                ->whereNotNull('patient_id'),
            'post_treatment_followup' => $this->applyPatientBranchScope(TreatmentSession::query(), 'treatmentPlan.patient')
                ->with(['treatmentPlan.patient', 'doctor', 'planItem.service'])
                ->whereNotNull('performed_at'),
            'birthday' => $this->applyDirectBranchScope(Patient::query(), 'first_branch_id')
                ->with('ownerStaff')
                ->whereNotNull('birthday'),
            'priority_queue' => $this->baseCareTicketQuery()
                ->with(['patient', 'user', 'branch'])
                ->whereIn('care_type', array_keys($this->priorityCareTypeOptions())),
            default => $this->applyDirectBranchScope(Note::query(), 'branch_id')
                ->with(['patient', 'user', 'branch'])
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
            'appointment_reminder' => $this->getAppointmentColumns(),
            'prescription_reminder' => $this->getPrescriptionColumns(),
            'post_treatment_followup' => $this->getFollowupColumns(),
            'birthday' => $this->getBirthdayColumns(),
            default => $this->getCareScheduleColumns(),
        };
    }

    protected function getTableFilters(): array
    {
        return match ($this->activeTab) {
            'priority_queue' => $this->getPriorityQueueFilters(),
            'appointment_reminder' => $this->getAppointmentFilters(),
            'prescription_reminder' => $this->getPrescriptionFilters(),
            'post_treatment_followup' => $this->getFollowupFilters(),
            'birthday' => $this->getBirthdayFilters(),
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
            'appointment_reminder' => $this->getAppointmentExportColumns(),
            'prescription_reminder' => $this->getPrescriptionExportColumns(),
            'post_treatment_followup' => $this->getFollowupExportColumns(),
            'birthday' => $this->getBirthdayExportColumns(),
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
                ->searchable(),
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
                ->searchable(),
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
                ->getStateUsing(function ($record): string {
                    if (! $record->care_at) {
                        return 'Chưa đặt lịch';
                    }

                    if ($record->care_at->isFuture()) {
                        return 'Còn '.$record->care_at->diffForHumans(now(), true);
                    }

                    return 'Quá hạn '.$record->care_at->diffForHumans(now(), true);
                })
                ->badge()
                ->color(function ($record): string {
                    if (! $record->care_at) {
                        return 'gray';
                    }

                    if ($record->care_at->isFuture()) {
                        return 'success';
                    }

                    return 'danger';
                }),
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
                ->relationship('user', 'name'),
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
                ->relationship('user', 'name'),
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
                ->relationship('doctor', 'name'),
            SelectFilter::make('assigned_to')
                ->label('Nhân viên chăm sóc')
                ->relationship('assignedTo', 'name'),
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
                ->relationship('doctor', 'name'),
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
                ->relationship('doctor', 'name'),
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
}
