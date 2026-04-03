@props([
    'viewState',
])

<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <h3 class="crm-section-label">Kế hoạch điều trị</h3>
        <span class="crm-section-badge">{{ $viewState['plan_count'] }} hồ sơ</span>
    </div>
    <div class="flex items-center gap-2">
        <button type="button"
            @disabled(empty($viewState['plan_rows']))
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
                    <th>Kế hoạch</th>
                    <th>Tên thủ thuật</th>
                    <th class="is-center">KH đồng ý</th>
                    <th class="is-center">S.L</th>
                    <th class="is-right">Đơn giá</th>
                    <th class="is-right">Thành tiền</th>
                    <th class="is-right">Giảm giá (%)</th>
                    <th class="is-right">Tiền giảm giá</th>
                    <th class="is-right">VAT</th>
                    <th class="is-right">Tổng chi phí</th>
                    <th>Ghi chú</th>
                    <th class="is-center">Tình trạng</th>
                    <th class="is-center">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($viewState['plan_rows'] as $row)
                    <tr>
                        <td>{{ $row['tooth_label'] }}</td>
                        <td>{{ $row['diagnosis_labels'] }}</td>
                        <td>
                            @if($row['plan_url'])
                                <a
                                    href="{{ $row['plan_url'] }}"
                                    class="text-primary-600 hover:underline"
                                >
                                    {{ $row['plan_title'] }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $row['service_name'] }}</td>
                        <td class="is-center">
                            <span class="crm-treatment-status {{ $row['approval_badge_classes'] }}">
                                {{ $row['approval_label'] }}
                            </span>
                        </td>
                        <td class="is-center">{{ $row['quantity'] }}</td>
                        <td class="is-right">{{ $row['unit_price_text'] }}</td>
                        <td class="is-right">{{ $row['line_amount_text'] }}</td>
                        <td class="is-right">{{ $row['discount_percent_text'] }}</td>
                        <td class="is-right">{{ $row['discount_amount_text'] }}</td>
                        <td class="is-right">{{ $row['vat_amount_text'] }}</td>
                        <td class="is-right">{{ $row['total_amount_text'] }}</td>
                        <td>{{ $row['notes'] }}</td>
                        <td class="is-center">
                            <span class="crm-treatment-status {{ $row['status_badge_classes'] }}">
                                {{ $row['status_label'] }}
                            </span>
                        </td>
                        <td class="is-center">
                            <a href="{{ $row['edit_url'] }}"
                               class="crm-table-icon-btn"
                               title="Sửa hạng mục"
                               aria-label="Sửa hạng mục kế hoạch điều trị"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="crm-icon-14">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 3.5a2.12 2.12 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                                </svg>
                            </a>
                            <button type="button" disabled class="crm-table-icon-btn is-disabled" aria-label="Xóa hạng mục kế hoạch điều trị">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="crm-icon-14">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m-7 4v6m4-6v6m4-10v12a1 1 0 01-1 1H8a1 1 0 01-1-1V7h10z" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="crm-treatment-empty crm-treatment-empty-bordered">
                            Kế hoạch điều trị chưa có item
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="crm-treatment-summary border-t border-gray-300 px-4 py-3 text-xs text-gray-700">
        @foreach($viewState['summary_panels'] as $panel)
            <div @class([
                'grid grid-cols-2 gap-3 md:grid-cols-3',
                'mt-2' => ! $loop->first,
            ])>
                @foreach($panel['items'] as $summaryItem)
                    <div>{{ $summaryItem['label'] }}: <strong>{{ $summaryItem['value'] }}</strong></div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
