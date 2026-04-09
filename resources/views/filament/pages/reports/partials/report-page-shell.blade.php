@props([
    'viewState',
])

<div class="space-y-6">
    @if(! empty($viewState['stats_panel']['cards']))
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach($viewState['stats_panel']['cards'] as $card)
                @include('filament.pages.reports.partials.report-stat-card', [
                    'card' => $card,
                ])
            @endforeach
        </div>
    @endif

    <div>
        {{ $this->table }}
    </div>
</div>
