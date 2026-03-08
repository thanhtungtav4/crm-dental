<?php

namespace App\Services;

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use App\Filament\Resources\MaterialBatches\MaterialBatchResource;
use App\Filament\Resources\Materials\MaterialResource;
use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Filament\Resources\TreatmentSessions\TreatmentSessionResource;
use App\Models\ClinicalNote;
use App\Models\Consent;
use App\Models\FactoryOrder;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class DeliveryOpsCenterService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $overviewCards = [];
        $sections = [];

        $treatmentSection = $this->canSeeTreatmentSection()
            ? $this->buildTreatmentSection()
            : null;
        $clinicalSection = $this->canSeeClinicalSection()
            ? $this->buildClinicalSection()
            : null;
        $inventorySection = $this->canSeeInventorySection()
            ? $this->buildInventorySection()
            : null;
        $labSection = $this->canSeeLabSection()
            ? $this->buildLabSection()
            : null;

        if (is_array($treatmentSection)) {
            $sections[] = $treatmentSection;
            $overviewCards[] = [
                'title' => 'Kế hoạch chờ duyệt',
                'value' => $treatmentSection['metrics']['draft_plans']['value'],
                'status' => $treatmentSection['metrics']['draft_plans']['label'],
                'tone' => $treatmentSection['metrics']['draft_plans']['tone'],
                'description' => 'Plan vẫn ở nháp và cần bác sĩ/quản lý chốt trước khi triển khai.',
                'meta' => [
                    'Phiên đã lên lịch '.$treatmentSection['metrics']['scheduled_sessions']['value'],
                ],
            ];
        }

        if (is_array($clinicalSection)) {
            $sections[] = $clinicalSection;
            $overviewCards[] = [
                'title' => 'Consent chờ ký',
                'value' => $clinicalSection['metrics']['pending_consents']['value'],
                'status' => $clinicalSection['metrics']['pending_consents']['label'],
                'tone' => $clinicalSection['metrics']['pending_consents']['tone'],
                'description' => 'Ca điều trị còn thiếu consent hoặc chưa chốt hồ sơ lâm sàng.',
                'meta' => [
                    'Thiếu EMR '.$clinicalSection['metrics']['missing_emr']['value'],
                ],
            ];
        }

        if (is_array($inventorySection)) {
            $sections[] = $inventorySection;
            $overviewCards[] = [
                'title' => 'SKU cần canh',
                'value' => $inventorySection['metrics']['low_stock']['value'],
                'status' => $inventorySection['metrics']['low_stock']['label'],
                'tone' => $inventorySection['metrics']['low_stock']['tone'],
                'description' => 'Vật tư đang low-stock hoặc sắp chạm reorder point.',
                'meta' => [
                    'Lô sắp/hết hạn '.$inventorySection['metrics']['batch_risk']['value'],
                ],
            ];
        }

        if (is_array($labSection)) {
            $sections[] = $labSection;
            $overviewCards[] = [
                'title' => 'Labo đang chạy',
                'value' => $labSection['metrics']['active_orders']['value'],
                'status' => $labSection['metrics']['active_orders']['label'],
                'tone' => $labSection['metrics']['active_orders']['tone'],
                'description' => 'Lệnh labo đang ordered hoặc in progress cần theo dõi giao hàng.',
                'meta' => [
                    'Draft '.$labSection['metrics']['draft_orders']['value'],
                ],
            ];
        }

        return [
            'overview_cards' => $overviewCards,
            'quick_links' => $this->quickLinks(),
            'sections' => $sections,
        ];
    }

    protected function canSeeTreatmentSection(): bool
    {
        return TreatmentPlanResource::canAccess() || TreatmentSessionResource::canAccess();
    }

    protected function canSeeClinicalSection(): bool
    {
        return PatientMedicalRecordResource::canAccess();
    }

    protected function canSeeInventorySection(): bool
    {
        return MaterialResource::canAccess() || MaterialBatchResource::canAccess();
    }

    protected function canSeeLabSection(): bool
    {
        return FactoryOrderResource::canAccess();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTreatmentSection(): array
    {
        $planQuery = $this->treatmentPlanQuery();
        $sessionQuery = $this->treatmentSessionQuery();

        $metrics = [
            'draft_plans' => $this->metric(
                label: 'Nháp',
                value: (int) (clone $planQuery)->where('status', TreatmentPlan::STATUS_DRAFT)->count(),
                tone: 'warning',
                description: 'Kế hoạch còn ở nháp, chưa qua approval workflow.',
            ),
            'approved_plans' => $this->metric(
                label: 'Đã duyệt',
                value: (int) (clone $planQuery)->where('status', TreatmentPlan::STATUS_APPROVED)->count(),
                tone: 'success',
                description: 'Kế hoạch đã chốt và sẵn sàng triển khai phiên điều trị.',
            ),
            'scheduled_sessions' => $this->metric(
                label: 'Phiên đã lên lịch',
                value: (int) (clone $sessionQuery)->where('status', 'scheduled')->count(),
                tone: 'info',
                description: 'Phiên điều trị đã lên lịch nhưng chưa bắt đầu.',
            ),
            'completed_sessions' => $this->metric(
                label: 'Phiên hoàn tất',
                value: (int) (clone $sessionQuery)->whereIn('status', ['done', 'completed'])->count(),
                tone: 'success',
                description: 'Phiên điều trị đã hoàn tất và có thể dùng cho đối soát tiến độ.',
            ),
        ];

        $rows = (clone $planQuery)
            ->with(['patient:id,full_name', 'doctor:id,name', 'branch:id,name'])
            ->orderByRaw(sprintf(
                'CASE status WHEN "%s" THEN 0 WHEN "%s" THEN 1 WHEN "%s" THEN 2 ELSE 3 END',
                TreatmentPlan::STATUS_DRAFT,
                TreatmentPlan::STATUS_APPROVED,
                TreatmentPlan::STATUS_IN_PROGRESS
            ))
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map(function (TreatmentPlan $plan): array {
                return [
                    'title' => (string) ($plan->title ?: 'Kế hoạch chưa đặt tên'),
                    'subtitle' => (string) ($plan->patient?->full_name ?? 'Chưa gắn bệnh nhân'),
                    'badge' => TreatmentPlan::statusLabel($plan->status),
                    'tone' => $this->treatmentPlanTone($plan->status),
                    'meta' => [
                        ['label' => 'Bác sĩ', 'value' => (string) ($plan->doctor?->name ?? 'Chưa gán')],
                        ['label' => 'Chi nhánh', 'value' => (string) ($plan->branch?->name ?? 'Không xác định')],
                        ['label' => 'Cập nhật', 'value' => $this->formatDateTime($plan->updated_at)],
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'title' => 'Workflow điều trị',
            'description' => 'Theo dõi plan và session để biết ca nào còn chờ duyệt, ca nào đã vào lịch điều trị.',
            'metrics' => $metrics,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClinicalSection(): array
    {
        $consentQuery = $this->consentQuery();
        $medicalRecordQuery = $this->medicalRecordQuery();
        $patientQuery = $this->patientQuery();
        $clinicalNoteQuery = $this->clinicalNoteQuery();

        $metrics = [
            'pending_consents' => $this->metric(
                label: 'Consent chờ ký',
                value: (int) (clone $consentQuery)->where('status', Consent::STATUS_PENDING)->count(),
                tone: 'warning',
                description: 'Ca điều trị có consent đang ở pending.',
            ),
            'signed_consents' => $this->metric(
                label: 'Consent đã ký',
                value: (int) (clone $consentQuery)->where('status', Consent::STATUS_SIGNED)->count(),
                tone: 'success',
                description: 'Consent đã ký hợp lệ trong scope hiện tại.',
            ),
            'missing_emr' => $this->metric(
                label: 'Thiếu hồ sơ y tế',
                value: (int) (clone $patientQuery)->whereDoesntHave('medicalRecord')->count(),
                tone: 'danger',
                description: 'Bệnh nhân chưa có hồ sơ y tế nền để bác sĩ rà nhanh.',
            ),
            'clinical_notes_today' => $this->metric(
                label: 'Phiếu khám hôm nay',
                value: (int) (clone $clinicalNoteQuery)->whereDate('date', now()->toDateString())->count(),
                tone: 'info',
                description: 'Phiếu khám lâm sàng ghi nhận trong ngày.',
            ),
        ];

        $pendingConsentRows = (clone $consentQuery)
            ->with(['patient:id,full_name', 'service:id,name', 'branch:id,name'])
            ->where('status', Consent::STATUS_PENDING)
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get()
            ->map(function (Consent $consent): array {
                return [
                    'title' => (string) ($consent->patient?->full_name ?? 'Consent chưa gắn bệnh nhân'),
                    'subtitle' => (string) ($consent->service?->name ?? 'Consent điều trị'),
                    'badge' => 'Consent chờ ký',
                    'tone' => 'warning',
                    'meta' => [
                        ['label' => 'Phiên bản', 'value' => (string) ($consent->consent_version ?: '-')],
                        ['label' => 'Chi nhánh', 'value' => (string) ($consent->branch?->name ?? 'Không xác định')],
                        ['label' => 'Cập nhật', 'value' => $this->formatDateTime($consent->updated_at)],
                    ],
                ];
            });

        $medicalRecordRows = (clone $medicalRecordQuery)
            ->with(['patient:id,full_name,first_branch_id', 'patient.branch:id,name', 'updatedBy:id,name'])
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get()
            ->map(function (PatientMedicalRecord $record): array {
                return [
                    'title' => (string) ($record->patient?->full_name ?? 'Hồ sơ y tế'),
                    'subtitle' => 'Hồ sơ y tế đã cập nhật',
                    'badge' => 'EMR',
                    'tone' => 'success',
                    'meta' => [
                        ['label' => 'Người cập nhật', 'value' => (string) ($record->updatedBy?->name ?? 'Không xác định')],
                        ['label' => 'Chi nhánh', 'value' => (string) ($record->patient?->branch?->name ?? 'Không xác định')],
                        ['label' => 'Cập nhật', 'value' => $this->formatDateTime($record->updated_at)],
                    ],
                ];
            });

        return [
            'title' => 'Hồ sơ lâm sàng',
            'description' => 'Kiểm tra consent, EMR và phiếu khám để chặn ca điều trị thiếu điều kiện lâm sàng.',
            'metrics' => $metrics,
            'rows' => $pendingConsentRows
                ->concat($medicalRecordRows)
                ->take(6)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildInventorySection(): array
    {
        $materialQuery = $this->materialQuery();
        $batchQuery = $this->materialBatchQuery();

        $metrics = [
            'low_stock' => $this->metric(
                label: 'Low stock',
                value: (int) (clone $materialQuery)->lowStock()->count(),
                tone: 'danger',
                description: 'SKU đã chạm ngưỡng min_stock.',
            ),
            'reorder_needed' => $this->metric(
                label: 'Cần reorder',
                value: (int) (clone $materialQuery)->needReorder()->count(),
                tone: 'warning',
                description: 'SKU cần tái đặt hàng theo reorder point.',
            ),
            'batch_risk' => $this->metric(
                label: 'Lô rủi ro',
                value: (int) (clone $batchQuery)
                    ->where(function (Builder $query): void {
                        $query->expired()->orWhere(function (Builder $inner): void {
                            $inner->expiringSoon(30);
                        });
                    })
                    ->count(),
                tone: 'warning',
                description: 'Lô đã hết hạn hoặc sắp hết hạn trong 30 ngày.',
            ),
            'active_batches' => $this->metric(
                label: 'Lô đang hoạt động',
                value: (int) (clone $batchQuery)->active()->count(),
                tone: 'info',
                description: 'Số lô active trong scope branch hiện tại.',
            ),
        ];

        $materialRows = (clone $materialQuery)
            ->with('branch:id,name')
            ->withCount('batches')
            ->orderByRaw('CASE WHEN stock_qty <= min_stock THEN 0 ELSE 1 END')
            ->orderBy('stock_qty')
            ->limit(6)
            ->get()
            ->map(function (Material $material): array {
                return [
                    'title' => (string) $material->name,
                    'subtitle' => (string) ($material->sku ?: 'Chưa có SKU'),
                    'badge' => $material->isLowStock() ? 'Low stock' : 'Theo dõi',
                    'tone' => $material->isLowStock() ? 'danger' : 'info',
                    'meta' => [
                        ['label' => 'Tồn tổng', 'value' => (string) $material->stock_qty],
                        ['label' => 'Min stock', 'value' => (string) $material->min_stock],
                        ['label' => 'Chi nhánh', 'value' => (string) ($material->branch?->name ?? 'Không xác định')],
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'title' => 'Cảnh báo kho',
            'description' => 'Theo dõi vật tư chạm ngưỡng, batch sắp/hết hạn và chuẩn bị reorder trước khi ca điều trị bị chặn.',
            'metrics' => $metrics,
            'rows' => $materialRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLabSection(): array
    {
        $factoryOrderQuery = $this->factoryOrderQuery();

        $metrics = [
            'draft_orders' => $this->metric(
                label: 'Draft',
                value: (int) (clone $factoryOrderQuery)->where('status', FactoryOrder::STATUS_DRAFT)->count(),
                tone: 'warning',
                description: 'Lệnh labo còn ở draft, chưa chốt gửi nhà cung cấp.',
            ),
            'active_orders' => $this->metric(
                label: 'Đang chạy',
                value: (int) (clone $factoryOrderQuery)
                    ->whereIn('status', [FactoryOrder::STATUS_ORDERED, FactoryOrder::STATUS_IN_PROGRESS])
                    ->count(),
                tone: 'info',
                description: 'Lệnh labo đang ordered hoặc in progress.',
            ),
            'delivered_orders' => $this->metric(
                label: 'Đã giao',
                value: (int) (clone $factoryOrderQuery)->where('status', FactoryOrder::STATUS_DELIVERED)->count(),
                tone: 'success',
                description: 'Lệnh labo đã giao thành công.',
            ),
            'due_soon' => $this->metric(
                label: 'Sắp tới hạn',
                value: (int) (clone $factoryOrderQuery)
                    ->whereNotNull('due_at')
                    ->whereBetween('due_at', [now()->startOfDay(), now()->copy()->addDays(3)->endOfDay()])
                    ->whereIn('status', [FactoryOrder::STATUS_ORDERED, FactoryOrder::STATUS_IN_PROGRESS])
                    ->count(),
                tone: 'warning',
                description: 'Lệnh labo sắp chạm hạn giao trong 3 ngày tới.',
            ),
        ];

        $rows = (clone $factoryOrderQuery)
            ->with(['patient:id,full_name', 'doctor:id,name', 'supplier:id,name'])
            ->whereIn('status', [
                FactoryOrder::STATUS_DRAFT,
                FactoryOrder::STATUS_ORDERED,
                FactoryOrder::STATUS_IN_PROGRESS,
            ])
            ->orderByRaw(sprintf(
                'CASE status WHEN "%s" THEN 0 WHEN "%s" THEN 1 WHEN "%s" THEN 2 ELSE 3 END',
                FactoryOrder::STATUS_DRAFT,
                FactoryOrder::STATUS_ORDERED,
                FactoryOrder::STATUS_IN_PROGRESS
            ))
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(function (FactoryOrder $order): array {
                return [
                    'title' => (string) ($order->order_no ?: 'Lệnh labo'),
                    'subtitle' => (string) ($order->patient?->full_name ?? 'Chưa gắn bệnh nhân'),
                    'badge' => FactoryOrder::statusOptions()[$order->status] ?? (string) $order->status,
                    'tone' => $this->factoryOrderTone($order->status),
                    'meta' => [
                        ['label' => 'Nhà cung cấp', 'value' => (string) ($order->supplier?->name ?? $order->vendor_name ?? 'Không xác định')],
                        ['label' => 'Bác sĩ', 'value' => (string) ($order->doctor?->name ?? 'Chưa gán')],
                        ['label' => 'Hạn giao', 'value' => $this->formatDateTime($order->due_at)],
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'title' => 'Labo & gia công',
            'description' => 'Điều phối lệnh labo đang mở, draft cần chốt và ca sắp tới hạn giao để không trễ hẹn bệnh nhân.',
            'metrics' => $metrics,
            'rows' => $rows,
        ];
    }

    protected function treatmentPlanQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(TreatmentPlan::query(), 'branch_id');
    }

    protected function treatmentSessionQuery(): Builder
    {
        $query = TreatmentSession::query();
        $authUser = BranchAccess::currentUser();

        if (! $authUser || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('treatmentPlan', function (Builder $planQuery) use ($branchIds): void {
            $planQuery->whereIn('branch_id', $branchIds);
        });
    }

    protected function consentQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(Consent::query(), 'branch_id');
    }

    protected function clinicalNoteQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(ClinicalNote::query(), 'branch_id');
    }

    protected function patientQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(Patient::query(), 'first_branch_id');
    }

    protected function medicalRecordQuery(): Builder
    {
        $query = PatientMedicalRecord::query();
        $authUser = BranchAccess::currentUser();

        if (! $authUser || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('patient', function (Builder $patientQuery) use ($branchIds): void {
            $patientQuery->whereIn('first_branch_id', $branchIds);
        });
    }

    protected function materialQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(Material::query(), 'branch_id');
    }

    protected function materialBatchQuery(): Builder
    {
        $query = MaterialBatch::query();
        $authUser = BranchAccess::currentUser();

        if (! $authUser || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('material', function (Builder $materialQuery) use ($branchIds): void {
            $materialQuery->whereIn('branch_id', $branchIds);
        });
    }

    protected function factoryOrderQuery(): Builder
    {
        return FactoryOrderResource::getEloquentQuery();
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function quickLinks(): array
    {
        $links = [
            [
                'label' => 'Kế hoạch điều trị',
                'description' => 'Xem pipeline plan, approval và các ca đang chờ triển khai.',
                'url' => TreatmentPlanResource::getUrl('index'),
                'visible' => TreatmentPlanResource::canAccess(),
            ],
            [
                'label' => 'Phiên điều trị',
                'description' => 'Đi thẳng tới danh sách session đã lên lịch hoặc đang xử lý.',
                'url' => TreatmentSessionResource::getUrl('index'),
                'visible' => TreatmentSessionResource::canAccess(),
            ],
            [
                'label' => 'Hồ sơ y tế',
                'description' => 'Rà EMR và các bệnh nhân cần hoàn thiện hồ sơ lâm sàng.',
                'url' => PatientMedicalRecordResource::getUrl('index'),
                'visible' => PatientMedicalRecordResource::canAccess(),
            ],
            [
                'label' => 'Vật tư',
                'description' => 'Theo dõi tồn tổng, reorder và các SKU có rủi ro.',
                'url' => MaterialResource::getUrl('index'),
                'visible' => MaterialResource::canAccess(),
            ],
            [
                'label' => 'Lô vật tư',
                'description' => 'Mở danh sách batch để rà lô hết hạn hoặc sắp hết hạn.',
                'url' => MaterialBatchResource::getUrl('index'),
                'visible' => MaterialBatchResource::canAccess(),
            ],
            [
                'label' => 'Lệnh labo',
                'description' => 'Theo dõi order đang chạy và ca sắp tới hạn giao.',
                'url' => FactoryOrderResource::getUrl('index'),
                'visible' => FactoryOrderResource::canAccess(),
            ],
        ];

        return collect($links)
            ->filter(fn (array $link): bool => (bool) ($link['visible'] ?? false))
            ->map(fn (array $link): array => [
                'label' => (string) $link['label'],
                'description' => (string) $link['description'],
                'url' => (string) $link['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function metric(string $label, int $value, string $tone, string $description): array
    {
        return [
            'label' => $label,
            'value' => number_format($value),
            'raw_value' => $value,
            'tone' => $tone,
            'description' => $description,
        ];
    }

    protected function treatmentPlanTone(?string $status): string
    {
        return match ((string) $status) {
            TreatmentPlan::STATUS_DRAFT => 'warning',
            TreatmentPlan::STATUS_APPROVED => 'success',
            TreatmentPlan::STATUS_IN_PROGRESS => 'info',
            TreatmentPlan::STATUS_COMPLETED => 'success',
            TreatmentPlan::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    protected function factoryOrderTone(?string $status): string
    {
        return match ((string) $status) {
            FactoryOrder::STATUS_DRAFT => 'warning',
            FactoryOrder::STATUS_ORDERED, FactoryOrder::STATUS_IN_PROGRESS => 'info',
            FactoryOrder::STATUS_DELIVERED => 'success',
            FactoryOrder::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    protected function formatDateTime(CarbonInterface|string|null $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return 'Chưa thiết lập';
        }

        return $value->format('d/m/Y H:i');
    }
}
