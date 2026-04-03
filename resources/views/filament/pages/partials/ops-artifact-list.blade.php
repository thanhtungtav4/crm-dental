@php
    $listClasses = $listClasses ?? 'space-y-4';
    $artifactContainerClasses = $artifactContainerClasses ?? null;
    $artifactPathContainerClasses = $artifactPathContainerClasses ?? null;
    $errorTextClasses = $errorTextClasses ?? null;
@endphp

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
