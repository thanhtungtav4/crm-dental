<div class="ops-grid-2">
    @include('filament.pages.partials.ops-artifact-list', [
        'artifacts' => $panel['runtime_artifacts'],
        'listClasses' => 'contents',
        'artifactContainerClasses' => 'rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60',
    ])
</div>

<div class="ops-grid-2 mt-4">
    @foreach($panel['fixture_columns'] as $column)
        @include('filament.pages.partials.ops-artifact-list', [
            'artifacts' => $column['items'],
            'errorTextClasses' => $column['error_text_classes'],
        ])
    @endforeach
</div>
