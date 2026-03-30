@if($url)
    <a href="{{ $url }}" class="{{ $actionClass }}">
        {{ $label }}
    </a>
@else
    <span class="{{ $actionClass }}">{{ $label }}</span>
@endif
