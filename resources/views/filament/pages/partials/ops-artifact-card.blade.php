@php
    $containerClasses = $containerClasses ?? 'rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60';
    $pathContainerClasses = $pathContainerClasses ?? 'ops-break-words mt-3 rounded-xl border border-dashed border-gray-300 px-3 py-2 text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400';
    $errorTextClasses = $errorTextClasses ?? 'mt-1 font-medium text-danger-700 dark:text-danger-300';
@endphp

<div class="{{ $containerClasses }}">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $artifact['label'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $artifact['description'] }}</p>
        </div>
        <span class="{{ $artifact['status_badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $artifact['status'] }}
        </span>
    </div>

    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
        @foreach(($artifact['meta'] ?? []) as $meta)
            <div class="flex items-center justify-between gap-3">
                <span>{{ $meta['label'] }}</span>
                <span class="ops-break-words font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</span>
            </div>
        @endforeach
    </div>

    @if(filled($artifact['path'] ?? null))
        <div class="{{ $pathContainerClasses }}">
            {{ $artifact['path'] }}
            @if(filled($artifact['error'] ?? null))
                <div class="{{ $errorTextClasses }}">{{ $artifact['error'] }}</div>
            @endif
        </div>
    @endif
</div>
