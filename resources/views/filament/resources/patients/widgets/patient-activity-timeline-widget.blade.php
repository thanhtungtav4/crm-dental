<div class="crm-activity-widget">
    <div class="crm-activity-widget-head">
        <h3 class="crm-activity-widget-title">Hoạt động gần đây</h3>
        <span class="crm-activity-widget-count">{{ $activityCount }} hoạt động</span>
    </div>

    @if(empty($activities))
        <div class="crm-activity-empty">
            <x-filament::icon icon="heroicon-o-clock" class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="crm-activity-empty-title">Chưa có hoạt động</h3>
            <p class="crm-activity-empty-desc">Lịch sử hoạt động của bệnh nhân sẽ hiển thị ở đây.</p>
        </div>
    @else
        <div class="crm-activity-list">
            @foreach($activities as $activity)
                <div class="crm-activity-item {{ $activity['type_class'] }}">
                    <div class="crm-activity-item-layout">
                        <div class="crm-activity-item-icon-wrap">
                            <div class="crm-activity-item-icon">
                                <x-filament::icon :icon="$activity['icon']" class="crm-activity-item-icon-svg" />
                            </div>
                        </div>

                        <div class="crm-activity-item-content">
                            <div class="crm-activity-item-title-row">
                                <span class="crm-activity-item-title">{{ $activity['title'] }}</span>
                                <span class="crm-activity-item-type">{{ $activity['type_label'] }}</span>
                            </div>

                            <p class="crm-activity-item-description">
                                {{ $activity['description_excerpt'] }}
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
                            <time datetime="{{ $activity['date_iso'] }}"
                                class="crm-activity-item-date-day">
                                {{ $activity['date_label'] }}
                            </time>
                            <div class="crm-activity-item-date-time">
                                {{ $activity['time_label'] }}
                            </div>
                            <div class="crm-activity-item-date-human">
                                {{ $activity['human_label'] }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($showsMaxActivitiesFooter)
            <div class="crm-activity-footer">
                Hiển thị 20 hoạt động gần nhất
            </div>
        @endif
    @endif
</div>
