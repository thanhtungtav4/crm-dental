<div class="crm-activity-widget">
    <div class="crm-activity-widget-head">
        <h3 class="crm-activity-widget-title">Hoạt động gần đây</h3>
        <span class="crm-activity-widget-count">{{ $this->getActivities()->count() }} hoạt động</span>
    </div>

    @if($this->getActivities()->isEmpty())
        <div class="crm-activity-empty">
            <x-filament::icon icon="heroicon-o-clock" class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="crm-activity-empty-title">Chưa có hoạt động</h3>
            <p class="crm-activity-empty-desc">Lịch sử hoạt động của bệnh nhân sẽ hiển thị ở đây.</p>
        </div>
    @else
        <div class="crm-activity-list">
            @foreach($this->getActivities() as $activity)
                @php
                    $typeClass = match ($activity['type']) {
                        'appointment' => 'is-appointment',
                        'treatment_plan' => 'is-treatment-plan',
                        'invoice' => 'is-invoice',
                        'payment' => 'is-payment',
                        'branch_log' => 'is-branch-log',
                        'note' => 'is-note',
                        'audit' => 'is-default',
                        default => 'is-default',
                    };

                    $typeName = match ($activity['type']) {
                        'appointment' => 'Lịch hẹn',
                        'treatment_plan' => 'Kế hoạch điều trị',
                        'invoice' => 'Hóa đơn',
                        'payment' => 'Thanh toán',
                        'branch_log' => 'Chuyển chi nhánh',
                        'note' => 'Ghi chú',
                        'audit' => 'Nhật ký hệ thống',
                        default => $activity['type'],
                    };
                @endphp
                <div class="crm-activity-item {{ $typeClass }}">
                    <div class="crm-activity-item-layout">
                        <div class="crm-activity-item-icon-wrap">
                            <div class="crm-activity-item-icon">
                                <x-filament::icon :icon="$activity['icon']" class="crm-activity-item-icon-svg" />
                            </div>
                        </div>

                        <div class="crm-activity-item-content">
                            <div class="crm-activity-item-title-row">
                                <span class="crm-activity-item-title">{{ $activity['title'] }}</span>
                                <span class="crm-activity-item-type">{{ $typeName }}</span>
                            </div>

                            <p class="crm-activity-item-description">
                                {{ Str::limit($activity['description'], 80) }}
                            </p>

                            @if(!empty($activity['meta']))
                                <div class="crm-activity-item-meta-grid">
                                    @foreach($activity['meta'] as $label => $value)
                                        <div class="crm-activity-item-meta-row">
                                            <span class="crm-activity-item-meta-label">{{ $label }}:</span>
                                            <span class="crm-activity-item-meta-value">{{ $value }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($activity['url'])
                                <div class="crm-activity-item-link-wrap">
                                    <a href="{{ $activity['url'] }}" class="crm-activity-item-link">
                                        Xem chi tiết
                                        <svg class="crm-activity-item-link-icon" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div class="crm-activity-item-date">
                            <time datetime="{{ $activity['date']->toIso8601String() }}"
                                class="crm-activity-item-date-day">
                                {{ $activity['date']->format('d/m/Y') }}
                            </time>
                            <div class="crm-activity-item-date-time">
                                {{ $activity['date']->format('H:i') }}
                            </div>
                            <div class="crm-activity-item-date-human">
                                {{ $activity['date']->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($this->getActivities()->count() === 20)
            <div class="crm-activity-footer">
                Hiển thị 20 hoạt động gần nhất
            </div>
        @endif
    @endif
</div>
