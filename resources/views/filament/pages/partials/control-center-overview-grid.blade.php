@props([
    'panel',
])

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach($panel['cards'] as $card)
        @include('filament.pages.partials.control-center-overview-card', [
            'card' => $card,
        ])
    @endforeach
</div>
