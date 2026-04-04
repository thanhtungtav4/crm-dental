@props([
    'panel',
])

<div class="space-y-4">
    @include('filament.pages.partials.section-summary-banner', [
        'summary' => $panel['summary'],
    ])

    @include('filament.pages.partials.ops-meta-grid', [
        'items' => $panel['meta'],
        'gridClasses' => 'ops-grid-2',
    ])

    <div class="ops-grid-3">
        @foreach($panel['signals'] as $signal)
            @include('filament.pages.partials.signal-badge-card', [
                'card' => $signal,
            ])
        @endforeach
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $panel['scenario_user_panel']['heading'] }}</p>
            <span class="{{ $panel['scenario_user_panel']['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ $panel['scenario_user_panel']['badge_label'] }}
            </span>
        </div>

        @forelse($panel['scenario_user_panel']['cards'] as $card)
            @include('filament.pages.partials.ops-detail-card', ['card' => $card])
        @empty
            @include('filament.pages.partials.ops-empty-state', [
                'message' => $panel['scenario_user_panel']['empty_state_message'],
            ])
        @endforelse
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $panel['recent_audit_panel']['heading'] }}</p>
            <span class="{{ $panel['recent_audit_panel']['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ $panel['recent_audit_panel']['badge_label'] }}
            </span>
        </div>

        @forelse($panel['recent_audit_panel']['cards'] as $card)
            @include('filament.pages.partials.ops-detail-card', ['card' => $card])
        @empty
            @include('filament.pages.partials.ops-empty-state', [
                'message' => $panel['recent_audit_panel']['empty_state_message'],
            ])
        @endforelse
    </div>

    @if(! empty($panel['links']))
        <div class="flex flex-wrap gap-3">
            @foreach($panel['links'] as $link)
                <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                    {{ $link['label'] }}
                </x-filament::link>
            @endforeach
        </div>
    @endif
</div>
