<div class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-100">
    <div class="font-semibold">{{ $rotation['display_name'] }}</div>
    <div>Grace tới: {{ $rotation['grace_expires_at_label'] }}</div>
    <div>{{ $rotation['remaining_minutes_label'] }}</div>

    @if(filled($rotation['rotation_reason'] ?? null))
        <div class="text-xs text-warning-800/80 dark:text-warning-200/80">Lý do: {{ $rotation['rotation_reason'] }}</div>
    @endif
</div>
