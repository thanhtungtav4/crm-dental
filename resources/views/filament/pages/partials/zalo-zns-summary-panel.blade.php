@props([
    'panel',
])

<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
    @foreach($panel['items'] as $card)
        @include('filament.pages.partials.dashboard-summary-card', ['card' => $card])
    @endforeach
</div>
