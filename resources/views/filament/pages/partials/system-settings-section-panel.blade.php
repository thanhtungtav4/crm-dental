@props([
    'section',
])

<x-filament::section :heading="$section['title']" :description="$section['description']">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach($section['items'] as $item)
            @include('filament.pages.partials.system-settings-link-card', [
                'item' => $item,
            ])
        @endforeach
    </div>
</x-filament::section>
