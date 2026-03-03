<div wire:poll.{{ $pollingSeconds }}s="refreshPending">
    @if($activeAnnouncement !== null)
        @php
            $priority = (string) ($activeAnnouncement['priority'] ?? 'info');
            $priorityLabel = match ($priority) {
                'success' => 'Thành công',
                'warning' => 'Cảnh báo',
                'danger' => 'Khẩn cấp',
                default => 'Thông tin',
            };
            $priorityClasses = match ($priority) {
                'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
                'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
                default => 'border-blue-200 bg-blue-50 text-blue-700',
            };
            $requiresAck = (bool) ($activeAnnouncement['require_ack'] ?? false);
        @endphp

        <div class="pointer-events-none fixed inset-0 z-[120] flex items-center justify-center bg-black/35 p-4 backdrop-blur-[1px]">
            <div class="pointer-events-auto flex w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <div class="space-y-1">
                        <div class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $priorityClasses }}">
                            {{ $priorityLabel }}
                        </div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                            {{ $activeAnnouncement['title'] ?? 'Thông báo hệ thống' }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Mã: {{ $activeAnnouncement['code'] ?? '-' }}
                            @if(!empty($activeAnnouncement['starts_at']))
                                · Bắt đầu: {{ $activeAnnouncement['starts_at'] }}
                            @endif
                            @if(!empty($activeAnnouncement['ends_at']))
                                · Kết thúc: {{ $activeAnnouncement['ends_at'] }}
                            @endif
                        </p>
                    </div>

                    @unless($requiresAck)
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600"
                            wire:click="dismiss"
                            title="Đóng popup"
                        >
                            ✕
                        </button>
                    @endunless
                </div>

                <div class="max-h-[55vh] overflow-y-auto px-5 py-4">
                    <div class="fi-prose max-w-none text-sm text-gray-700 dark:text-gray-200">
                        {{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make((string) ($activeAnnouncement['message'] ?? ''))
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsVisibility('public') }}
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                    @if($requiresAck)
                        <x-filament::button type="button" color="primary" wire:click="acknowledge">
                            Tôi đã đọc
                        </x-filament::button>
                    @else
                        <x-filament::button type="button" color="gray" wire:click="dismiss">
                            Đóng
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
