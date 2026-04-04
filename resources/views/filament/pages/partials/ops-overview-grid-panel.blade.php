@props([
    'panel',
])

<div class="ops-overview-grid">
    @foreach($panel['cards'] as $card)
        @include('filament.pages.partials.ops-overview-card', ['card' => $card])
    @endforeach
</div>
