@props([
    'threadPanel',
    'composerPanel',
    'messages',
])

<div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-gray-50/70 shadow-sm dark:border-gray-800 dark:bg-gray-950/55">
    <div class="border-b border-gray-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-950/80">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $threadPanel['heading'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $threadPanel['description'] }}
                </p>
            </div>

            @if($threadPanel['show_load_older_messages'])
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    wire:click="loadOlderMessages"
                    data-testid="load-older-messages"
                >
                    {{ $threadPanel['load_older_label'] }}
                </x-filament::button>
            @endif
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
        <div class="space-y-3">
            @forelse($messages as $message)
                <div wire:key="message-{{ $message['id'] }}" class="flex {{ $message['container_class'] }}">
                    <div class="{{ $message['bubble_class'] }} w-full max-w-2xl rounded-2xl border px-4 py-3 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                {{ $message['sender_label'] }}
                            </p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $message['message_at_text'] }}
                            </p>
                        </div>

                        <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200">
                            {{ $message['body'] }}
                        </p>

                        <div class="mt-3 flex items-center justify-between gap-3">
                            <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                {{ $message['status_label'] }}
                            </span>

                            @if($message['can_retry'])
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    size="xs"
                                    wire:click="retryMessage({{ $message['id'] }})"
                                >
                                    Gửi lại
                                </x-filament::button>
                            @endif
                        </div>

                        @if($message['last_error'])
                            <p class="mt-2 text-xs text-danger-600 dark:text-danger-300">
                                {{ $message['last_error'] }}
                            </p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    {{ $threadPanel['empty_state_text'] }}
                </div>
            @endforelse
        </div>
    </div>

    <form wire:submit="sendReply" class="border-t border-gray-200 bg-white/95 px-4 py-4 backdrop-blur dark:border-gray-800 dark:bg-gray-950/90">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
            <div class="space-y-2">
                <label for="conversation-reply" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ $composerPanel['label'] }}
                </label>
                <textarea
                    id="conversation-reply"
                    wire:model.live="draftReply"
                    rows="4"
                    placeholder="{{ $composerPanel['placeholder'] }}"
                    class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    data-testid="reply-composer"
                ></textarea>
                @error('draftReply')
                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $composerPanel['helper_text'] }}
                </p>
            </div>

            <div class="flex flex-col gap-3 xl:items-end">
                <p class="max-w-xs text-xs text-gray-500 dark:text-gray-400">
                    {{ $composerPanel['polling_notice'] }}
                </p>

                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="sendReply">
                    {{ $composerPanel['submit_label'] }}
                </x-filament::button>
            </div>
        </div>
    </form>
</div>
