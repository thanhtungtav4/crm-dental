<x-filament-panels::page>
    <style>
        .crm-catalog-switch-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: minmax(0, 1fr);
        }

        @media (min-width: 768px) {
            .crm-catalog-switch-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .crm-catalog-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 0.625rem 0.75rem;
            color: #111827;
            transition: border-color .15s ease, background-color .15s ease;
            text-align: left;
        }

        .crm-catalog-switch.is-on {
            border-color: color-mix(in srgb, var(--crm-primary, #4f46e5) 45%, #d1d5db);
            background: color-mix(in srgb, var(--crm-primary, #4f46e5) 10%, #fff);
        }

        .crm-catalog-switch__text {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        .crm-catalog-switch__title {
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.2;
            color: #111827;
        }

        .crm-catalog-switch__hint {
            font-size: 0.75rem;
            color: #6b7280;
            line-height: 1.25;
        }

        .crm-catalog-toggle__switch {
            position: relative;
            display: inline-flex;
            height: 1.5rem;
            width: 2.75rem;
            align-items: center;
            border-radius: 9999px;
            background: #d1d5db;
            padding: 0.125rem;
            transition: background-color .15s ease;
        }

        .crm-catalog-switch.is-on .crm-catalog-toggle__switch {
            background: var(--crm-primary, #4f46e5);
        }

        .crm-catalog-toggle__thumb {
            height: 1.25rem;
            width: 1.25rem;
            border-radius: 9999px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .15);
            transform: translateX(0);
            transition: transform .15s ease;
        }

        .crm-catalog-switch.is-on .crm-catalog-toggle__thumb {
            transform: translateX(1.25rem);
        }

        .crm-catalog-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background: #fff;
            padding: 0.5rem;
        }

        .crm-catalog-row--disabled {
            opacity: 0.6;
        }

        .crm-catalog-row__field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .crm-catalog-row__field--toggle {
            justify-content: center;
        }

        .crm-catalog-row__caption {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .crm-catalog-row__actions {
            display: flex;
            justify-content: flex-start;
            align-items: center;
        }

        @media (min-width: 1024px) {
            .crm-catalog-row {
                grid-template-columns: minmax(0, 1.1fr) minmax(0, 3fr) minmax(0, 2.2fr) auto;
                align-items: center;
            }

            .crm-catalog-row.crm-catalog-row--without-toggle {
                grid-template-columns: minmax(0, 3fr) minmax(0, 2.2fr) auto;
            }

            .crm-catalog-row__actions {
                justify-content: flex-end;
            }

            .crm-catalog-row__caption {
                display: none;
            }
        }
    </style>

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
                                    'email' => 'email',
                                    'color' => 'color',
                                    'integer' => 'number',
                                    default => 'text',
                                };
                        @endphp
                        @continue((bool) ($field['hidden'] ?? false))

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
                        @elseif(($field['type'] ?? null) === 'json')
                            @php
                                $rows = data_get($this->catalogEditors, $field['state'], []);
                                $isExamIndicationCatalog = ($field['state'] ?? null) === 'catalog_exam_indications_json';
                                $rowEntries = collect($rows)
                                    ->map(fn ($row, $index) => ['index' => $index, 'row' => $row]);
                                $editableEntries = $rowEntries->values();
                                $editableRowsCount = $editableEntries->count();
                                $showRowEnabledToggle = ! $isExamIndicationCatalog;
                            @endphp

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
                                            @php
                                                $index = (int) ($entry['index'] ?? 0);
                                                $row = (array) ($entry['row'] ?? []);
                                                $rowEnabled = filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
                                            @endphp
                                            <div @class([
                                                'crm-catalog-row dark:border-gray-800 dark:bg-gray-900',
                                                'crm-catalog-row--disabled' => $showRowEnabledToggle && ! $rowEnabled,
                                                'crm-catalog-row--without-toggle' => ! $showRowEnabledToggle,
                                            ])>
                                                @if($showRowEnabledToggle)
                                                    <div class="crm-catalog-row__field crm-catalog-row__field--toggle">
                                                        <span class="crm-catalog-row__caption">Bật</span>
                                                        <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-200">
                                                            <input
                                                                type="checkbox"
                                                                wire:model.live="catalogEditors.{{ $field['state'] }}.{{ $index }}.enabled"
                                                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                            />
                                                            <span>{{ $rowEnabled ? 'Hiển thị' : 'Ẩn' }}</span>
                                                        </label>
                                                    </div>
                                                @endif
                                                <div class="crm-catalog-row__field">
                                                    <span class="crm-catalog-row__caption">Nhãn hiển thị</span>
                                                    <x-filament::input.wrapper>
                                                        <input
                                                            type="text"
                                                            wire:model.live="catalogEditors.{{ $field['state'] }}.{{ $index }}.label"
                                                            wire:blur="syncCatalogRowFromLabel('{{ $field['state'] }}', {{ $index }})"
                                                            class="fi-input"
                                                            placeholder="Nhãn hiển thị"
                                                        />
                                                    </x-filament::input.wrapper>
                                                    @error("catalogEditors.{$field['state']}.{$index}.label")
                                                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                                <div class="crm-catalog-row__field">
                                                    <span class="crm-catalog-row__caption">Mã tự sinh</span>
                                                    <x-filament::input.wrapper>
                                                        <input
                                                            type="text"
                                                            wire:model.live="catalogEditors.{{ $field['state'] }}.{{ $index }}.key"
                                                            class="fi-input bg-gray-50 dark:bg-gray-800"
                                                            readonly
                                                        />
                                                    </x-filament::input.wrapper>
                                                    @error("catalogEditors.{$field['state']}.{$index}.key")
                                                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                                <div class="crm-catalog-row__actions">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-danger-300 hover:text-danger-600 dark:border-gray-700 dark:text-gray-300"
                                                        wire:click="removeCatalogRow('{{ $field['state'] }}', {{ $index }})"
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

                                @if($showRowEnabledToggle)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nhập nhãn hiển thị, hệ thống tự sinh mã. Dùng toggle từng dòng để bật/tắt hiển thị option. Không cần sửa JSON thủ công.</p>
                                @else
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nhập nhãn hiển thị, hệ thống tự sinh mã. Không cần sửa JSON thủ công.</p>
                                @endif
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
