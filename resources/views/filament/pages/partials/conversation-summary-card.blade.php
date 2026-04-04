@props([
    'card',
])

<div class="rounded-2xl border border-gray-200/80 bg-white/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">{{ $card['label'] }}</p>
    <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $card['value'] }}</p>
</div>
