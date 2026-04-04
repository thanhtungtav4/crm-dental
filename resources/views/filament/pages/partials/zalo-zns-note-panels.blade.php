<div class="grid gap-3 lg:grid-cols-[1.4fr,1fr]">
    @foreach($panels as $panel)
        @include('filament.pages.partials.control-plane-note-panel', ['panel' => $panel])
    @endforeach
</div>
