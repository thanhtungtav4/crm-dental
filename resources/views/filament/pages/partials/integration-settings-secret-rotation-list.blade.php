<div class="grid gap-3 md:grid-cols-2">
    @foreach($panel['items'] as $rotation)
        @include('filament.pages.partials.secret-rotation-card', ['rotation' => $rotation])
    @endforeach
</div>
