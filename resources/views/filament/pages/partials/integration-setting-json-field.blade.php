@props([
    'field' => [],
    'statePath' => '',
    'editableEntries' => [],
    'editableRowsCount' => 0,
    'showRowEnabledToggle' => true,
    'helperText' => '',
])

<div class="md:col-span-2">
    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
        {{ $field['label'] }}
    </label>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 dark:border-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span class="font-semibold text-gray-700 dark:text-gray-200">Tổng mục:</span>
                {{ $editableRowsCount }}
            </div>
            <div class="flex gap-2">
                <x-filament::button type="button" color="gray" size="sm" wire:click="addCatalogRow('{{ $field['state'] }}')">
                    Thêm dòng
                </x-filament::button>
                <x-filament::button type="button" color="gray" size="sm" wire:click="restoreCatalogDefaults('{{ $field['state'] }}')">
                    Khôi phục mặc định
                </x-filament::button>
            </div>
        </div>

        @if($showRowEnabledToggle)
            <div class="hidden grid-cols-12 border-b border-gray-100 px-3 py-2 text-xs font-semibold text-gray-500 dark:border-gray-800 dark:text-gray-400 md:grid">
                <div class="md:col-span-2">Bật</div>
                <div class="md:col-span-5">Nhãn hiển thị</div>
                <div class="md:col-span-4">Mã tự sinh</div>
                <div class="md:col-span-1 text-right">Xóa</div>
            </div>
        @else
            <div class="hidden grid-cols-12 border-b border-gray-100 px-3 py-2 text-xs font-semibold text-gray-500 dark:border-gray-800 dark:text-gray-400 md:grid">
                <div class="md:col-span-6">Nhãn hiển thị</div>
                <div class="md:col-span-5">Mã tự sinh</div>
                <div class="md:col-span-1 text-right">Xóa</div>
            </div>
        @endif

        <div class="max-h-[26rem] space-y-2 overflow-y-auto p-3">
            @foreach($editableEntries as $entry)
                <div @class([
                    'crm-catalog-row dark:border-gray-800 dark:bg-gray-900',
                    'crm-catalog-row--disabled' => $showRowEnabledToggle && ! $entry['row_enabled'],
                    'crm-catalog-row--without-toggle' => ! $showRowEnabledToggle,
                ])>
                    @if($showRowEnabledToggle)
                        <div class="crm-catalog-row__field crm-catalog-row__field--toggle">
                            <span class="crm-catalog-row__caption">Bật</span>
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    wire:model.live="catalogEditors.{{ $field['state'] }}.{{ $entry['index'] }}.enabled"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span>{{ $entry['row_enabled'] ? 'Hiển thị' : 'Ẩn' }}</span>
                            </label>
                        </div>
                    @endif
                    <div class="crm-catalog-row__field">
                        <span class="crm-catalog-row__caption">Nhãn hiển thị</span>
                        <x-filament::input.wrapper>
                            <input
                                type="text"
                                wire:model.live="catalogEditors.{{ $field['state'] }}.{{ $entry['index'] }}.label"
                                wire:blur="syncCatalogRowFromLabel('{{ $field['state'] }}', {{ $entry['index'] }})"
                                class="fi-input"
                                placeholder="Nhãn hiển thị"
                            />
                        </x-filament::input.wrapper>
                        @error("catalogEditors.{$field['state']}.{$entry['index']}.label")
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="crm-catalog-row__field">
                        <span class="crm-catalog-row__caption">Mã tự sinh</span>
                        <x-filament::input.wrapper>
                            <input
                                type="text"
                                wire:model.live="catalogEditors.{{ $field['state'] }}.{{ $entry['index'] }}.key"
                                class="fi-input bg-gray-50 dark:bg-gray-800"
                                readonly
                            />
                        </x-filament::input.wrapper>
                        @error("catalogEditors.{$field['state']}.{$entry['index']}.key")
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="crm-catalog-row__actions">
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-danger-300 hover:text-danger-600 dark:border-gray-700 dark:text-gray-300"
                            wire:click="removeCatalogRow('{{ $field['state'] }}', {{ $entry['index'] }})"
                            @disabled($editableRowsCount <= 1)
                            title="Xóa dòng"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $helperText }}</p>
    @error($statePath)
        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
    @enderror
</div>
