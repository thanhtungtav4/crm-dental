@php
    $toneBadgeClasses = [
        'success' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
        'warning' => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
        'danger' => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
        'info' => 'border-info-200 bg-info-50 text-info-700 dark:border-info-900/60 dark:bg-info-950/30 dark:text-info-200',
        'gray' => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200',
    ];
    $defaultBadgeClasses = 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200';
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
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

        <x-filament::section heading="Lối tắt delivery" description="Đi thẳng tới các màn hình điều trị, EMR, kho và labo đang được dùng trong ca vận hành.">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                @foreach($this->getQuickLinks() as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="rounded-2xl border border-gray-200 bg-white px-4 py-4 transition hover:border-primary-300 hover:bg-primary-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-gray-800 dark:bg-gray-950/60 dark:hover:border-primary-700 dark:hover:bg-primary-950/20 dark:focus-visible:ring-offset-gray-950"
                    >
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $link['label'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $link['description'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-filament::section>

        <div class="space-y-6">
            @foreach($this->getSections() as $section)
                <x-filament::section :heading="$section['title']" :description="$section['description']">
                    <div class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @foreach($section['metrics'] as $metric)
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                                            <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
                                        </div>
                                        <span class="{{ $toneBadgeClasses[$metric['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                            {{ $metric['label'] }}
                                        </span>
                                    </div>
                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $metric['description'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if(empty($section['rows']))
                            <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Chưa có dữ liệu trong scope hiện tại.
                            </div>
                        @else
                            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                                @foreach($section['rows'] as $row)
                                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['title'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['subtitle'] }}</p>
                                            </div>

                                            <span class="{{ $toneBadgeClasses[$row['tone']] ?? $defaultBadgeClasses }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                                {{ $row['badge'] }}
                                            </span>
                                        </div>

                                        <dl class="mt-4 space-y-2">
                                            @foreach($row['meta'] as $meta)
                                                <div class="flex items-center justify-between gap-3 text-sm">
                                                    <dt class="text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                                                    <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
