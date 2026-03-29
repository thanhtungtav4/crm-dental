@php
    $selectedConversation = $this->selectedConversation;
    $conversationList = $this->conversationList;
@endphp

<x-filament-panels::page>
    <div wire:poll.{{ $this->getPollingIntervalSeconds() }}s="refreshInbox" class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ $this->getSubheading() }}
        </p>

        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <x-filament::section heading="Queue hội thoại" description="Tin nhắn inbound mới từ Zalo OA sẽ tự vào đây theo chu kỳ polling đã cấu hình.">
                <div class="space-y-3">
                    @forelse($conversationList as $conversation)
                        @php
                            $isSelected = (int) $conversation->id === (int) ($selectedConversation?->id ?? 0);
                        @endphp

                        <button
                            type="button"
                            wire:key="conversation-{{ $conversation->id }}"
                            wire:click="selectConversation({{ $conversation->id }})"
                            class="{{ $isSelected
                                ? 'border-primary-300 bg-primary-50/70 dark:border-primary-700 dark:bg-primary-950/30'
                                : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50/40 dark:border-gray-800 dark:bg-gray-950/50 dark:hover:border-primary-800 dark:hover:bg-primary-950/20' }} flex w-full flex-col gap-3 rounded-2xl border px-4 py-4 text-left transition"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $conversation->displayName() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $conversation->branch?->name ?? 'Chưa route chi nhánh' }}
                                    </p>
                                </div>

                                @if($conversation->unread_count > 0)
                                    <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-danger-500 px-2 py-1 text-[11px] font-semibold text-white">
                                        {{ $conversation->unread_count }}
                                    </span>
                                @endif
                            </div>

                            <p class="line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                                {{ $conversation->latestPreview() }}
                            </p>

                            <div class="flex items-center justify-between gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                                <span>{{ $conversation->customer_id ? 'Đã gắn lead' : 'Chưa gắn lead' }}</span>
                                <span>{{ optional($conversation->last_message_at)->diffForHumans() ?? '-' }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-8 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Chưa có hội thoại nào trong scope hiện tại.
                        </div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Chi tiết hội thoại" description="Phản hồi trực tiếp, gắn lead vào đúng conversation và tiếp tục giữ luồng tin nhắn về sau.">
                @if($selectedConversation)
                    <div class="space-y-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                                        {{ $selectedConversation->displayName() }}
                                    </h3>

                                    <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {{ $selectedConversation->provider }}
                                    </span>

                                    @if($selectedConversation->customer_id)
                                        <span class="inline-flex rounded-full border border-success-200 bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200">
                                            Đã gắn lead
                                        </span>
                                    @endif
                                </div>

                                <div class="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <p>Khách ngoài hệ thống: {{ $selectedConversation->external_user_id }}</p>
                                    <p>
                                        Chi nhánh: {{ $selectedConversation->branch?->name ?? 'Chưa route' }}
                                        · Phụ trách: {{ $selectedConversation->assignee?->name ?? 'Chưa claim' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if($selectedConversation->customer_id)
                                    <x-filament::button
                                        color="gray"
                                        tag="a"
                                        :href="$this->customerEditUrl($selectedConversation)"
                                    >
                                        Mở lead
                                    </x-filament::button>
                                @else
                                    <x-filament::button color="primary" wire:click="openLeadForm">
                                        Tạo lead
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-800 dark:bg-gray-950/50">
                            @forelse($selectedConversation->messages as $message)
                                @php
                                    $isInbound = $message->isInbound();
                                    $bubbleClasses = $isInbound
                                        ? 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'
                                        : 'border-primary-200 bg-primary-50/80 dark:border-primary-800 dark:bg-primary-950/30';
                                @endphp

                                <div wire:key="message-{{ $message->id }}" class="flex {{ $isInbound ? 'justify-start' : 'justify-end' }}">
                                    <div class="{{ $bubbleClasses }} w-full max-w-2xl rounded-2xl border px-4 py-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                                {{ $isInbound ? 'Khách' : ($message->sender?->name ?? 'CRM') }}
                                            </p>
                                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                                {{ optional($message->message_at)->format('d/m/Y H:i') ?? '-' }}
                                            </p>
                                        </div>

                                        <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200">
                                            {{ filled($message->body) ? $message->body : 'Tin nhắn không hỗ trợ hiển thị ở v1.' }}
                                        </p>

                                        <div class="mt-3 flex items-center justify-between gap-3">
                                            <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                                {{ $this->messageStatusLabel($message->status) }}
                                            </span>

                                            @if($message->status === \App\Models\ConversationMessage::STATUS_FAILED)
                                                <x-filament::button
                                                    type="button"
                                                    color="gray"
                                                    size="xs"
                                                    wire:click="retryMessage({{ $message->id }})"
                                                >
                                                    Gửi lại
                                                </x-filament::button>
                                            @endif
                                        </div>

                                        @if($message->status === \App\Models\ConversationMessage::STATUS_FAILED && filled($message->last_error))
                                            <p class="mt-2 text-xs text-danger-600 dark:text-danger-300">
                                                {{ $message->last_error }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Hội thoại này chưa có tin nhắn nào.
                                </div>
                            @endforelse
                        </div>

                        <form wire:submit="sendReply" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950/50">
                            <div class="space-y-2">
                                <label for="conversation-reply" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Phản hồi từ CRM
                                </label>
                                <textarea
                                    id="conversation-reply"
                                    wire:model.live="draftReply"
                                    rows="4"
                                    placeholder="Nhập phản hồi gửi qua Zalo OA..."
                                    class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    data-testid="reply-composer"
                                ></textarea>
                                @error('draftReply')
                                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Hội thoại chưa có websocket ở v1, nên page sẽ tự polling để cập nhật thread mới.
                                </p>

                                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="sendReply">
                                    Gửi phản hồi
                                </x-filament::button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Chọn một hội thoại ở cột bên trái để xem thread chi tiết.
                    </div>
                @endif
            </x-filament::section>
        </div>
    </div>

    @if($showLeadModal && $selectedConversation)
        <div class="fixed inset-0 z-[1000] flex items-end justify-center overflow-y-auto bg-slate-950/45 p-3 backdrop-blur-sm sm:items-center sm:p-6">
            <div class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-3xl min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:max-h-[calc(100dvh-3rem)]">
                <div class="border-b border-slate-100 px-4 py-4 sm:px-6 dark:border-slate-800">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Tạo lead từ hội thoại</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Prefill từ Zalo OA và gắn lead vào đúng conversation hiện tại.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-slate-100"
                            wire:click="closeLeadForm"
                            aria-label="Đóng modal tạo lead"
                        >
                            <span aria-hidden="true" class="text-lg leading-none">×</span>
                        </button>
                    </div>
                </div>

                <form wire:submit="createLead" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="lead-full-name" class="text-sm font-medium text-slate-700 dark:text-slate-200">Họ và tên</label>
                                <input
                                    id="lead-full-name"
                                    type="text"
                                    wire:model.live="leadForm.full_name"
                                    class="fi-input block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    placeholder="VD: Nguyễn Văn A"
                                    data-testid="lead-full-name"
                                />
                                @error('leadForm.full_name')
                                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="lead-phone" class="text-sm font-medium text-slate-700 dark:text-slate-200">Số điện thoại</label>
                                <input
                                    id="lead-phone"
                                    type="text"
                                    wire:model.live="leadForm.phone"
                                    class="fi-input block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    placeholder="VD: 0901234567"
                                    data-testid="lead-phone"
                                />
                                @error('leadForm.phone')
                                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="lead-email" class="text-sm font-medium text-slate-700 dark:text-slate-200">Email</label>
                                <input
                                    id="lead-email"
                                    type="email"
                                    wire:model.live="leadForm.email"
                                    class="fi-input block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    placeholder="email@example.com"
                                    data-testid="lead-email"
                                />
                                @error('leadForm.email')
                                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="lead-branch" class="text-sm font-medium text-slate-700 dark:text-slate-200">Chi nhánh</label>
                                <select
                                    id="lead-branch"
                                    wire:model.live="leadForm.branch_id"
                                    class="fi-input block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    data-testid="lead-branch"
                                >
                                    <option value="">Chọn chi nhánh</option>
                                    @foreach($this->branchOptions as $branchId => $branchLabel)
                                        <option value="{{ $branchId }}">{{ $branchLabel }}</option>
                                    @endforeach
                                </select>
                                @error('leadForm.branch_id')
                                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <label for="lead-assigned-to" class="text-sm font-medium text-slate-700 dark:text-slate-200">Phụ trách</label>
                                <select
                                    id="lead-assigned-to"
                                    wire:model.live="leadForm.assigned_to"
                                    class="fi-input block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    data-testid="lead-assigned-to"
                                >
                                    <option value="">Chưa gán</option>
                                    @foreach($this->assignableStaffOptions as $staffId => $staffLabel)
                                        <option value="{{ $staffId }}">{{ $staffLabel }}</option>
                                    @endforeach
                                </select>
                                @error('leadForm.assigned_to')
                                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <label for="lead-notes" class="text-sm font-medium text-slate-700 dark:text-slate-200">Ghi chú nội bộ</label>
                                <textarea
                                    id="lead-notes"
                                    rows="4"
                                    wire:model.live="leadForm.notes"
                                    class="fi-input block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    placeholder="Bổ sung ghi chú nếu cần..."
                                    data-testid="lead-notes"
                                ></textarea>
                                @error('leadForm.notes')
                                    <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-300 sm:grid-cols-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Nguồn</p>
                                <p class="mt-1 font-medium">zalo</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Nguồn chi tiết</p>
                                <p class="mt-1 font-medium">zalo_oa_inbox</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Trạng thái</p>
                                <p class="mt-1 font-medium">lead</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 bg-white px-4 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-filament::button type="button" color="gray" wire:click="closeLeadForm">
                                Đóng
                            </x-filament::button>

                            <x-filament::button type="submit" color="primary" wire:loading.attr="disabled" wire:target="createLead" data-testid="lead-save">
                                Lưu lead
                            </x-filament::button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
