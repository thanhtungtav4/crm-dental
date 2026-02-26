<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\TreatmentMaterial;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public string $activeTab = 'basic-info';

    protected ?array $cachedTabCounters = null;

    protected ?Collection $cachedTreatmentProgress = null;

    protected ?Collection $cachedMaterialUsages = null;

    protected ?array $cachedPaymentSummary = null;

    protected ?Collection $cachedLatestPrescriptions = null;

    protected ?Collection $cachedLatestInvoices = null;

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

        $this->activeTab = in_array($requestedTab, $this->workspaceTabs, true)
            ? $requestedTab
            : 'basic-info';
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

        return [
            ['id' => 'basic-info', 'label' => 'Thông tin cơ bản', 'count' => null],
            ['id' => 'exam-treatment', 'label' => 'Khám & Điều trị', 'count' => $counter['clinical_notes'] + $counter['treatment_plans']],
            ['id' => 'prescriptions', 'label' => 'Đơn thuốc', 'count' => $counter['prescriptions']],
            ['id' => 'photos', 'label' => 'Thư viện ảnh', 'count' => $counter['photos']],
            ['id' => 'lab-materials', 'label' => 'Xưởng/Vật tư', 'count' => $counter['materials']],
            ['id' => 'appointments', 'label' => 'Lịch hẹn', 'count' => $counter['appointments']],
            ['id' => 'payments', 'label' => 'Thanh toán', 'count' => $counter['invoices'] + $counter['payments']],
            ['id' => 'forms', 'label' => 'Biểu mẫu', 'count' => $counter['prescriptions'] + $counter['invoices']],
            ['id' => 'care', 'label' => 'Chăm sóc', 'count' => $counter['notes']],
            ['id' => 'activity-log', 'label' => 'Lịch sử thao tác', 'count' => $counter['activity']],
        ];
    }

    public function getTabCountersProperty(): array
    {
        if ($this->cachedTabCounters !== null) {
            return $this->cachedTabCounters;
        }

        $this->record->loadCount([
            'treatmentPlans',
            'invoices',
            'appointments',
            'notes',
            'clinicalNotes',
            'photos',
            'prescriptions',
            'payments',
            'branchLogs',
        ]);

        $materialCount = TreatmentMaterial::query()
            ->whereHas('session.treatmentPlan', fn ($query) => $query->where('patient_id', $this->record->id))
            ->count();

        $this->cachedTabCounters = [
            'treatment_plans' => (int) ($this->record->treatment_plans_count ?? 0),
            'invoices' => (int) ($this->record->invoices_count ?? 0),
            'appointments' => (int) ($this->record->appointments_count ?? 0),
            'notes' => (int) ($this->record->notes_count ?? 0),
            'clinical_notes' => (int) ($this->record->clinical_notes_count ?? 0),
            'photos' => (int) ($this->record->photos_count ?? 0),
            'prescriptions' => (int) ($this->record->prescriptions_count ?? 0),
            'payments' => (int) ($this->record->payments_count ?? 0),
            'materials' => $materialCount,
            'activity' => (int) (($this->record->appointments_count ?? 0)
                + ($this->record->treatment_plans_count ?? 0)
                + ($this->record->invoices_count ?? 0)
                + ($this->record->payments_count ?? 0)
                + ($this->record->notes_count ?? 0)
                + ($this->record->branch_logs_count ?? 0)),
        ];

        return $this->cachedTabCounters;
    }

    public function getTreatmentProgressProperty(): Collection
    {
        if ($this->cachedTreatmentProgress !== null) {
            return $this->cachedTreatmentProgress;
        }

        $sessions = $this->record->treatmentSessions()
            ->with(['doctor:id,name', 'assistant:id,name', 'planItem:id,name,tooth_number,tooth_ids,quantity,price,status'])
            ->latest('performed_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $this->cachedTreatmentProgress = $sessions->map(function ($session) {
            $performedAt = $session->performed_at ?? $session->start_at ?? $session->created_at;
            $statusLabel = match ($session->status) {
                'done', 'completed' => 'Hoàn thành',
                'follow_up' => 'Tái khám',
                default => 'Đã lên lịch',
            };
            $statusClass = match ($session->status) {
                'done', 'completed' => 'is-completed',
                'follow_up' => 'is-progress',
                default => 'is-default',
            };
            $toothLabel = $session->planItem?->tooth_number
                ?: (is_array($session->planItem?->tooth_ids) ? implode(' ', $session->planItem?->tooth_ids) : '-');
            $sessionQty = $session->planItem?->quantity ?? 1;
            $sessionPrice = (float) ($session->planItem?->price ?? 0);

            return [
                'performed_at' => $performedAt?->format('d/m/Y H:i') ?? '-',
                'tooth_label' => $toothLabel,
                'plan_item_name' => $session->planItem?->name ?? '-',
                'procedure' => $session->procedure ?: ($session->notes ?: '-'),
                'doctor_name' => $session->doctor?->name ?? '-',
                'assistant_name' => $session->assistant?->name ?? '-',
                'quantity' => (int) $sessionQty,
                'price_formatted' => $this->formatMoney($sessionPrice),
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
            ];
        });

        return $this->cachedTreatmentProgress;
    }

    public function getTreatmentProgressCountProperty(): int
    {
        return $this->treatmentProgress->count();
    }

    public function getMaterialUsagesProperty(): Collection
    {
        if ($this->cachedMaterialUsages !== null) {
            return $this->cachedMaterialUsages;
        }

        $this->cachedMaterialUsages = TreatmentMaterial::query()
            ->with(['session', 'material', 'user'])
            ->whereHas('session.treatmentPlan', fn ($query) => $query->where('patient_id', $this->record->id))
            ->latest('created_at')
            ->limit(100)
            ->get();

        return $this->cachedMaterialUsages;
    }

    public function getPaymentSummaryProperty(): array
    {
        if ($this->cachedPaymentSummary !== null) {
            return $this->cachedPaymentSummary;
        }

        $totalTreatmentAmount = (float) $this->record->invoices()->sum('total_amount');
        $totalDiscountAmount = (float) $this->record->invoices()->sum('discount_amount');
        $mustPayAmount = max(0, $totalTreatmentAmount);
        $receiptAmount = (float) $this->record->payments()->where('direction', 'receipt')->sum('amount');
        $refundAmount = abs((float) $this->record->payments()->where('direction', 'refund')->sum('amount'));
        $netCollectedAmount = $receiptAmount - $refundAmount;
        $remainingAmount = max(0, $mustPayAmount - $netCollectedAmount);
        $balanceAmount = $netCollectedAmount - $mustPayAmount;

        $openInvoice = $this->record->invoices()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->latest('created_at')
            ->first();
        $latestInvoice = $openInvoice ?: $this->record->invoices()->latest('created_at')->first();

        $this->cachedPaymentSummary = [
            'total_treatment_amount' => $totalTreatmentAmount,
            'total_treatment_amount_formatted' => $this->formatMoney($totalTreatmentAmount),
            'total_discount_amount' => $totalDiscountAmount,
            'total_discount_amount_formatted' => $this->formatMoney($totalDiscountAmount),
            'must_pay_amount' => $mustPayAmount,
            'must_pay_amount_formatted' => $this->formatMoney($mustPayAmount),
            'net_collected_amount' => $netCollectedAmount,
            'net_collected_amount_formatted' => $this->formatMoney($netCollectedAmount),
            'remaining_amount' => $remainingAmount,
            'remaining_amount_formatted' => $this->formatMoney($remainingAmount),
            'balance_amount' => $balanceAmount,
            'balance_amount_formatted' => $this->formatMoney($balanceAmount),
            'balance_is_positive' => $balanceAmount >= 0,
            'create_payment_url' => route(
                'filament.admin.resources.payments.create',
                $latestInvoice ? ['invoice_id' => $latestInvoice->id] : []
            ),
        ];

        return $this->cachedPaymentSummary;
    }

    public function getLatestPrescriptionsProperty(): Collection
    {
        if ($this->cachedLatestPrescriptions !== null) {
            return $this->cachedLatestPrescriptions;
        }

        $this->cachedLatestPrescriptions = $this->record->prescriptions()
            ->latest('created_at')
            ->limit(5)
            ->get();

        return $this->cachedLatestPrescriptions;
    }

    public function getLatestInvoicesProperty(): Collection
    {
        if ($this->cachedLatestInvoices !== null) {
            return $this->cachedLatestInvoices;
        }

        $this->cachedLatestInvoices = $this->record->invoices()
            ->latest('created_at')
            ->limit(5)
            ->get();

        return $this->cachedLatestInvoices;
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, $this->workspaceTabs, true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTreatmentPlan')
                ->label('Tạo kế hoạch điều trị')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('success')
                ->url(fn () => route('filament.admin.resources.treatment-plans.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Action::make('createInvoice')
                ->label('Tạo hóa đơn')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->url(fn () => route('filament.admin.resources.invoices.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

            Action::make('createAppointment')
                ->label('Đặt lịch hẹn')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.appointments.create', [
                    'patient_id' => $this->record->id,
                ]))
                ->openUrlInNewTab(),

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
}
