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

    <div class="crm-treatment-card rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" style="border-radius: 8px; overflow: hidden;">
        <div class="crm-treatment-table-wrap" style="overflow-x: auto;">
            <table class="crm-treatment-table" style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead style="background: #4b4b4b; color: #ffffff;">
                    <tr>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: left; white-space: nowrap;">Răng số</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: left; white-space: nowrap;">Tình trạng răng</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: left; white-space: nowrap;">Tên thủ thuật</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center; white-space: nowrap;">KH đồng ý</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center; white-space: nowrap;">S.L</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right; white-space: nowrap;">Đơn giá</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right; white-space: nowrap;">Thành tiền</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right; white-space: nowrap;">Giảm giá (%)</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right; white-space: nowrap;">Tiền giảm giá</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right; white-space: nowrap;">Tổng chi phí</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: left; white-space: nowrap;">Ghi chú</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center; white-space: nowrap;">Tình trạng</th>
                        <th style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center; white-space: nowrap;">Thao tác</th>
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

                            $isApproved = (bool) ($item->patient_approved ?? false);
                        @endphp
                        <tr>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db;">{{ $toothLabel }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db;">{{ $diagnosisLabels ?: '-' }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db;">{{ $item->service?->name ?? $item->name }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center;">
                                <input type="checkbox" disabled @checked($isApproved) style="width: 14px; height: 14px; accent-color: #8b5cf6;">
                            </td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center;">{{ $item->quantity ?? 1 }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right;">{{ $formatMoney($unitPrice) }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right;">{{ $formatMoney($lineAmount) }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right;">{{ $discountPercent ? rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.') : 0 }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right;">{{ $formatMoney($discountAmount) }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: right;">{{ $formatMoney($totalAmount) }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db;">{{ $item->notes ?: '-' }}</td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center;">
                                <span class="crm-treatment-status {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td style="padding: 8px 10px; border: 1px solid #d1d5db; text-align: center;">
                                <a href="{{ route('filament.admin.resources.plan-items.edit', ['record' => $item->id]) }}"
                                   class="crm-table-icon-btn"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 14px; height: 14px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 3.5a2.12 2.12 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                    </svg>
                                </a>
                                <button type="button" disabled class="crm-table-icon-btn is-disabled">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 14px; height: 14px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 4v6m4-6v6m4-10v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="crm-treatment-empty" style="padding: 18px; text-align: center; color: #6b7280; border: 1px solid #d1d5db;">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click="closePlanModal">
            <div class="w-full max-w-6xl rounded-xl bg-white shadow-xl dark:bg-gray-900" style="border-radius: 12px;" wire:click.stop>
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4" style="display: flex; align-items: center; justify-content: space-between;">
                    <h3 class="text-lg font-semibold text-gray-900">Thêm kế hoạch điều trị</h3>
                    <button type="button" wire:click="closePlanModal" style="color: #6b7280;">✕</button>
                </div>

                <div class="p-6">
                    <div class="mb-4 flex items-center justify-between" style="display: flex; align-items: center; justify-content: space-between;">
                        <div class="text-sm font-semibold text-gray-900">Thủ thuật thực hiện *</div>
                        <button type="button" wire:click="openProcedureModal" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600" style="border-radius: 6px;">
                            Thêm thủ thuật
                        </button>
                    </div>

                    <div style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead style="background: #f3f4f6; color: #374151;">
                                <tr>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">STT</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left;">Răng số</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left;">Tình trạng răng</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left;">Tên thủ thuật</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: center;">KH đồng ý</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: center;">S.L</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">Đơn giá</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">Thành tiền</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">Giảm giá (%)</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">Tiền giảm giá</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: right;">Tổng chi phí</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: left;">Ghi chú</th>
                                    <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; text-align: center;">Thao tác</th>
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
                                    @endphp
                                    <tr wire:key="draft-item-{{ $index }}">
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: center;">{{ $index + 1 }}</td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">
                                            <input type="text" wire:model.lazy="draftItems.{{ $index }}.tooth_text" placeholder="VD: 11,12"
                                                style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 6px;">
                                        </td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">
                                            <select multiple wire:model="draftItems.{{ $index }}.diagnosis_ids"
                                                style="width: 100%; min-width: 140px; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 6px; height: 44px;">
                                                @foreach($diagnosisOptions as $id => $label)
                                                    <option value="{{ $id }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">{{ $item['service_name'] ?? 'Thủ thuật' }}</td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                            <input type="checkbox" wire:model="draftItems.{{ $index }}.patient_approved" style="width: 14px; height: 14px; accent-color: #8b5cf6;">
                                        </td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                            <input type="number" min="1" wire:model.lazy="draftItems.{{ $index }}.quantity"
                                                style="width: 60px; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 6px; text-align: center;">
                                        </td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: right;">
                                            <input type="number" min="0" wire:model.lazy="draftItems.{{ $index }}.price"
                                                style="width: 90px; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 6px; text-align: right;">
                                        </td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: right;">{{ $formatMoney($lineAmount) }}</td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: right;">
                                            <input type="number" min="0" max="100" wire:model.lazy="draftItems.{{ $index }}.discount_percent"
                                                style="width: 70px; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 6px; text-align: right;">
                                        </td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: right;">{{ $formatMoney($discountAmount) }}</td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: right;">{{ $formatMoney($totalAmount) }}</td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">
                                            <input type="text" wire:model.lazy="draftItems.{{ $index }}.notes"
                                                style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 6px;">
                                        </td>
                                        <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                            <button type="button" wire:click="removeDraftItem({{ $index }})" style="color: #ef4444;">Xóa</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" style="padding: 18px; text-align: center; color: #6b7280;">
                                            Chưa có thủ thuật được chọn.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4" style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                    <button type="button" wire:click="closePlanModal" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600" style="border-radius: 6px;">
                        Hủy bỏ
                    </button>
                    <button type="button" wire:click="savePlanItems" class="rounded-md bg-primary-500 px-4 py-2 text-sm font-semibold text-white" style="border-radius: 6px; background: #8b5cf6;">
                        Lưu thông tin
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showProcedureModal)
        <div class="fixed inset-0 flex items-center justify-center bg-black/50 p-4" style="z-index: 60;" wire:click="closeProcedureModal">
            <div class="w-full max-w-6xl rounded-xl bg-white shadow-xl dark:bg-gray-900" style="border-radius: 12px;" wire:click.stop>
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4" style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Chọn thủ thuật điều trị</h3>
                        <a href="{{ url('/setting/trick') }}" class="text-xs text-primary-600">Đi tới thiết lập thủ thuật</a>
                    </div>
                    <button type="button" wire:click="closeProcedureModal" style="color: #6b7280;">✕</button>
                </div>

                <div class="p-6">
                    <div class="mb-4">
                        <input type="text" wire:model.debounce.300ms="procedureSearch" placeholder="Tìm theo tên thủ thuật"
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px;">
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[240px,1fr]" style="display: grid; grid-template-columns: 240px 1fr; gap: 16px;">
                        <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; max-height: 360px; overflow: auto;">
                            <button type="button"
                                wire:click="selectCategory(null)"
                                style="display: block; width: 100%; text-align: left; padding: 6px 8px; border-radius: 6px; background: {{ $selectedCategoryId ? 'transparent' : '#eef2ff' }};">
                                Tất cả nhóm thủ thuật
                            </button>
                            @foreach($categories as $category)
                                <button type="button"
                                    wire:click="selectCategory({{ $category->id }})"
                                    style="display: block; width: 100%; text-align: left; padding: 6px 8px; border-radius: 6px; margin-top: 4px; background: {{ $selectedCategoryId === $category->id ? '#eef2ff' : 'transparent' }};">
                                    {{ $category->name }}
                                </button>
                            @endforeach
                        </div>

                        <div style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                <thead style="background: #f3f4f6;">
                                    <tr>
                                        <th style="padding: 8px 10px; text-align: center; border-bottom: 1px solid #e5e7eb;">Chọn</th>
                                        <th style="padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb;">Tên thủ thuật</th>
                                        <th style="padding: 8px 10px; text-align: right; border-bottom: 1px solid #e5e7eb;">Đơn giá</th>
                                        <th style="padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb;">Quy trình thủ thuật</th>
                                        <th style="padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb;">Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($services as $service)
                                        <tr wire:key="service-{{ $service->id }}">
                                            <td style="padding: 8px 10px; text-align: center; border-bottom: 1px solid #f1f5f9;">
                                                <input type="checkbox" value="{{ $service->id }}" wire:model="selectedServiceIds" style="width: 14px; height: 14px; accent-color: #8b5cf6;">
                                            </td>
                                            <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">{{ $service->name }}</td>
                                            <td style="padding: 8px 10px; text-align: right; border-bottom: 1px solid #f1f5f9;">{{ $formatMoney($service->default_price ?? 0) }}</td>
                                            <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">{{ $service->description ?: '-' }}</td>
                                            <td style="padding: 8px 10px; border-bottom: 1px solid #f1f5f9;">-</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" style="padding: 18px; text-align: center; color: #6b7280;">
                                                Không có thủ thuật phù hợp.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4" style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                    <button type="button" wire:click="closeProcedureModal" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600" style="border-radius: 6px;">
                        Hủy bỏ
                    </button>
                    <button type="button" wire:click="addSelectedServices" class="rounded-md bg-primary-500 px-4 py-2 text-sm font-semibold text-white" style="border-radius: 6px; background: #8b5cf6;">
                        Chọn thủ thuật
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
