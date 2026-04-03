@php
    $showHeader = $showHeader ?? true;
    $showContainer = $showContainer ?? true;
    $containerClasses = $containerClasses ?? 'rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/60';
    $gridClasses = $gridClasses ?? 'mt-3 grid gap-3 md:grid-cols-2';
    $cardContainerClasses = $cardContainerClasses ?? 'rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/60';
    $cardMetaValueClasses = $cardMetaValueClasses ?? 'break-all font-medium text-gray-950 dark:text-white';
    $cardStatusMessageContainerClasses = $cardStatusMessageContainerClasses ?? 'mt-3 rounded-lg border px-3 py-2 text-xs';
    $panelBadgeLabel = $panel['drift_badge_label'] ?? $panel['drift_label'] ?? null;
    $panelBadgeClasses = $panel['drift_badge_classes'] ?? ($badgeClasses ?? 'inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200');
@endphp

@if($showContainer)
    <div class="{{ $containerClasses }}">
@endif
    @if($showHeader)
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $panel['heading'] }}</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
            </div>
            @if(filled($panelBadgeLabel))
                <span class="{{ $panelBadgeClasses }}">
                    {{ $panelBadgeLabel }}
                </span>
            @endif
        </div>
    @endif

    <div @class([$gridClasses, 'mt-3' => $showHeader])>
        @foreach(($panel['items'] ?? []) as $provider)
            @include('filament.pages.partials.provider-health-card', [
                'provider' => $provider,
                'containerClasses' => $cardContainerClasses,
                'metaValueClasses' => $cardMetaValueClasses,
                'statusMessageContainerClasses' => $cardStatusMessageContainerClasses,
            ])
        @endforeach
    </div>
@if($showContainer)
    </div>
@endif
