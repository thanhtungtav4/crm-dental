@props([
    'sections',
])

@foreach($sections as $section)
    @include('filament.pages.partials.control-plane-section', ['section' => $section])
@endforeach
