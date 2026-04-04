<div class="space-y-4">
    <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-8">
        @foreach($overviewPanel['summary_cards'] as $card)
            @include('filament.pages.partials.customer-care-summary-card', ['card' => $card])
        @endforeach
    </div>

    <div class="grid gap-3 lg:grid-cols-3">
        @foreach($overviewPanel['breakdown_sections'] as $section)
            @include('filament.pages.partials.customer-care-breakdown-panel', ['section' => $section])
        @endforeach
    </div>
</div>
