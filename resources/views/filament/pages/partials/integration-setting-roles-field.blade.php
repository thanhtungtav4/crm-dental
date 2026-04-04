@props([
    'field' => [],
    'statePath' => '',
    'options' => [],
    'emptyStateText' => '',
    'helperText' => null,
])

<div class="md:col-span-2">
    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
        {{ $field['label'] }}
    </label>

    @if($options !== [])
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($options as $roleValue => $roleLabel)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <input
                        type="checkbox"
                        value="{{ $roleValue }}"
                        wire:model.live="{{ $statePath }}"
                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span>{{ $roleLabel }}</span>
                </label>
            @endforeach
        </div>
    @else
        <p class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            {{ $emptyStateText }}
        </p>
    @endif

    @if(filled($helperText))
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $helperText }}</p>
    @endif
    @error($statePath)
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
</div>
