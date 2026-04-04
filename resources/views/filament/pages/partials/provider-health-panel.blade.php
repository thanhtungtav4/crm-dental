@props([
    'panel',
    'badgeClasses' => 'inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200',
])

@if($panel['show_container'] ?? true)
    <div class="{{ $panel['container_classes'] ?? 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/60' }}">
@endif
    @if($panel['show_header'] ?? true)
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $panel['heading'] }}</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
            </div>
            @if(filled($panel['drift_badge_label'] ?? $panel['drift_label'] ?? null))
                <span class="{{ $panel['drift_badge_classes'] ?? $badgeClasses }}">
                    {{ $panel['drift_badge_label'] ?? $panel['drift_label'] }}
                </span>
            @endif
        </div>
    @endif

    <div @class([$panel['grid_classes'] ?? 'grid gap-3 md:grid-cols-2', 'mt-3' => $panel['show_header'] ?? true])>
        @foreach($panel['items'] as $provider)
            @include('filament.pages.partials.provider-health-card', ['provider' => $provider])
        @endforeach
    </div>
@if($panel['show_container'] ?? true)
    </div>
@endif
