@props([
    'section',
])

<div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $section['heading'] }}</h3>

    <div class="mt-2 space-y-1.5 text-sm">
        @forelse($section['rows'] as $row)
            <div class="flex items-center justify-between">
                <span class="text-gray-600 dark:text-gray-300">{{ $row['label'] }}</span>
                <span class="font-semibold">{{ $row['total_label'] }}</span>
            </div>
        @empty
            <p class="text-xs text-gray-500">{{ $section['empty_text'] }}</p>
        @endforelse
    </div>
</div>
