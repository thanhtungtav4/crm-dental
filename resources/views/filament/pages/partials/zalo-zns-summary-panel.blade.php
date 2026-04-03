<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
    @foreach($panel['items'] as $card)
        @include('filament.pages.partials.dashboard-summary-card', [
            'card' => $card,
            'containerClasses' => ($card['card_classes'] ?? '').' rounded-xl border p-3',
            'labelClasses' => ($card['label_classes'] ?? ''),
            'valueClasses' => ($card['value_classes'] ?? ''),
        ])
    @endforeach
</div>
