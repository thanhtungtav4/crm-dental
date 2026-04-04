@props([
    'items',
    'gridClasses' => 'ops-grid-2',
    'containerClasses' => 'rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60',
    'valueClasses' => 'mt-1 text-sm font-medium text-gray-950 dark:text-white',
])

<dl class="{{ $gridClasses }}">
    @foreach($items as $meta)
        <div class="{{ $containerClasses }}">
            <dt class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
            <dd class="{{ $valueClasses }}">{{ $meta['value'] }}</dd>
        </div>
    @endforeach
</dl>
