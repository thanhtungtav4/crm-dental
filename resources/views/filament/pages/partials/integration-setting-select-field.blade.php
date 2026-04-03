<div>
    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
        {{ $field['label'] }}
    </label>
    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.blur="{{ $statePath }}">
            @foreach(($field['options'] ?? []) as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
    @error($statePath)
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
</div>
