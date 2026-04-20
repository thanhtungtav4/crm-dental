@props([
    'heading',
    'panel',
])

<div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
    <div class="space-y-1">
        <h1 class="fi-header-heading">{{ $heading }}</h1>
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Google Calendar:
            <span class="{{ $panel['badge_classes'] }}">
                {{ $panel['badge_label'] }}
                @if($panel['enabled'])
                    &middot; {{ $panel['label'] }}
                @endif
            </span>
        </div>
    </div>
</div>
