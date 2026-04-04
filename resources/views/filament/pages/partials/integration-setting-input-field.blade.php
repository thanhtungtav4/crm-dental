@props([
    'field' => [],
    'statePath' => '',
    'inputType' => 'text',
    'isWebLeadApiToken' => false,
    'min' => null,
    'step' => null,
])

<div>
    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
        {{ $field['label'] }}
    </label>
    @if($isWebLeadApiToken)
        <div x-data="{ showWebLeadToken: false }" class="flex flex-wrap items-start gap-2">
            <x-filament::input.wrapper class="min-w-0 flex-1">
                <input
                    x-ref="webLeadApiTokenInput"
                    x-bind:type="showWebLeadToken ? 'text' : 'password'"
                    wire:model.blur="{{ $statePath }}"
                    class="fi-input"
                    autocomplete="off"
                />
            </x-filament::input.wrapper>

            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                x-on:click="showWebLeadToken = !showWebLeadToken"
            >
                <span x-text="showWebLeadToken ? 'Ẩn' : 'Hiện'"></span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                x-on:click="navigator.clipboard?.writeText($refs.webLeadApiTokenInput?.value ?? '')"
            >
                Copy
            </x-filament::button>
        </div>
    @else
        <x-filament::input.wrapper>
            <input
                type="{{ $inputType }}"
                wire:model.blur="{{ $statePath }}"
                class="fi-input"
                @if($min !== null) min="{{ $min }}" @endif
                @if($step !== null) step="{{ $step }}" @endif
            />
        </x-filament::input.wrapper>
    @endif
    @error($statePath)
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
</div>
