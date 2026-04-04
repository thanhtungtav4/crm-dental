<div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $panel['heading'] }}</p>
        <span class="{{ $panel['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $panel['badge_label'] }}
        </span>
    </div>

    <div class="mt-3 space-y-3">
        @forelse(($panel['items'] ?? []) as $rotation)
            <div class="{{ $rotation['card_classes'] }}">
                <div class="font-medium{{ str_contains($rotation['card_classes'], 'danger') ? '' : ' text-gray-950 dark:text-white' }}">
                    {{ $rotation['display_name'] }}
                </div>
                <div class="mt-1 text-xs{{ str_contains($rotation['card_classes'], 'danger') ? '' : ' text-gray-500 dark:text-gray-400' }}">
                    {{ $rotation['detail_text'] }}
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {{ $panel['empty_state_text'] }}
            </div>
        @endforelse
    </div>
</div>
