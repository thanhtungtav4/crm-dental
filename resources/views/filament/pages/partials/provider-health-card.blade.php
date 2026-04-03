@php
    $containerClasses = $containerClasses ?? 'rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/60';
    $metaValueClasses = $metaValueClasses ?? 'break-all font-medium text-gray-950 dark:text-white';
    $statusMessageContainerClasses = $statusMessageContainerClasses ?? 'mt-3 rounded-lg border px-3 py-2 text-xs';
@endphp

<div class="{{ $containerClasses }}">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $provider['label'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $provider['description'] }}</p>
        </div>
        <span class="{{ $provider['status_badge']['classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $provider['status_badge']['label'] }}
        </span>
    </div>

    <div class="mt-3 flex flex-wrap gap-2 text-xs">
        <span class="{{ $provider['summary_badge']['classes'] }} inline-flex rounded-full border px-2.5 py-1 font-semibold">
            {{ $provider['summary_badge']['label'] }}
        </span>

        @if(filled($provider['issue_badge'] ?? null))
            <span class="{{ $provider['issue_badge']['classes'] }} inline-flex rounded-full border px-2.5 py-1 font-semibold">
                {{ $provider['issue_badge']['label'] }}
            </span>
        @endif
    </div>

    @if(! empty($provider['meta_preview']))
        <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
            @foreach($provider['meta_preview'] as $meta)
                <div class="flex items-center justify-between gap-3">
                    <span>{{ $meta['label'] }}</span>
                    <span class="{{ $metaValueClasses }}">{{ $meta['value'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if(filled($provider['status_message'] ?? null))
        <div class="{{ $provider['status_message_classes'] }} {{ $statusMessageContainerClasses }}">
            {{ $provider['status_message'] }}
        </div>
    @endif
</div>
