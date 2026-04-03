@props([
    'items',
])

@foreach($items as $item)
    @include($item['partial'], $item['include_data'] ?? [])
@endforeach
