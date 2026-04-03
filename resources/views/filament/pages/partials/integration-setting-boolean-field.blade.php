<label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
    <input
        type="checkbox"
        wire:model.live="{{ $statePath }}"
        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
    />
    <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $field['label'] }}</span>
</label>
