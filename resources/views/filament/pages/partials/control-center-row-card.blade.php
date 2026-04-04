@props([
    'row',
])

<div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
    <div class="flex items-start justify-between gap-3">
        <div class="space-y-1">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['title'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['subtitle'] }}</p>
        </div>

        <span class="{{ $row['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $row['badge'] }}
        </span>
    </div>

    <dl class="mt-4 space-y-2">
        @foreach($row['meta'] as $meta)
            <div class="flex items-center justify-between gap-3 text-sm">
                <dt class="text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
            </div>
        @endforeach
    </dl>
</div>
