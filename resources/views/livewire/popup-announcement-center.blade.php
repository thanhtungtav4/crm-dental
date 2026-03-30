<div wire:poll.visible.{{ $this->pollingInterval }}s="refreshPending" aria-live="polite">
    @if($activeAnnouncement !== null)
        <div class="pointer-events-none fixed inset-0 z-[1000] flex items-end justify-center bg-slate-950/45 p-3 backdrop-blur-sm sm:items-center sm:p-6">
            <div
                wire:key="{{ $activeAnnouncement['dialog_id'] }}"
                role="dialog"
                aria-modal="true"
                aria-labelledby="{{ $activeAnnouncement['title_id'] }}"
                aria-describedby="{{ $activeAnnouncement['meta_id'] }} {{ $activeAnnouncement['body_id'] }}"
                class="pointer-events-auto flex max-h-[85dvh] w-full max-w-3xl min-w-0 flex-col overflow-hidden rounded-t-[1.75rem] border border-slate-200/90 bg-white shadow-2xl ring-1 ring-black/5 sm:rounded-[1.75rem] dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10"
            >
                <div class="border-b border-slate-100 px-4 py-4 sm:px-6 dark:border-slate-800">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $activeAnnouncement['priority_classes'] }}">
                                    {{ $activeAnnouncement['priority_label'] }}
                                </div>
                                <div class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium {{ $activeAnnouncement['mode_classes'] }}">
                                    {{ $activeAnnouncement['mode_label'] }}
                                </div>
                            </div>

                            <div class="space-y-1">
                                <h3 id="{{ $activeAnnouncement['title_id'] }}" class="text-pretty text-lg font-semibold text-slate-900 dark:text-slate-100 sm:text-xl">
                                    {{ $activeAnnouncement['title'] }}
                                </h3>
                                <p id="{{ $activeAnnouncement['meta_id'] }}" class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ $activeAnnouncement['meta_text'] }}
                                </p>
                            </div>

                            <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $activeAnnouncement['intro_text'] }}</p>
                        </div>

                        @if($activeAnnouncement['close_action'] !== null)
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 shrink-0 touch-manipulation items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition-colors hover:border-slate-300 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-slate-100"
                                wire:click="{{ $activeAnnouncement['close_action']['method'] }}"
                                wire:loading.attr="disabled"
                                wire:target="{{ $activeAnnouncement['close_action']['target'] }}"
                                title="{{ $activeAnnouncement['close_action']['label'] }}"
                                aria-label="{{ $activeAnnouncement['close_action']['label'] }}"
                            >
                                <span aria-hidden="true" class="text-lg leading-none">×</span>
                            </button>
                        @endif
                    </div>
                </div>

                <div id="{{ $activeAnnouncement['body_id'] }}" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6">
                    <div class="overflow-x-auto">
                        <div class="fi-prose max-w-none break-words text-sm leading-6 text-slate-700 dark:text-slate-200 [&_a]:break-all [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-xl [&_pre]:overflow-x-auto [&_table]:min-w-full [&_td]:align-top [&_th]:align-top">
                            {{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make((string) $activeAnnouncement['message'])
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsVisibility('public') }}
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 px-4 py-4 sm:px-6 dark:border-slate-800">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $activeAnnouncement['footer_text'] }}</p>

                        <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row">
                            <x-filament::button
                                type="button"
                                :color="$activeAnnouncement['primary_action']['color']"
                                class="w-full sm:w-auto"
                                wire:click="{{ $activeAnnouncement['primary_action']['method'] }}"
                                wire:loading.attr="disabled"
                                wire:target="{{ $activeAnnouncement['primary_action']['target'] }}"
                            >
                                {{ $activeAnnouncement['primary_action']['label'] }}
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
