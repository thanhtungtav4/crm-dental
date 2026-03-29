@php
    $selectedConversation = $this->selectedConversation;
    $conversationList = $this->conversationList;
    $inboxStats = $this->inboxStats;
@endphp

<x-filament-panels::page>
    @if (! $this->isConversationSchemaReady())
        <x-filament::section
            heading="Inbox hội thoại chưa sẵn sàng"
            description="Trang này sẽ tự hoạt động lại sau khi schema hội thoại được cài đặt đầy đủ."
        >
            <div class="rounded-2xl border border-dashed border-warning-300 bg-warning-50 px-5 py-5 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/20 dark:text-warning-100">
                Quản trị viên cần hoàn tất cài đặt dữ liệu hội thoại trước khi đội CSKH sử dụng màn hình này.
            </div>
        </x-filament::section>
    @else
        <div wire:poll.{{ $this->getPollingIntervalSeconds() }}s="refreshInbox" class="space-y-6">
            <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <x-filament::section heading="Queue hội thoại" description="Tin nhắn inbound mới từ Zalo OA và Facebook Messenger sẽ tự vào đây theo chu kỳ polling đã cấu hình.">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-gray-800 dark:bg-gray-950/60">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">Chưa đọc</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $inboxStats['unread'] }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cần mở thread để xử lý ngay trong ca.</p>
                        </div>

                        <div class="rounded-2xl border border-warning-200 bg-warning-50/80 px-4 py-4 shadow-sm dark:border-warning-900/60 dark:bg-warning-950/20">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-warning-700 dark:text-warning-200">Đến hạn follow-up</p>
                            <p class="mt-2 text-2xl font-semibold text-warning-900 dark:text-warning-100">{{ $inboxStats['due'] }}</p>
                            <p class="mt-1 text-xs text-warning-700/80 dark:text-warning-200/80">Ưu tiên gọi lại hoặc chốt bước tiếp theo.</p>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-gray-800 dark:bg-gray-950/60">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">Chưa claim</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $inboxStats['unclaimed'] }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Hội thoại chưa có người phụ trách rõ ràng.</p>
                        </div>

                        <div class="rounded-2xl border border-primary-200 bg-primary-50/80 px-4 py-4 shadow-sm dark:border-primary-900/60 dark:bg-primary-950/20">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-primary-700 dark:text-primary-200">Chưa gắn lead</p>
                            <p class="mt-2 text-2xl font-semibold text-primary-900 dark:text-primary-100">{{ $inboxStats['unbound'] }}</p>
                            <p class="mt-1 text-xs text-primary-700/80 dark:text-primary-200/80">Còn cơ hội convert trực tiếp từ inbox.</p>
                        </div>
                    </div>

                    <div class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50/70 p-3 dark:border-gray-800 dark:bg-gray-950/40">
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem]">
                            <div class="space-y-2">
                                <label for="conversation-search" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Tìm nhanh hội thoại
                                </label>
                                <input
                                    id="conversation-search"
                                    type="search"
                                    name="conversation-search"
                                    wire:model.live.debounce.400ms="search"
                                    autocomplete="off"
                                    inputmode="search"
                                    placeholder="Tìm theo tên, lead, note…"
                                    class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    data-testid="conversation-search"
                                />
                            </div>

                            <div class="space-y-2">
                                <label for="conversation-provider-filter" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    Kênh
                                </label>
                                <select
                                    id="conversation-provider-filter"
                                    wire:model.live="providerFilter"
                                    class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    data-testid="conversation-provider-filter"
                                >
                                    <option value="all">Tất cả</option>
                                    <option value="zalo">Zalo OA</option>
                                    <option value="facebook">Facebook Messenger</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach($this->inboxTabOptions as $tabValue => $tabLabel)
                                @php
                                    $isActiveTab = $this->inboxTab === $tabValue;
                                @endphp

                                <button
                                    type="button"
                                    wire:key="conversation-tab-{{ $tabValue }}"
                                    wire:click="$set('inboxTab', '{{ $tabValue }}')"
                                    class="{{ $isActiveTab
                                        ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-200'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:text-primary-200' }} inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                    data-testid="conversation-tab-{{ $tabValue }}"
                                >
                                    {{ $tabLabel }}
                                </button>
                            @endforeach
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Đang hiển thị {{ $conversationList->count() }} hội thoại theo bộ lọc hiện tại.
                        </p>
                    </div>

                    <div class="space-y-3">
                    @forelse($conversationList as $conversation)
                        @php
                            $isSelected = (int) $conversation->id === (int) ($selectedConversation?->id ?? 0);
                            $priorityClasses = match ($conversation->handoffPriorityValue()) {
                                \App\Models\Conversation::PRIORITY_LOW => 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                                \App\Models\Conversation::PRIORITY_HIGH => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
                                \App\Models\Conversation::PRIORITY_URGENT => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
                                default => 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-900/60 dark:bg-primary-950/30 dark:text-primary-200',
                            };
                            $statusClasses = match ($conversation->handoffStatusValue()) {
                                \App\Models\Conversation::HANDOFF_STATUS_CONSULTING => 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-900/60 dark:bg-primary-950/30 dark:text-primary-200',
                                \App\Models\Conversation::HANDOFF_STATUS_QUOTED => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
                                \App\Models\Conversation::HANDOFF_STATUS_WAITING_CUSTOMER => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
                                \App\Models\Conversation::HANDOFF_STATUS_FOLLOW_UP => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
                                default => 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                            };
                        @endphp

                        <button
                            type="button"
                            wire:key="conversation-{{ $conversation->id }}"
                            wire:click="selectConversation({{ $conversation->id }})"
                            class="{{ $isSelected
                                ? 'border-primary-300 bg-primary-50/70 dark:border-primary-700 dark:bg-primary-950/30'
                                : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50/40 dark:border-gray-800 dark:bg-gray-950/50 dark:hover:border-primary-800 dark:hover:bg-primary-950/20' }} flex w-full flex-col gap-3 rounded-2xl border px-4 py-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                            {{ $conversation->displayName() }}
                                        </p>

                                        <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            {{ $conversation->providerLabel() }}
                                        </span>

                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $statusClasses }}">
                                            {{ $conversation->handoffStatusLabel() }}
                                        </span>

                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-medium {{ $priorityClasses }}">
                                            {{ $conversation->handoffPriorityLabel() }}
                                        </span>
                                    </div>
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

                            @if(filled($conversation->handoffSummaryPreview()))
                                <p class="line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                                    Note nội bộ: {{ $conversation->handoffSummaryPreview() }}
                                </p>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                                <span>{{ $conversation->customer_id ? 'Đã gắn lead' : 'Chưa gắn lead' }}</span>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if($conversation->handoffNextActionLabel())
                                        <span class="rounded-full border border-gray-200 bg-white px-2 py-1 dark:border-gray-700 dark:bg-gray-900">
                                            Follow-up {{ $conversation->handoffNextActionLabel() }}
                                        </span>
                                    @endif

                                    <span>{{ optional($conversation->last_message_at)->diffForHumans() ?? '-' }}</span>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-8 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Không có hội thoại nào khớp bộ lọc hiện tại.
                        </div>
                    @endforelse
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section heading="Chi tiết hội thoại" description="Phản hồi trực tiếp, gắn lead vào đúng conversation và tiếp tục giữ luồng tin nhắn về sau.">
                @if($selectedConversation)
                    @php
                        $selectedPriorityClasses = match ($selectedConversation->handoffPriorityValue()) {
                            \App\Models\Conversation::PRIORITY_LOW => 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                            \App\Models\Conversation::PRIORITY_HIGH => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
                            \App\Models\Conversation::PRIORITY_URGENT => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
                            default => 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-900/60 dark:bg-primary-950/30 dark:text-primary-200',
                        };
                        $selectedStatusClasses = match ($selectedConversation->handoffStatusValue()) {
                            \App\Models\Conversation::HANDOFF_STATUS_CONSULTING => 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-900/60 dark:bg-primary-950/30 dark:text-primary-200',
                            \App\Models\Conversation::HANDOFF_STATUS_QUOTED => 'border-success-200 bg-success-50 text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200',
                            \App\Models\Conversation::HANDOFF_STATUS_WAITING_CUSTOMER => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-200',
                            \App\Models\Conversation::HANDOFF_STATUS_FOLLOW_UP => 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-900/60 dark:bg-danger-950/30 dark:text-danger-200',
                            default => 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                        };
                    @endphp

                    <div class="flex min-h-[72vh] flex-col gap-4">
                        <div class="rounded-[1.75rem] border border-gray-200 bg-linear-to-br from-white to-primary-50/30 p-5 shadow-sm dark:border-gray-800 dark:from-gray-950 dark:to-primary-950/20">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                                            {{ $selectedConversation->displayName() }}
                                        </h3>

                                        <span class="inline-flex rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            {{ $selectedConversation->providerLabel() }}
                                        </span>

                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $selectedStatusClasses }}">
                                            {{ $selectedConversation->handoffStatusLabel() }}
                                        </span>

                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $selectedPriorityClasses }}">
                                            {{ $selectedConversation->handoffPriorityLabel() }}
                                        </span>

                                        @if($selectedConversation->customer_id)
                                            <span class="inline-flex rounded-full border border-success-200 bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200">
                                                Đã gắn lead
                                            </span>
                                        @endif
                                    </div>

                                    <div class="grid gap-3 text-sm text-gray-500 dark:text-gray-400 lg:grid-cols-3">
                                        <div class="rounded-2xl border border-gray-200/80 bg-white/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">Khách ngoài hệ thống</p>
                                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $selectedConversation->external_user_id }}</p>
                                        </div>

                                        <div class="rounded-2xl border border-gray-200/80 bg-white/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">Chi nhánh / phụ trách</p>
                                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                                {{ $selectedConversation->branch?->name ?? 'Chưa route' }}
                                                · {{ $selectedConversation->assignee?->name ?? 'Chưa claim' }}
                                            </p>
                                        </div>

                                        <div class="rounded-2xl border border-gray-200/80 bg-white/80 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-400 dark:text-gray-500">Follow-up tiếp theo</p>
                                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                                                {{ $selectedConversation->handoffNextActionLabel('d/m/Y H:i') ?? 'Chưa đặt lịch follow-up' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex w-full flex-col gap-3 xl:w-auto xl:min-w-[21rem]">
                                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                        <select
                                            wire:model.defer="assignmentForm.assigned_to"
                                            class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="conversation-assignee"
                                        >
                                            <option value="">Queue chung / chưa claim</option>
                                            @foreach($this->conversationAssigneeOptions as $staffId => $staffLabel)
                                                <option value="{{ $staffId }}">{{ $staffLabel }}</option>
                                            @endforeach
                                        </select>

                                        <x-filament::button
                                            type="button"
                                            color="gray"
                                            wire:click="saveConversationAssignee"
                                            data-testid="conversation-assignee-save"
                                        >
                                            Lưu phụ trách
                                        </x-filament::button>
                                    </div>
                                    @error('assignmentForm.assigned_to')
                                        <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                    @enderror

                                    <div class="flex flex-wrap gap-2">
                                        <x-filament::button
                                            type="button"
                                            color="gray"
                                            wire:click="claimConversation"
                                            data-testid="conversation-claim-me"
                                        >
                                            Claim tôi
                                        </x-filament::button>

                                        @if($selectedConversation->assigned_to)
                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                wire:click="releaseConversation"
                                                data-testid="conversation-release-claim"
                                            >
                                                Nhả claim
                                            </x-filament::button>
                                        @endif

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
                            </div>
                        </div>

                        <form wire:submit="saveHandoffNote" class="grid gap-4 rounded-[1.75rem] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950/60 xl:grid-cols-[minmax(0,1.2fr)_13rem_13rem]">
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Note bàn giao nội bộ</h4>
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $selectedStatusClasses }}">
                                            {{ $selectedConversation->handoffStatusLabel() }}
                                        </span>
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $selectedPriorityClasses }}">
                                            {{ $selectedConversation->handoffPriorityLabel() }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Chỉ hiển thị trong CRM để CSKH khác mở vào là nắm nhanh bối cảnh, ưu tiên hiện tại và bước follow-up tiếp theo.
                                    </p>

                                    @if($selectedConversation->handoff_updated_at)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Cập nhật gần nhất bởi {{ $selectedConversation->handoffEditor?->name ?? 'CRM' }}
                                            lúc {{ $selectedConversation->handoff_updated_at->format('d/m/Y H:i') }}
                                        </p>
                                    @endif
                                </div>

                                <div class="space-y-2">
                                    <label for="handoff-summary" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Tóm tắt bàn giao
                                    </label>
                                    <textarea
                                        id="handoff-summary"
                                        wire:model.live="handoffForm.summary"
                                        rows="3"
                                        placeholder="VD: Khách đang so sánh 2 gói, đã hẹn gọi lại 17h, cần ưu tiên tư vấn giá."
                                        class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        data-testid="handoff-summary"
                                    ></textarea>
                                    @error('handoffForm.summary')
                                        <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <label for="handoff-status" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Trạng thái xử lý
                                    </label>
                                    <select
                                        id="handoff-status"
                                        wire:model.live="handoffForm.status"
                                        class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        data-testid="handoff-status"
                                    >
                                        @foreach($this->handoffStatusOptions as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('handoffForm.status')
                                        <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="handoff-next-action-at" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Follow-up tiếp theo
                                    </label>
                                    <input
                                        id="handoff-next-action-at"
                                        type="datetime-local"
                                        wire:model.live="handoffForm.next_action_at"
                                        class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        data-testid="handoff-next-action-at"
                                    />
                                    @error('handoffForm.next_action_at')
                                        <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <label for="handoff-priority" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        Mức ưu tiên
                                    </label>
                                    <select
                                        id="handoff-priority"
                                        wire:model.live="handoffForm.priority"
                                        class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        data-testid="handoff-priority"
                                    >
                                        @foreach($this->handoffPriorityOptions as $priorityValue => $priorityLabel)
                                            <option value="{{ $priorityValue }}">{{ $priorityLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('handoffForm.priority')
                                        <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex h-full flex-col justify-between gap-3 rounded-2xl border border-dashed border-gray-200 bg-gray-50/80 p-4 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-400">
                                    <p>Gợi ý: ghi nhu cầu chính, cam kết đã hẹn, thông tin còn thiếu và điều gì phải xử lý trước trong ca trực tiếp theo.</p>

                                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveHandoffNote" data-testid="handoff-save">
                                        Lưu note bàn giao
                                    </x-filament::button>
                                </div>
                            </div>
                        </form>

                        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-gray-50/70 shadow-sm dark:border-gray-800 dark:bg-gray-950/55">
                            <div class="border-b border-gray-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-950/80">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Thread hội thoại</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Đang hiển thị {{ $selectedConversation->getAttribute('loaded_message_count') }} tin gần nhất. Polling sẽ chỉ cập nhật phần thread đang mở thay vì kéo toàn bộ lịch sử.
                                        </p>
                                    </div>

                                    @if($selectedConversation->getAttribute('has_more_messages'))
                                        <x-filament::button
                                            type="button"
                                            color="gray"
                                            size="sm"
                                            wire:click="loadOlderMessages"
                                            data-testid="load-older-messages"
                                        >
                                            Xem tin cũ hơn
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                                <div class="space-y-3">
                                    @forelse($selectedConversation->messages as $message)
                                        @php
                                            $isInbound = $message->isInbound();
                                            $bubbleClasses = $isInbound
                                                ? 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'
                                                : 'border-primary-200 bg-primary-50/80 dark:border-primary-800 dark:bg-primary-950/30';
                                        @endphp

                                        <div wire:key="message-{{ $message->id }}" class="flex {{ $isInbound ? 'justify-start' : 'justify-end' }}">
                                            <div class="{{ $bubbleClasses }} w-full max-w-2xl rounded-2xl border px-4 py-3 shadow-sm">
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
                            </div>

                            <form wire:submit="sendReply" class="border-t border-gray-200 bg-white/95 px-4 py-4 backdrop-blur dark:border-gray-800 dark:bg-gray-950/90">
                                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                                    <div class="space-y-2">
                                        <label for="conversation-reply" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                            Phản hồi từ CRM
                                        </label>
                                        <textarea
                                            id="conversation-reply"
                                            wire:model.live="draftReply"
                                            rows="4"
                                            placeholder="Nhập phản hồi gửi qua hội thoại đang chọn..."
                                            class="fi-input block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="reply-composer"
                                        ></textarea>
                                        @error('draftReply')
                                            <p class="text-sm text-danger-600 dark:text-danger-300">{{ $message }}</p>
                                        @enderror
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Composer được giữ cố định ở cuối thread để CSKH không phải kéo xuống đáy lịch sử sau mỗi lần xem lại tin cũ.
                                        </p>
                                    </div>

                                    <div class="flex flex-col gap-3 xl:items-end">
                                        <p class="max-w-xs text-xs text-gray-500 dark:text-gray-400">
                                            Chưa có websocket ở v1, nên page sẽ tự polling để cập nhật thread mới giữa các kênh.
                                        </p>

                                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="sendReply">
                                            Gửi phản hồi
                                        </x-filament::button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Chọn một hội thoại ở cột bên trái để xem thread chi tiết.
                    </div>
                @endif
            </x-filament::section>
            </div>
        </div>
    @endif

    @if($showLeadModal && $selectedConversation)
        <div class="fixed inset-0 z-[1000] flex items-end justify-center overflow-y-auto bg-slate-950/45 p-3 backdrop-blur-sm sm:items-center sm:p-6">
            <div class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-3xl min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:max-h-[calc(100dvh-3rem)]">
                <div class="border-b border-slate-100 px-4 py-4 sm:px-6 dark:border-slate-800">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Tạo lead từ hội thoại</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Prefill từ kênh chat hiện tại và gắn lead vào đúng conversation đang chọn.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-slate-100 dark:focus-visible:ring-offset-slate-900"
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
                                <p class="mt-1 font-medium">{{ $leadForm['source'] ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Nguồn chi tiết</p>
                                <p class="mt-1 font-medium">{{ $leadForm['source_detail'] ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">Trạng thái</p>
                                <p class="mt-1 font-medium">{{ $leadForm['status'] ?? '-' }}</p>
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
