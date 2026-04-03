@props([
    'panel',
])

<div class="crm-treatment-progress-stack">
    <div class="crm-treatment-progress-head">
        <h3 class="crm-section-label">{{ $panel['section_title'] }}</h3>
        <span class="crm-section-badge">{{ $panel['summary_badge'] }}</span>
    </div>

    <div class="crm-treatment-card">
        <div class="crm-treatment-subhead">
            <div class="crm-treatment-subhead-title">{{ $panel['card_title'] }}</div>
            <div class="crm-treatment-subhead-actions">
                <span class="crm-treatment-subhead-count">{{ $panel['total_amount_text'] }}</span>
                @if($panel['primary_action'])
                    <a
                        href="{{ $panel['primary_action']['url'] }}"
                        class="crm-btn {{ $panel['primary_action']['button_class'] }} crm-btn-md"
                        style="color: #ffffff;"
                    >
                        {{ $panel['primary_action']['label'] }}
                    </a>
                @endif
            </div>
        </div>

        @if($panel['has_day_summaries'])
            <div class="crm-treatment-table-wrap">
                <table class="crm-treatment-table">
                    <thead>
                        <tr>
                            <th>Ngày điều trị</th>
                            <th class="is-center">Số phiên</th>
                            <th class="is-right">Tổng chi phí ngày</th>
                            <th>Tình trạng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($panel['day_summaries'] as $summary)
                            <tr>
                                <td>{{ $summary['progress_date'] }}</td>
                                <td class="is-center">{{ $summary['sessions_count'] }}</td>
                                <td class="is-right">{{ $summary['day_total_amount_formatted'] }}đ</td>
                                <td>{{ $summary['status_label'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="crm-treatment-table-wrap">
            <table class="crm-treatment-table">
                <thead>
                    <tr>
                        <th>Ngày điều trị</th>
                        <th>Răng số</th>
                        <th>Thủ thuật</th>
                        <th>Nội dung thủ thuật</th>
                        <th>Bác sĩ</th>
                        <th>Trợ thủ</th>
                        <th class="is-center">S.L</th>
                        <th class="is-right">Đơn giá</th>
                        <th class="is-right">Thành tiền</th>
                        <th>Tình trạng</th>
                        <th class="is-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($panel['rows'] as $session)
                        <tr>
                            <td>{{ $session['performed_at'] }}</td>
                            <td>{{ $session['tooth_label'] }}</td>
                            <td>{{ $session['plan_item_name'] }}</td>
                            <td>{{ $session['procedure'] }}</td>
                            <td>{{ $session['doctor_name'] }}</td>
                            <td>{{ $session['assistant_name'] }}</td>
                            <td class="is-center">{{ $session['quantity'] }}</td>
                            <td class="is-right">{{ $session['price_formatted'] }}</td>
                            <td class="is-right">{{ $session['total_amount_formatted'] }}</td>
                            <td>
                                <span class="crm-treatment-status {{ $session['status_class'] }}">
                                    {{ $session['status_label'] }}
                                </span>
                            </td>
                            <td class="is-center">
                                @if($session['edit_action'])
                                    <a
                                        href="{{ $session['edit_action']['url'] }}"
                                        class="{{ $session['edit_action']['button_class'] }}"
                                        title="{{ $session['edit_action']['label'] }}"
                                        aria-label="{{ $session['edit_action']['label'] }}"
                                    >
                                        <x-filament::icon :icon="$session['edit_action']['icon']" class="crm-icon-14" />
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">{{ $session['edit_action_placeholder'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="crm-treatment-empty">
                                {{ $panel['empty_text'] }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
