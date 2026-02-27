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
                        @else
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ $field['label'] }}
                                </label>
                                @if(($field['state'] ?? null) === 'web_lead_api_token')
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
                                            @if(($field['type'] ?? null) === 'integer') min="0" step="1" @endif
                                        />
                                    </x-filament::input.wrapper>
                                @endif
                                @error($statePath)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    @endforeach
                </div>

                @if(($provider['group'] ?? null) === 'emr')
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button type="button" color="gray" icon="heroicon-o-signal" wire:click="testEmrConnection">
                            Test EMR
                        </x-filament::button>
                        <x-filament::button type="button" color="info" icon="heroicon-o-arrow-top-right-on-square" wire:click="openEmrConfigUrl">
                            Mở config EMR
                        </x-filament::button>
                    </div>
                @endif

                @if(($provider['group'] ?? null) === 'web_lead')
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button type="button" color="gray" icon="heroicon-o-key" wire:click="generateWebLeadApiToken">
                            Tạo API Token
                        </x-filament::button>
                    </div>

                    <div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                        <div class="mb-2 text-sm font-semibold">Hướng dẫn tích hợp Web Lead API</div>
                        <ul class="list-disc space-y-1 pl-5 text-xs md:text-sm">
                            <li>Endpoint: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">{{ route('api.v1.web-leads.store') }}</code></li>
                            <li>Method: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">POST</code></li>
                            <li>Headers bắt buộc: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">Authorization: Bearer &lt;TOKEN&gt;</code>, <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">X-Idempotency-Key: &lt;UNIQUE_KEY&gt;</code></li>
                            <li>Payload tối thiểu: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">full_name</code>, <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">phone</code>. Tùy chọn: <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">branch_code</code>, <code class="rounded bg-white px-1 py-0.5 text-[11px] dark:bg-gray-800">note</code>.</li>
                        </ul>

                        <div class="mt-3 overflow-x-auto rounded-md border border-gray-200 bg-white p-3 text-[11px] leading-5 text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200">
                            <pre class="min-w-[620px]"><code>curl -X POST '{{ route('api.v1.web-leads.store') }}' \
  -H 'Authorization: Bearer &lt;TOKEN&gt;' \
  -H 'X-Idempotency-Key: web-{{ now()->format('YmdHis') }}-001' \
  -H 'Content-Type: application/json' \
  -d '{
    "full_name": "Nguyen Van A",
    "phone": "0901234567",
    "branch_code": "BR-WEB-HCM",
    "note": "Form tu website landing page"
  }'</code></pre>
                        </div>
                    </div>
                @endif
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
