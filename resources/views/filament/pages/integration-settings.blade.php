<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        @foreach($this->getProviders() as $provider)
            <x-filament::section :heading="$provider['title']" :description="$provider['description']">
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($provider['fields'] as $field)
                        @php
                            $statePath = 'settings.' . $field['state'];
                            $isBoolean = $field['type'] === 'boolean';
                            $inputType = ($field['is_secret'] ?? false)
                                ? 'password'
                                : match ($field['type'] ?? 'text') {
                                    'url' => 'url',
                                    'integer' => 'number',
                                    default => 'text',
                                };
                        @endphp

                        @if($isBoolean)
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                <input
                                    type="checkbox"
                                    wire:model.live="{{ $statePath }}"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $field['label'] }}</span>
                            </label>
                        @elseif(($field['type'] ?? null) === 'select')
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ $field['label'] }}
                                </label>
                                <select
                                    wire:model.blur="{{ $statePath }}"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                                >
                                    @foreach(($field['options'] ?? []) as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                                @error($statePath)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ $field['label'] }}
                                </label>
                                <input
                                    type="{{ $inputType }}"
                                    wire:model.blur="{{ $statePath }}"
                                    @if(($field['type'] ?? null) === 'integer') min="0" step="1" @endif
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                                @error($statePath)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check-circle">
                Lưu cài đặt tích hợp
            </x-filament::button>
        </div>
    </form>

    @if($this->canViewAuditLogs())
        <x-filament::section heading="Nhật ký thay đổi cài đặt" description="Theo dõi ai sửa, sửa gì và thời điểm cập nhật gần nhất." class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Thời gian</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Người sửa</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Thiết lập</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Giá trị cũ</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Giá trị mới</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse($this->getRecentLogs() as $log)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    {{ optional($log->changed_at)->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-800 dark:text-gray-100">
                                    {{ $log->changedBy?->name ?? 'Hệ thống' }}
                                </td>
                                <td class="px-3 py-2 text-gray-800 dark:text-gray-100">
                                    <div class="font-medium">{{ $log->setting_label ?: $log->setting_key }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->setting_key }}</div>
                                </td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $log->old_value }}</td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $log->new_value }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Chưa có lịch sử thay đổi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
