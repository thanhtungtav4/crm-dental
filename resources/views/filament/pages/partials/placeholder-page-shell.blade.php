@props([
    'viewState',
])

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <x-filament::badge color="warning">{{ $viewState['badge_label'] }}</x-filament::badge>
        <span class="text-sm text-gray-500">{{ $viewState['status_text'] }}</span>
    </div>

    @if(filled($viewState['subheading']))
        <p class="text-sm text-gray-600">{{ $viewState['subheading'] }}</p>
    @endif

    @if(! empty($viewState['bullets']))
        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
            @foreach($viewState['bullets'] as $bullet)
                <li>{{ $bullet }}</li>
            @endforeach
        </ul>
    @endif
</div>
