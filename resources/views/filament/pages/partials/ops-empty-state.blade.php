@props([
    'message',
    'containerClasses' => 'rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400',
])

<div class="{{ $containerClasses }}">
    {{ $message }}
</div>
