@props([
    'component',
    'parameters' => [],
    'wireKey',
    'wrapperClass' => null,
    'innerWrapperClass' => null,
])

<div @class([$wrapperClass]) wire:key="{{ $wireKey }}">
    @if($innerWrapperClass)
        <div class="{{ $innerWrapperClass }}">
            @livewire($component, $parameters, key($wireKey))
        </div>
    @else
        @livewire($component, $parameters, key($wireKey))
    @endif
</div>
