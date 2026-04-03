<div class="space-y-4">
    <div class="grid gap-4 md:grid-cols-2">
        @foreach($provider['rendered_fields'] as $renderedField)
            @include($renderedField['partial'], [
                'field' => $renderedField['field'],
                'statePath' => $renderedField['state_path'],
            ])
        @endforeach
    </div>

    @include('filament.pages.partials.control-plane-partial-list', [
        'items' => $provider['support_sections'],
    ])
</div>
