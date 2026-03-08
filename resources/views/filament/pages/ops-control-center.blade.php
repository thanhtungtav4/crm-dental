@php
    $toneBadgeClasses = [
        'success' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
        'warning' => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
        'danger' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
        'info' => 'border-info-200 bg-info-50 text-info-700 dark:border-info-900/60 dark:bg-info-950/30 dark:text-info-200',
    ];
    $defaultBadgeClasses = 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200';
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ $this->getSubheading() }}
        </p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($this->getOverviewCards() as $card)
                <x-filament::section>
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                    {{ $card['title'] }}
                                </p>
                                <p class="text-lg font-semibold text-gray-950 dark:text-white">
                                    {{ $card['value'] }}
                                </p>
                            </div>

                            <span class="{{ $toneBadgeClasses[$card['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ $card['status'] }}
                            </span>
                        </div>

                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ $card['description'] }}
                        </p>

                        @if(! empty($card['meta']))
                            <div class="space-y-1">
                                @foreach($card['meta'] as $meta)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $meta }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="space-y-6">
                <x-filament::section heading="Automation actor" description="Kiểm tra service account dùng cho scheduler và command automation.">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="{{ $toneBadgeClasses[$this->getAutomationActor()['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ $this->getAutomationActor()['status'] ?? 'Info' }}
                            </span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $this->getAutomationActor()['label'] ?? 'Chưa cấu hình' }}
                            </span>
                        </div>

                        <dl class="grid gap-3 md:grid-cols-3">
                            @foreach(($this->getAutomationActor()['meta'] ?? []) as $meta)
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/60">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if(! empty($this->getAutomationActor()['issues']))
                            <div class="space-y-2">
                                @foreach($this->getAutomationActor()['issues'] as $issue)
                                    <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-100">
                                        <div class="font-semibold">{{ strtoupper($issue['severity']) }} · {{ $issue['code'] }}</div>
                                        <div class="mt-1">{{ $issue['message'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section heading="Backup & restore" description="Runtime path thật và fixture fail/pass để QA chạy smoke local.">
                    <div class="space-y-4">
                        @php($runtimeBackup = $this->getRuntimeBackup())
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $runtimeBackup['label'] ?? 'Runtime backup path' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $runtimeBackup['description'] ?? '' }}</p>
                                </div>
                                <span class="{{ $toneBadgeClasses[$runtimeBackup['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                    {{ $runtimeBackup['status'] ?? 'Unknown' }}
                                </span>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-3">
                                @foreach(($runtimeBackup['meta'] ?? []) as $meta)
                                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                        <div class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</div>
                                        <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3 rounded-xl border border-dashed border-gray-300 px-4 py-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                {{ $runtimeBackup['path'] ?? '-' }}
                                @if(filled($runtimeBackup['error'] ?? null))
                                    <div class="mt-1 font-medium text-danger-700 dark:text-danger-300">{{ $runtimeBackup['error'] }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            @foreach($this->getBackupFixtures() as $fixture)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $fixture['label'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fixture['description'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$fixture['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $fixture['status'] }}
                                        </span>
                                    </div>

                                    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                        @foreach(($fixture['meta'] ?? []) as $meta)
                                            <div class="flex items-center justify-between gap-3">
                                                <span>{{ $meta['label'] }}</span>
                                                <span class="font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-3 rounded-xl border border-dashed border-gray-300 px-3 py-2 text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        {{ $fixture['path'] }}
                                        @if(filled($fixture['error'] ?? null))
                                            <div class="mt-1 font-medium text-danger-700 dark:text-danger-300">{{ $fixture['error'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Readiness artifacts" description="Theo dõi artifact report/signoff local và runtime để release gate không còn nằm trong CLI.">
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach(array_filter([$this->getLatestRuntimeReport(), $this->getLatestRuntimeSignoff()]) as $artifact)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $artifact['label'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $artifact['description'] }}</p>
                                    </div>
                                    <span class="{{ $toneBadgeClasses[$artifact['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                        {{ $artifact['status'] }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                    @foreach(($artifact['meta'] ?? []) as $meta)
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ $meta['label'] }}</span>
                                            <span class="font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-3 rounded-xl border border-dashed border-gray-300 px-3 py-2 text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    {{ $artifact['path'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 grid gap-4 xl:grid-cols-2">
                        <div class="space-y-4">
                            @foreach($this->getReadinessFixtures() as $fixture)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $fixture['label'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fixture['description'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$fixture['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $fixture['status'] }}
                                        </span>
                                    </div>

                                    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                        @foreach(($fixture['meta'] ?? []) as $meta)
                                            <div class="flex items-center justify-between gap-3">
                                                <span>{{ $meta['label'] }}</span>
                                                <span class="font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-3 rounded-xl border border-dashed border-gray-300 px-3 py-2 text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        {{ $fixture['path'] }}
                                        @if(filled($fixture['error'] ?? null))
                                            <div class="mt-1 font-medium text-danger-700 dark:text-danger-300">{{ $fixture['error'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-4">
                            @foreach($this->getSignoffFixtures() as $fixture)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $fixture['label'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fixture['description'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$fixture['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $fixture['status'] }}
                                        </span>
                                    </div>

                                    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                        @foreach(($fixture['meta'] ?? []) as $meta)
                                            <div class="flex items-center justify-between gap-3">
                                                <span>{{ $meta['label'] }}</span>
                                                <span class="font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-3 rounded-xl border border-dashed border-gray-300 px-3 py-2 text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        {{ $fixture['path'] }}
                                        @if(filled($fixture['error'] ?? null))
                                            <div class="mt-1 font-medium text-warning-700 dark:text-warning-300">{{ $fixture['error'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <div class="space-y-6">
                <x-filament::section heading="Integrations & secret rotation" description="Nhìn nhanh grace token, retention backlog và chuyển tiếp sang trang integration settings.">
                    @php($integrations = $this->getIntegrations())

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <div>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $integrations['status'] ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Secret rotation grace state và retention candidate cho web lead, webhook, EMR, Google Calendar.</p>
                            </div>
                            <span class="{{ $toneBadgeClasses[$integrations['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ count($integrations['expired_grace_rotations'] ?? []) }} expired
                            </span>
                        </div>

                        <dl class="grid gap-3 md:grid-cols-3">
                            @foreach(($integrations['meta'] ?? []) as $meta)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Active grace tokens</p>
                                    <span class="{{ $toneBadgeClasses[empty($integrations['active_grace_rotations']) ? 'success' : 'warning'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                        {{ count($integrations['active_grace_rotations'] ?? []) }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-3">
                                    @forelse(($integrations['active_grace_rotations'] ?? []) as $rotation)
                                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900/60">
                                            <div class="font-medium text-gray-950 dark:text-white">{{ $rotation['display_name'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hết hạn {{ $rotation['grace_expires_at'] }} · còn {{ $rotation['remaining_minutes'] }} phút</div>
                                        </div>
                                    @empty
                                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                            Không có grace token nào còn hiệu lực.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Expired grace tokens</p>
                                    <span class="{{ $toneBadgeClasses[empty($integrations['expired_grace_rotations']) ? 'success' : 'danger'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                        {{ count($integrations['expired_grace_rotations'] ?? []) }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-3">
                                    @forelse(($integrations['expired_grace_rotations'] ?? []) as $rotation)
                                        <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-900 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-100">
                                            <div class="font-medium">{{ $rotation['display_name'] }}</div>
                                            <div class="mt-1 text-xs">Hết hạn {{ $rotation['grace_expires_at'] }} · quá hạn {{ $rotation['expired_minutes'] }} phút</div>
                                        </div>
                                    @empty
                                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                            Không có grace token nào chờ revoke.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @foreach(($integrations['retention_candidates'] ?? []) as $candidate)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $candidate['label'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $candidate['description'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$candidate['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $candidate['total'] }} candidate
                                        </span>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Retention {{ $candidate['retention_days'] }} ngày</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap gap-3">
                            @foreach(($integrations['links'] ?? []) as $link)
                                <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                                    {{ $link['label'] }}
                                </x-filament::link>
                            @endforeach
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="KPI freshness & alerts" description="Tóm tắt snapshot SLA, hot aggregate readiness và owner của alert đang mở theo branch scope hiện tại.">
                    @php($kpi = $this->getKpi())

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <div>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $kpi['status'] ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Snapshot date {{ $kpi['snapshot_date'] ?? '-' }} · visible branches theo branch scope hiện tại.</p>
                            </div>
                            <span class="{{ $toneBadgeClasses[$kpi['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ count($kpi['open_alerts'] ?? []) }} open
                            </span>
                        </div>

                        <dl class="grid gap-3 md:grid-cols-3">
                            @foreach(($kpi['meta'] ?? []) as $meta)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach(($kpi['snapshot_counts'] ?? []) as $key => $count)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ str($key)->replace('_', ' ')->title() }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[in_array($key, ['stale', 'missing'], true) && $count > 0 ? 'danger' : 'success'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ number_format($count) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach(($kpi['aggregate_readiness'] ?? []) as $aggregate)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $aggregate['label'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$aggregate['ready'] ? 'success' : 'warning'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $aggregate['ready'] ? 'Ready' : 'Fallback raw' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-3">
                            @forelse(($kpi['open_alerts'] ?? []) as $alert)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $alert['title'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $alert['branch'] }} · owner {{ $alert['owner'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$alert['status'] === 'new' ? 'danger' : 'warning'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ strtoupper($alert['status']) }} · {{ $alert['severity'] }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Không có KPI alert mở trong branch scope hiện tại.
                                </div>
                            @endforelse
                        </div>

                        <div class="flex flex-wrap gap-3">
                            @foreach(($kpi['links'] ?? []) as $link)
                                <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                                    {{ $link['label'] }}
                                </x-filament::link>
                            @endforeach
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Finance & collections" description="Nhìn nhanh aging sync, dunning và receipt reversal watchlist theo branch scope hiện tại.">
                    @php($finance = $this->getFinance())

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <div>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $finance['status'] ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Finance watchlist branch-scoped cho overdue sync, collections và receipt reversal sau mỗi lần reset seed.</p>
                            </div>
                            <span class="{{ $toneBadgeClasses[$finance['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ count($finance['watchlist'] ?? []) }} watch item
                            </span>
                        </div>

                        <dl class="grid gap-3 md:grid-cols-2">
                            @foreach(($finance['meta'] ?? []) as $meta)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach(($finance['signals'] ?? []) as $signal)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $signal['label'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$signal['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $signal['value'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-3">
                            @forelse(($finance['watchlist'] ?? []) as $item)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['subtitle'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$item['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $item['badge'] }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</p>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Không có finance watchlist nào trong branch scope hiện tại.
                                </div>
                            @endforelse
                        </div>

                        @if(! empty($finance['links']))
                            <div class="flex flex-wrap gap-3">
                                @foreach(($finance['links'] ?? []) as $link)
                                    <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                                        {{ $link['label'] }}
                                    </x-filament::link>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section heading="ZNS triage cockpit" description="Tóm tắt backlog retry/dead-letter, retention và lối tắt sang campaign workflow.">
                    @php($zns = $this->getZns())

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <div>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $zns['status'] ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Backlog automation, campaign failures và retention candidate sau mỗi lần seed/reset.</p>
                            </div>
                            <span class="{{ $toneBadgeClasses[$zns['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ count($zns['summary_cards'] ?? []) }} signals
                            </span>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach(($zns['summary_cards'] ?? []) as $card)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $card['label'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$card['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ number_format($card['value']) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-3">
                            @foreach(($zns['retention_candidates'] ?? []) as $candidate)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $candidate['label'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $candidate['description'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$candidate['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $candidate['total'] }} candidate
                                        </span>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Retention {{ $candidate['retention_days'] }} ngày</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap gap-3">
                            @foreach(($zns['links'] ?? []) as $link)
                                <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                                    {{ $link['label'] }}
                                </x-filament::link>
                            @endforeach
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section heading="Observability" description="Error budget theo runtime settings và metric hiện tại sau khi seed.">
                    @php($observability = $this->getObservability())

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <div>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $observability['status'] ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Window {{ $observability['window_hours'] ?? 0 }}h · Snapshot {{ $observability['snapshot_date'] ?? '-' }}
                                </p>
                            </div>
                            <span class="{{ $toneBadgeClasses[$observability['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ count($observability['breaches'] ?? []) }} breach
                            </span>
                        </div>

                        <div class="space-y-3">
                            @foreach(($observability['metrics'] ?? []) as $metric)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $metric['label'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $metric['key'] }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Budget {{ $metric['budget'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if(! empty($observability['missing_runbook_categories']))
                            <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-4 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-100">
                                <div class="font-semibold">Thiếu runbook map</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($observability['missing_runbook_categories'] as $category)
                                        <span class="rounded-full border border-warning-300 px-2.5 py-1 text-xs font-semibold dark:border-warning-800">{{ $category }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section heading="Governance & audit scope" description="Kiểm tra role matrix baseline và những gì manager/admin thấy được trong user directory và audit trail.">
                    @php($governance = $this->getGovernance())

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                            <div>
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $governance['status'] ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $governance['policy_note'] ?? '' }}</p>
                            </div>
                            <span class="{{ $toneBadgeClasses[$governance['tone'] ?? 'info'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ count($governance['scenario_users'] ?? []) }} scenario user
                            </span>
                        </div>

                        <dl class="grid gap-3 md:grid-cols-2">
                            @foreach(($governance['meta'] ?? []) as $meta)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="grid gap-3 md:grid-cols-3">
                            @foreach(($governance['signals'] ?? []) as $signal)
                                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $signal['label'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$signal['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $signal['value'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-3">
                            @forelse(($governance['scenario_users'] ?? []) as $user)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $user['email'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user['role'] }} · {{ $user['branch'] }}</p>
                                        </div>
                                        @if(filled($user['assignments']))
                                            <span class="{{ $toneBadgeClasses['warning'] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                                {{ $user['assignments'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Không có governance scenario user nào hiển thị trong scope hiện tại.
                                </div>
                            @endforelse
                        </div>

                        <div class="space-y-3">
                            @forelse(($governance['recent_audits'] ?? []) as $audit)
                                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ strtoupper($audit['entity']) }} · {{ $audit['action'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $audit['actor'] }} · {{ $audit['occurred_at'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Không có audit entry nào được mở từ cockpit này.
                                </div>
                            @endforelse
                        </div>

                        @if(! empty($governance['links']))
                            <div class="flex flex-wrap gap-3">
                                @foreach(($governance['links'] ?? []) as $link)
                                    <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                                        {{ $link['label'] }}
                                    </x-filament::link>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-filament::section>

                <x-filament::section heading="Smoke pack" description="Bộ lệnh local/test chuẩn để QA reset seed rồi replay control-plane, integration maintenance và ZNS triage.">
                    <div class="space-y-3">
                        @foreach($this->getSmokeCommands() as $command)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-xs text-gray-700 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-200">
                                {{ $command }}
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                <x-filament::section heading="Recent operator runs" description="Nhật ký command gần nhất cho OPS, integration maintenance và ZNS để operator xem nhanh status mà không cần mở audit resource.">
                    @if($this->getRecentRuns() === [])
                        <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Chưa có lần chạy command OPS nào trong audit log. Hãy chạy smoke pack ở bên trên để page bắt đầu hiển thị history.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                <thead>
                                    <tr class="text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2">Command</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Actor</th>
                                        <th class="px-3 py-2">Occurred</th>
                                        <th class="px-3 py-2">Summary</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-900">
                                    @foreach($this->getRecentRuns() as $run)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $run['command'] }}</td>
                                            <td class="px-3 py-2">
                                                <span class="{{ str_contains(strtolower($run['status']), 'fail') || str_contains(strtolower($run['action']), 'fail') ? ($toneBadgeClasses['danger'] ?? $defaultBadgeClasses) : ($toneBadgeClasses['success'] ?? $defaultBadgeClasses) }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                                    {{ $run['status'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $run['actor'] }}</td>
                                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $run['occurred_at'] }}</td>
                                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $run['summary'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
