<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use App\Filament\Resources\MaterialIssueNotes\MaterialIssueNoteResource;
use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\TreatmentMaterials\TreatmentMaterialResource;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\PatientMedicalRecord;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Services\PatientOverviewReadModelService;
use App\Services\PhiAccessAuditService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public string $activeTab = 'basic-info';

    public string $workspaceReturnUrl = '';

    protected ?array $cachedTabCounters = null;

    protected ?Collection $cachedTreatmentProgress = null;

    protected ?Collection $cachedTreatmentProgressDaySummaries = null;

    protected ?Collection $cachedMaterialUsages = null;

    protected ?Collection $cachedFactoryOrders = null;

    protected ?Collection $cachedMaterialIssueNotes = null;

    protected ?array $cachedPaymentSummary = null;

    protected ?Collection $cachedLatestPrescriptions = null;

    protected ?Collection $cachedLatestInvoices = null;

    protected bool $hasResolvedMedicalRecord = false;

    protected ?PatientMedicalRecord $resolvedMedicalRecord = null;

    /**
     * Tabs rendered in the custom patient workspace view.
     */
    protected array $workspaceTabs = [
        'basic-info',
        'exam-treatment',
        'prescriptions',
        'photos',
        'lab-materials',
        'appointments',
        'payments',
        'forms',
        'care',
        'activity-log',
    ];

    protected array $legacyTabMap = [
        'overview' => 'basic-info',
        'invoices' => 'payments',
        'notes' => 'care',
        'payment' => 'payments',
        'appointment' => 'appointments',
        'photo' => 'photos',
        'examAndTreatment' => 'exam-treatment',
    ];

    public function mount($record): void
    {
        parent::mount($record);

        $requestedTab = (string) request()->query('tab', 'basic-info');
        $requestedTab = $this->legacyTabMap[$requestedTab] ?? $requestedTab;

        $visibleTabs = $this->visibleWorkspaceTabIds();
        $this->activeTab = in_array($requestedTab, $visibleTabs, true)
            ? $requestedTab
            : ($visibleTabs[0] ?? 'basic-info');
        $this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($this->activeTab);

        if ($this->activeTab === 'exam-treatment') {
            app(PhiAccessAuditService::class)->recordPatientWorkspaceRead(
                patient: $this->record,
                context: [
                    'tab' => 'exam-treatment',
                    'route' => request()->path(),
                ],
            );
        }
    }

    public function getView(): string
    {
        return 'filament.resources.patients.pages.view-patient';
    }

    public function getTitle(): string
    {
        return 'Hồ sơ: '.$this->record->full_name;
    }

    public function getTabsProperty(): array
    {
        $counter = $this->tabCounters;

        $tabs = [
            ['id' => 'basic-info', 'label' => 'Thông tin cơ bản', 'count' => null],
            ['id' => 'exam-treatment', 'label' => 'Khám & Điều trị', 'count' => $counter['exam_sessions'] + $counter['treatment_plans']],
        ];

        if ($this->canViewPrescriptionsTab()) {
            $tabs[] = ['id' => 'prescriptions', 'label' => 'Đơn thuốc', 'count' => $counter['prescriptions']];
        }

        $tabs[] = ['id' => 'photos', 'label' => 'Thư viện ảnh', 'count' => $counter['photos']];

        if ($this->canViewLabMaterialsTab()) {
            $tabs[] = ['id' => 'lab-materials', 'label' => 'Xưởng/Vật tư', 'count' => $counter['materials']];
        }

        if ($this->canViewAppointmentsTab()) {
            $tabs[] = ['id' => 'appointments', 'label' => 'Lịch hẹn', 'count' => $counter['appointments']];
        }

        if ($this->canViewPaymentsTab()) {
            $tabs[] = ['id' => 'payments', 'label' => 'Thanh toán', 'count' => $counter['invoices'] + $counter['payments']];
        }

        if ($this->canViewFormsTab()) {
            $tabs[] = [
                'id' => 'forms',
                'label' => 'Biểu mẫu',
                'count' => ($this->canViewPrescriptionsTab() ? $counter['prescriptions'] : 0)
                    + ($this->canViewInvoiceForms() ? $counter['invoices'] : 0),
            ];
        }

        if ($this->canViewCareTab()) {
            $tabs[] = ['id' => 'care', 'label' => 'Chăm sóc', 'count' => $counter['notes']];
        }

        $tabs[] = ['id' => 'activity-log', 'label' => 'Lịch sử thao tác', 'count' => $counter['activity']];

        return $tabs;
    }

    public function getTabCountersProperty(): array
    {
        if ($this->cachedTabCounters !== null) {
            return $this->cachedTabCounters;
        }

        $this->cachedTabCounters = app(PatientOverviewReadModelService::class)->tabCounters($this->record);

        return $this->cachedTabCounters;
    }

    public function getTreatmentProgressProperty(): Collection
    {
        if ($this->cachedTreatmentProgress !== null) {
            return $this->cachedTreatmentProgress;
        }

        $progressItems = $this->record->treatmentProgressItems()
            ->with([
                'doctor:id,name',
                'assistant:id,name',
                'planItem:id,name,tooth_number,tooth_ids,quantity,price,status',
                'progressDay:id,progress_date,status',
            ])
            ->latest('performed_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $this->cachedTreatmentProgress = $progressItems->map(function ($progressItem) {
            $performedAt = $progressItem->performed_at ?? $progressItem->created_at;
            $progressDate = $progressItem->progressDay?->progress_date?->format('d/m/Y')
                ?? $performedAt?->format('d/m/Y')
                ?? '-';
            $statusLabel = match ($progressItem->status) {
                'completed' => 'Hoàn thành',
                'in_progress' => 'Đang thực hiện',
                'cancelled' => 'Đã hủy',
                default => 'Đã lên lịch',
            };
            $statusClass = match ($progressItem->status) {
                'completed' => 'is-completed',
                'in_progress' => 'is-progress',
                'cancelled' => 'is-default',
                default => 'is-default',
            };
            $toothLabel = $progressItem->tooth_number
                ?: $progressItem->planItem?->tooth_number
                ?: (is_array($progressItem->planItem?->tooth_ids) ? implode(' ', $progressItem->planItem?->tooth_ids) : '-');
            $sessionQty = (float) ($progressItem->quantity ?? 1);
            $sessionPrice = (float) ($progressItem->unit_price ?? 0);
            $sessionLineTotal = (float) ($progressItem->total_amount ?? ($sessionQty * $sessionPrice));
            $sessionId = $progressItem->treatment_session_id ? (int) $progressItem->treatment_session_id : null;

            return [
                'session_id' => $progressItem->id,
                'performed_at' => $performedAt?->format('d/m/Y H:i') ?? '-',
                'progress_date' => $progressDate,
                'tooth_label' => $toothLabel,
                'plan_item_name' => $progressItem->procedure_name ?: ($progressItem->planItem?->name ?? '-'),
                'procedure' => $progressItem->notes ?: '-',
                'doctor_name' => $progressItem->doctor?->name ?? '-',
                'assistant_name' => $progressItem->assistant?->name ?? '-',
                'quantity' => fmod($sessionQty, 1.0) === 0.0 ? (int) $sessionQty : $sessionQty,
                'price_formatted' => $this->formatMoney($sessionPrice),
                'total_amount' => $sessionLineTotal,
                'total_amount_formatted' => $this->formatMoney($sessionLineTotal),
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'edit_url' => $sessionId
                    ? route('filament.admin.resources.treatment-sessions.edit', [
                        'record' => $sessionId,
                        'return_url' => $this->workspaceReturnUrl,
                    ])
                    : null,
            ];
        });

        return $this->cachedTreatmentProgress;
    }

    public function getTreatmentProgressCountProperty(): int
    {
        return $this->treatmentProgress->count();
    }

    public function getTreatmentProgressDaySummariesProperty(): Collection
    {
        if ($this->cachedTreatmentProgressDaySummaries !== null) {
            return $this->cachedTreatmentProgressDaySummaries;
        }

        $days = $this->record->treatmentProgressDays()
            ->withSum('items as day_total_amount', 'total_amount')
            ->withCount('items as sessions_count')
            ->latest('progress_date')
            ->latest('id')
            ->limit(20)
            ->get(['id', 'progress_date', 'status']);

        $this->cachedTreatmentProgressDaySummaries = $days->map(function ($day) {
            $statusLabel = match ($day->status) {
                'completed' => 'Hoàn thành',
                'in_progress' => 'Đang thực hiện',
                'locked' => 'Đã khoá',
                default => 'Đã lên lịch',
            };

            return [
                'progress_date' => $day->progress_date?->format('d/m/Y') ?? '-',
                'status_label' => $statusLabel,
                'sessions_count' => (int) ($day->sessions_count ?? 0),
                'day_total_amount' => (float) ($day->day_total_amount ?? 0),
                'day_total_amount_formatted' => $this->formatMoney((float) ($day->day_total_amount ?? 0)),
            ];
        });

        return $this->cachedTreatmentProgressDaySummaries;
    }

    public function getTreatmentProgressDayCountProperty(): int
    {
        return $this->treatmentProgressDaySummaries->count();
    }

    public function getTreatmentProgressTotalAmountProperty(): float
    {
        return $this->treatmentProgress
            ->sum(fn (array $session): float => (float) ($session['total_amount'] ?? 0));
    }

    public function getTreatmentProgressTotalAmountFormattedProperty(): string
    {
        return $this->formatMoney($this->treatmentProgressTotalAmount);
    }

    public function getMaterialUsagesProperty(): Collection
    {
        if ($this->cachedMaterialUsages !== null) {
            return $this->cachedMaterialUsages;
        }

        $this->cachedMaterialUsages = app(PatientOverviewReadModelService::class)->materialUsages($this->record);

        return $this->cachedMaterialUsages;
    }

    public function getFactoryOrdersProperty(): Collection
    {
        if ($this->cachedFactoryOrders !== null) {
            return $this->cachedFactoryOrders;
        }

        $this->cachedFactoryOrders = app(PatientOverviewReadModelService::class)->factoryOrders($this->record);

        return $this->cachedFactoryOrders;
    }

    public function getMaterialIssueNotesProperty(): Collection
    {
        if ($this->cachedMaterialIssueNotes !== null) {
            return $this->cachedMaterialIssueNotes;
        }

        $this->cachedMaterialIssueNotes = app(PatientOverviewReadModelService::class)->materialIssueNotes($this->record);

        return $this->cachedMaterialIssueNotes;
    }

    public function getPaymentSummaryProperty(): array
    {
        if ($this->cachedPaymentSummary !== null) {
            return $this->cachedPaymentSummary;
        }

        $summary = app(PatientOverviewReadModelService::class)->paymentSummary($this->record);
        $latestInvoiceId = $summary['latest_invoice_id'];

        $this->cachedPaymentSummary = [
            'total_treatment_amount' => $summary['total_treatment_amount'],
            'total_treatment_amount_formatted' => $this->formatMoney($summary['total_treatment_amount']),
            'total_discount_amount' => $summary['total_discount_amount'],
            'total_discount_amount_formatted' => $this->formatMoney($summary['total_discount_amount']),
            'must_pay_amount' => $summary['must_pay_amount'],
            'must_pay_amount_formatted' => $this->formatMoney($summary['must_pay_amount']),
            'net_collected_amount' => $summary['net_collected_amount'],
            'net_collected_amount_formatted' => $this->formatMoney($summary['net_collected_amount']),
            'remaining_amount' => $summary['remaining_amount'],
            'remaining_amount_formatted' => $this->formatMoney($summary['remaining_amount']),
            'balance_amount' => $summary['balance_amount'],
            'balance_amount_formatted' => $this->formatMoney($summary['balance_amount']),
            'balance_is_positive' => $summary['balance_is_positive'],
            'create_payment_url' => route(
                'filament.admin.resources.payments.create',
                $latestInvoiceId ? ['invoice_id' => $latestInvoiceId] : []
            ),
        ];

        return $this->cachedPaymentSummary;
    }

    public function getLatestPrescriptionsProperty(): Collection
    {
        if ($this->cachedLatestPrescriptions !== null) {
            return $this->cachedLatestPrescriptions;
        }

        $this->cachedLatestPrescriptions = app(PatientOverviewReadModelService::class)->latestPrescriptions($this->record);

        return $this->cachedLatestPrescriptions;
    }

    public function getLatestInvoicesProperty(): Collection
    {
        if ($this->cachedLatestInvoices !== null) {
            return $this->cachedLatestInvoices;
        }

        $this->cachedLatestInvoices = app(PatientOverviewReadModelService::class)->latestInvoices($this->record);

        return $this->cachedLatestInvoices;
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, $this->visibleWorkspaceTabIds(), true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($tab);
    }

    protected function visibleWorkspaceTabIds(): array
    {
        return collect($this->getTabsProperty())
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && in_array($id, $this->workspaceTabs, true))
            ->values()
            ->all();
    }

    protected function buildWorkspaceReturnUrl(string $tab): string
    {
        return PatientResource::getUrl('view', [
            'record' => $this->record,
            'tab' => $tab,
        ]);
    }

    protected function canViewPrescriptionsTab(): bool
    {
        return auth()->user()?->can('viewAny', Prescription::class) ?? false;
    }

    protected function canViewAppointmentsTab(): bool
    {
        return auth()->user()?->can('viewAny', Appointment::class) ?? false;
    }

    protected function canViewPaymentsTab(): bool
    {
        return (auth()->user()?->can('viewAny', Invoice::class) ?? false)
            || (auth()->user()?->can('viewAny', Payment::class) ?? false);
    }

    protected function canCreatePayments(): bool
    {
        return auth()->user()?->can('create', Payment::class) ?? false;
    }

    protected function canViewInvoiceForms(): bool
    {
        return auth()->user()?->can('viewAny', Invoice::class) ?? false;
    }

    protected function canViewFormsTab(): bool
    {
        return $this->canViewPrescriptionsTab() || $this->canViewInvoiceForms();
    }

    protected function canViewCareTab(): bool
    {
        return auth()->user()?->can('viewAny', \App\Models\Note::class) ?? false;
    }

    protected function canViewLabMaterialsTab(): bool
    {
        return FactoryOrderResource::canAccess()
            || MaterialIssueNoteResource::canAccess()
            || TreatmentMaterialResource::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTreatmentPlan')
                ->label('Tạo kế hoạch điều trị')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('success')
                ->visible(fn (): bool => auth()->user()?->can('create', TreatmentPlan::class) ?? false)
                ->url(fn () => route('filament.admin.resources.treatment-plans.create', [
                    'patient_id' => $this->record->id,
                    'return_url' => $this->workspaceReturnUrl,
                ]))
                ->openUrlInNewTab(),

            Action::make('createInvoice')
                ->label('Tạo hóa đơn')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('create', Invoice::class) ?? false)
                ->url(fn () => route('filament.admin.resources.invoices.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Action::make('createAppointment')
                ->label('Đặt lịch hẹn')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->visible(fn (): bool => auth()->user()?->can('create', Appointment::class) ?? false)
                ->url(fn () => route('filament.admin.resources.appointments.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Action::make('medicalRecord')
                ->label(fn (): string => $this->resolveMedicalRecordActionLabel())
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->url(fn (): ?string => $this->resolveMedicalRecordActionUrl())
                ->visible(fn (): bool => $this->resolveMedicalRecordActionUrl() !== null),

            Actions\EditAction::make()
                ->label('Chỉnh sửa')
                ->icon('heroicon-o-pencil'),

            Actions\DeleteAction::make()
                ->label('Xóa')
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function formatMoney(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    protected function resolveMedicalRecordActionUrl(): ?string
    {
        $authUser = auth()->user();
        if (! $authUser) {
            return null;
        }

        $medicalRecord = $this->resolvePatientMedicalRecord();

        if ($medicalRecord instanceof PatientMedicalRecord) {
            if (! ($authUser->can('update', $medicalRecord) || $authUser->can('view', $medicalRecord))) {
                return null;
            }

            return PatientMedicalRecordResource::getUrl('edit', [
                'record' => $medicalRecord,
                'patient_id' => $this->record->id,
            ]);
        }

        if (! $authUser->can('create', PatientMedicalRecord::class)) {
            return null;
        }

        return PatientMedicalRecordResource::getUrl('create', [
            'patient_id' => $this->record->id,
        ]);
    }

    protected function resolveMedicalRecordActionLabel(): string
    {
        return $this->resolvePatientMedicalRecord() instanceof PatientMedicalRecord
            ? 'Mở bệnh án điện tử'
            : 'Tạo bệnh án điện tử';
    }

    protected function resolvePatientMedicalRecord(): ?PatientMedicalRecord
    {
        if ($this->hasResolvedMedicalRecord) {
            return $this->resolvedMedicalRecord;
        }

        $this->resolvedMedicalRecord = $this->record->medicalRecord()
            ->first(['id', 'patient_id']);

        $this->hasResolvedMedicalRecord = true;

        return $this->resolvedMedicalRecord;
    }
}
