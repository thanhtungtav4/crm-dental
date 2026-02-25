@php
    $formatMoney = fn($value) => number_format((float) $value, 0, ',', '.');
    $planCount = $planItems->pluck('treatment_plan_id')->unique()->count();
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h3 class="crm-section-label">Kế hoạch điều trị</h3>
            <span class="crm-section-badge">{{ $planCount }} hồ sơ</span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                @disabled($planItems->isEmpty())
                class="crm-btn crm-btn-outline h-8 px-3 text-xs disabled:opacity-50"
            >
                Sắp xếp kế hoạch điều trị
            </button>
            <button type="button"
                wire:click="openPlanModal"
                class="crm-btn crm-btn-primary h-8 px-3 text-xs"
            >
                Thêm kế hoạch điều trị
            </button>
        </div>
    </div>

    <div class="crm-treatment-card rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="crm-treatment-table-wrap">
            <table class="crm-treatment-table">
                <thead>
                    <tr>
                        <th>Răng số</th>
                        <th>Tình trạng răng</th>
                        <th>Tên thủ thuật</th>
                        <th class="is-center">KH đồng ý</th>
                        <th class="is-center">S.L</th>
                        <th class="is-right">Đơn giá</th>
                        <th class="is-right">Thành tiền</th>
                        <th class="is-right">Giảm giá (%)</th>
                        <th class="is-right">Tiền giảm giá</th>
                        <th class="is-right">Tổng chi phí</th>
                        <th>Ghi chú</th>
                        <th class="is-center">Tình trạng</th>
                        <th class="is-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($planItems as $item)
                        @php
                            $toothNumbers = $item->tooth_ids ?: ($item->tooth_number ? [$item->tooth_number] : []);
                            $toothLabel = $toothNumbers ? implode(' ', $toothNumbers) : '-';
                            $diagnosisLabels = collect($item->diagnosis_ids ?? [])
                                ->map(fn($id) => $diagnosisMap[$id]->condition?->name ?? null)
                                ->filter()
                                ->join(', ');

                            $unitPrice = (float) ($item->price ?? 0);
                            $lineAmount = ((int) ($item->quantity ?? 0)) * $unitPrice;
                            $discountPercent = (float) ($item->discount_percent ?? 0);
                            $discountAmount = (float) ($item->discount_amount ?? 0);
                            if ($discountAmount <= 0 && $discountPercent > 0) {
                                $discountAmount = ($discountPercent / 100) * $lineAmount;
                            }
                            $totalAmount = $lineAmount - $discountAmount;

                            $statusLabel = $item->getStatusLabel();
                            $statusClass = match ($item->status) {
                                'completed' => 'is-completed',
                                'in_progress' => 'is-progress',
                                'cancelled' => 'is-cancelled',
                                default => 'is-default',
                            };

                            $approvalClass = match ($item->getApprovalStatusBadgeColor()) {
                                'success' => 'is-completed',
                                'warning' => 'is-progress',
                                'danger' => 'is-cancelled',
                                default => 'is-default',
                            };
                        @endphp
                        <tr>
                            <td>{{ $toothLabel }}</td>
                            <td>{{ $diagnosisLabels ?: '-' }}</td>
                            <td>{{ $item->service?->name ?? $item->name }}</td>
                            <td class="is-center">
                                <span class="crm-treatment-status {{ $approvalClass }}">
                                    {{ $item->getApprovalStatusLabel() }}
                                </span>
                            </td>
                            <td class="is-center">{{ $item->quantity ?? 1 }}</td>
                            <td class="is-right">{{ $formatMoney($unitPrice) }}</td>
                            <td class="is-right">{{ $formatMoney($lineAmount) }}</td>
                            <td class="is-right">{{ $discountPercent ? rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.') : 0 }}</td>
                            <td class="is-right">{{ $formatMoney($discountAmount) }}</td>
                            <td class="is-right">{{ $formatMoney($totalAmount) }}</td>
                            <td>{{ $item->notes ?: '-' }}</td>
                            <td class="is-center">
                                <span class="crm-treatment-status {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="is-center">
                                <a href="{{ route('filament.admin.resources.plan-items.edit', ['record' => $item->id]) }}"
                                   class="crm-table-icon-btn"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="crm-icon-14">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 3.5a2.12 2.12 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </a>
                                <button type="button" disabled class="crm-table-icon-btn is-disabled">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="crm-icon-14">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 4v6m4-6v6m4-10v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="crm-treatment-empty crm-treatment-empty-bordered">
                                Kế hoạch điều trị chưa có item
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="crm-treatment-summary border-t border-gray-300 px-4 py-3 text-xs text-gray-700">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div>Chi phí dự kiến: <strong>{{ $formatMoney($estimatedTotal) }}</strong></div>
                <div>Tiền giảm giá: <strong>{{ $formatMoney($discountTotal) }}</strong></div>
                <div>Tổng chi phí dự kiến: <strong>{{ $formatMoney($totalCost) }}</strong></div>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-3 md:grid-cols-3">
                <div>Đã hoàn thành: <strong>{{ $formatMoney($completedCost) }}</strong></div>
                <div>Chưa hoàn thành: <strong>{{ $formatMoney($pendingCost) }}</strong></div>
            </div>
        </div>
    </div>

    @if($showPlanModal)
        <div class="crm-modal-backdrop crm-modal-z-50" wire:click="closePlanModal">
            <div class="crm-modal-card crm-modal-card-lg dark:bg-gray-900" wire:click.stop>
                <div class="crm-modal-header">
                    <h3 class="text-lg font-semibold text-gray-900">Thêm kế hoạch điều trị</h3>
                    <button type="button" wire:click="closePlanModal" class="crm-modal-close-btn">✕</button>
                </div>

                <div class="crm-modal-body">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-900">Thủ thuật thực hiện *</div>
                        <button type="button" wire:click="openProcedureModal" class="crm-btn crm-btn-outline px-3 py-2">
                            Thêm thủ thuật
                        </button>
                    </div>

                    <div class="crm-plan-editor-table-wrap">
                        <table class="crm-plan-editor-table">
                            <thead>
                                <tr>
                                    <th class="is-center">STT</th>
                                    <th>Răng số</th>
                                    <th>Tình trạng răng</th>
                                    <th>Tên thủ thuật</th>
                                    <th class="is-center">KH đồng ý</th>
                                    <th>Lý do từ chối</th>
                                    <th class="is-center">S.L</th>
                                    <th class="is-right">Đơn giá</th>
                                    <th class="is-right">Thành tiền</th>
                                    <th class="is-right">Giảm giá (%)</th>
                                    <th class="is-right">Tiền giảm giá</th>
                                    <th class="is-right">Tổng chi phí</th>
                                    <th>Ghi chú</th>
                                    <th class="is-center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($draftItems as $index => $item)
                                    @php
                                        $quantity = (int) ($item['quantity'] ?? 1);
                                        $price = (float) ($item['price'] ?? 0);
                                        $lineAmount = $quantity * $price;
                                        $discountPercent = (float) ($item['discount_percent'] ?? 0);
                                        $discountAmount = $discountPercent > 0 ? ($discountPercent / 100) * $lineAmount : 0;
                                        $totalAmount = $lineAmount - $discountAmount;
                                        $toothDisplay = collect($item['diagnosis_ids'] ?? [])
                                            ->map(fn($id) => $diagnosisDetails[(int) $id]['tooth_number'] ?? null)
                                            ->filter()
                                            ->unique()
                                            ->sortBy(fn($toothNumber) => (int) $toothNumber, SORT_NUMERIC)
                                            ->values()
                                            ->join(', ');
                                    @endphp
                                    <tr wire:key="draft-item-{{ $index }}">
                                        <td class="is-center">{{ $index + 1 }}</td>
                                        <td>
                                            <input type="text" readonly value="{{ $toothDisplay !== '' ? $toothDisplay : 'Tự động theo tình trạng răng' }}"
                                                class="crm-plan-editor-input">
                                        </td>
                                        <td>
                                            <select multiple wire:model="draftItems.{{ $index }}.diagnosis_ids"
                                                class="crm-plan-editor-select">
                                                @foreach($diagnosisOptions as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>{{ $item['service_name'] ?? 'Thủ thuật' }}</td>
                                        <td class="is-center">
                                            <select wire:model="draftItems.{{ $index }}.approval_status" class="crm-plan-editor-select">
                                                <option value="draft">Nháp</option>
                                                <option value="proposed">Đề xuất</option>
                                                <option value="approved">Đồng ý</option>
                                                <option value="declined">Từ chối</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text"
                                                wire:model.lazy="draftItems.{{ $index }}.approval_decline_reason"
                                                @disabled(($item['approval_status'] ?? 'proposed') !== 'declined')
                                                placeholder="Nhập lý do nếu từ chối"
                                                class="crm-plan-editor-input">
                                        </td>
                                        <td class="is-center">
                                            <input type="number" min="1" wire:model.lazy="draftItems.{{ $index }}.quantity"
                                                class="crm-plan-editor-input is-qty">
                                        </td>
                                        <td class="is-right">
                                            <input type="number" min="0" wire:model.lazy="draftItems.{{ $index }}.price"
                                                class="crm-plan-editor-input is-price">
                                        </td>
                                        <td class="is-right">{{ $formatMoney($lineAmount) }}</td>
                                        <td class="is-right">
                                            <input type="number" min="0" max="100" wire:model.lazy="draftItems.{{ $index }}.discount_percent"
                                                class="crm-plan-editor-input is-discount">
                                        </td>
                                        <td class="is-right">{{ $formatMoney($discountAmount) }}</td>
                                        <td class="is-right">{{ $formatMoney($totalAmount) }}</td>
                                        <td>
                                            <input type="text" wire:model.lazy="draftItems.{{ $index }}.notes"
                                                class="crm-plan-editor-input">
                                        </td>
                                        <td class="is-center">
                                            <button type="button" wire:click="removeDraftItem({{ $index }})" class="crm-plan-editor-remove-btn">Xóa</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="14" class="crm-plan-editor-empty">
                                            Chưa có thủ thuật được chọn.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">
                        Răng số được đồng bộ tự động theo các chẩn đoán đã chọn.
                    </p>
                </div>

                <div class="crm-modal-footer">
                    <button type="button" wire:click="closePlanModal" class="crm-btn crm-btn-outline px-4 py-2">
                        Hủy bỏ
                    </button>
                    <button type="button" wire:click="savePlanItems" class="crm-btn crm-btn-primary px-4 py-2">
                        Lưu thông tin
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showProcedureModal)
        <div class="crm-modal-backdrop crm-modal-z-60" wire:click="closeProcedureModal">
            <div class="crm-modal-card crm-modal-card-lg dark:bg-gray-900" wire:click.stop>
                <div class="crm-modal-header">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Chọn thủ thuật điều trị</h3>
                        <a href="{{ url('/setting/trick') }}" class="text-xs text-primary-600">Đi tới thiết lập thủ thuật</a>
                    </div>
                    <button type="button" wire:click="closeProcedureModal" class="crm-modal-close-btn">✕</button>
                </div>

                <div class="crm-modal-body">
                    <div class="mb-4">
                        <input type="text" wire:model.debounce.300ms="procedureSearch" placeholder="Tìm theo tên thủ thuật"
                            class="crm-procedure-search-input">
                    </div>

                    <div class="crm-procedure-layout">
                        <div class="crm-procedure-category-panel">
                            <button type="button"
                                wire:click="selectCategory(null)"
                                class="crm-procedure-category-btn {{ $selectedCategoryId ? '' : 'is-active' }}">
                                Tất cả nhóm thủ thuật
                            </button>
                            @foreach($categories as $category)
                                <button type="button"
                                    wire:click="selectCategory({{ $category->id }})"
                                    class="crm-procedure-category-btn {{ $selectedCategoryId === $category->id ? 'is-active' : '' }}">
                                    {{ $category->name }}
                                </button>
                            @endforeach
                        </div>

                        <div class="crm-procedure-services-wrap">
                            <table class="crm-procedure-services-table">
                                <thead>
                                    <tr>
                                        <th class="is-center">Chọn</th>
                                        <th>Tên thủ thuật</th>
                                        <th class="is-right">Đơn giá</th>
                                        <th>Quy trình thủ thuật</th>
                                        <th>Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($services as $service)
                                        <tr wire:key="service-{{ $service->id }}">
                                            <td class="is-center">
                                                <input type="checkbox" value="{{ $service->id }}" wire:model="selectedServiceIds" class="crm-check-sm">
                                            </td>
                                            <td>{{ $service->name }}</td>
                                            <td class="is-right">{{ $formatMoney($service->default_price ?? 0) }}</td>
                                            <td>{{ $service->description ?: '-' }}</td>
                                            <td>-</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="crm-plan-editor-empty">
                                                Không có thủ thuật phù hợp.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="crm-modal-footer">
                    <button type="button" wire:click="closeProcedureModal" class="crm-btn crm-btn-outline px-4 py-2">
                        Hủy bỏ
                    </button>
                    <button type="button" wire:click="addSelectedServices" class="crm-btn crm-btn-primary px-4 py-2">
                        Chọn thủ thuật
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
