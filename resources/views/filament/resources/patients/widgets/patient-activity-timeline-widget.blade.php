<div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb;"
    class="dark:bg-gray-900 dark:border-gray-700">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0;" class="dark:text-white">Hoạt động gần
            đây</h3>
        <span style="font-size: 13px; color: #6b7280; background: #f3f4f6; padding: 4px 12px; border-radius: 20px;"
            class="dark:bg-gray-700 dark:text-gray-300">{{ $this->getActivities()->count() }} hoạt động</span>
    </div>

    @if($this->getActivities()->isEmpty())
        <div style="text-align: center; padding: 48px 24px;">
            <x-filament::icon icon="heroicon-o-clock" class="mx-auto h-12 w-12 text-gray-400" />
            <h3 style="margin-top: 12px; font-size: 14px; font-weight: 500; color: #111827;">Chưa có hoạt động</h3>
            <p style="margin-top: 4px; font-size: 14px; color: #6b7280;">Lịch sử hoạt động của bệnh nhân sẽ hiển thị ở đây.
            </p>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 16px;">
            @foreach($this->getActivities() as $activity)
                @php
                    $bgColor = match ($activity['type']) {
                        'appointment' => '#eff6ff',
                        'treatment_plan' => '#fef3c7',
                        'invoice' => '#f3e8ff',
                        'payment' => '#dcfce7',
                        'branch_log' => '#e0f2fe',
                        'note' => '#f1f5f9',
                        default => '#f9fafb',
                    };
                    $borderColor = match ($activity['type']) {
                        'appointment' => '#3b82f6',
                        'treatment_plan' => '#f59e0b',
                        'invoice' => '#8b5cf6',
                        'payment' => '#22c55e',
                        'branch_log' => '#0284c7',
                        'note' => '#64748b',
                        default => '#9ca3af',
                    };
                    $iconBgColor = match ($activity['type']) {
                        'appointment' => '#3b82f6',
                        'treatment_plan' => '#f59e0b',
                        'invoice' => '#8b5cf6',
                        'payment' => '#22c55e',
                        'branch_log' => '#0284c7',
                        'note' => '#64748b',
                        default => '#9ca3af',
                    };
                    $typeName = match ($activity['type']) {
                        'appointment' => 'Lịch hẹn',
                        'treatment_plan' => 'Kế hoạch điều trị',
                        'invoice' => 'Hóa đơn',
                        'payment' => 'Thanh toán',
                        'branch_log' => 'Chuyển chi nhánh',
                        'note' => 'Ghi chú',
                        default => $activity['type'],
                    };
                @endphp
                <div style="background: {{ $bgColor }}; border-radius: 12px; padding: 16px; border-left: 4px solid {{ $borderColor }}; transition: transform 0.2s;"
                    class="dark:bg-gray-800 hover:translate-x-1">
                    <div style="display: flex; gap: 16px;">
                        {{-- Icon --}}
                        <div style="flex-shrink: 0;">
                            <div
                                style="width: 40px; height: 40px; background: {{ $iconBgColor }}; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <x-filament::icon :icon="$activity['icon']" style="width: 20px; height: 20px; color: white;" />
                            </div>
                        </div>
                        {{-- Content --}}
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span style="font-size: 15px; font-weight: 600; color: #111827;"
                                    class="dark:text-white">{{ $activity['title'] }}</span>
                                <span
                                    style="font-size: 11px; font-weight: 600; color: {{ $borderColor }}; background: white; padding: 2px 8px; border-radius: 12px; border: 1px solid {{ $borderColor }};">{{ $typeName }}</span>
                            </div>
                            <p style="margin-top: 4px; font-size: 14px; color: #4b5563;" class="dark:text-gray-300">
                                {{ Str::limit($activity['description'], 80) }}
                            </p>
                            @if(!empty($activity['meta']))
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 16px; margin-top: 12px;">
                                    @foreach($activity['meta'] as $label => $value)
                                        <div style="font-size: 12px;">
                                            <span style="color: #6b7280;">{{ $label }}:</span>
                                            <span style="color: #111827; font-weight: 500;"
                                                class="dark:text-gray-300">{{ $value }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if($activity['url'])
                                <div style="margin-top: 12px;">
                                    <a href="{{ $activity['url'] }}"
                                        style="font-size: 13px; color: {{ $borderColor }}; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        Xem chi tiết
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                        {{-- Date --}}
                        <div style="flex-shrink: 0; text-align: right;">
                            <time datetime="{{ $activity['date']->toIso8601String() }}"
                                style="font-size: 13px; font-weight: 600; color: #374151;" class="dark:text-gray-300">
                                {{ $activity['date']->format('d/m/Y') }}
                            </time>
                            <div style="font-size: 12px; color: #6b7280;">
                                {{ $activity['date']->format('H:i') }}
                            </div>
                            <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">
                                {{ $activity['date']->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($this->getActivities()->count() === 20)
            <div
                style="text-align: center; font-size: 13px; color: #6b7280; padding-top: 16px; margin-top: 16px; border-top: 1px solid #e5e7eb;">
                Hiển thị 20 hoạt động gần nhất
            </div>
        @endif
    @endif
</div>
