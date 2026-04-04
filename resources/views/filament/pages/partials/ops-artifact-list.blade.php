@props([
    'artifacts',
    'listClasses' => 'space-y-4',
    'artifactContainerClasses' => null,
    'artifactPathContainerClasses' => null,
    'errorTextClasses' => null,
])

<div class="{{ $listClasses }}">
    @foreach($artifacts as $artifact)
        @include('filament.pages.partials.ops-artifact-card', [
            'artifact' => $artifact,
            'containerClasses' => $artifactContainerClasses,
            'pathContainerClasses' => $artifactPathContainerClasses,
            'errorTextClasses' => $errorTextClasses,
        ])
    @endforeach
</div>
