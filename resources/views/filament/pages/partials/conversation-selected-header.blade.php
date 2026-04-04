@props([
    'conversation',
    'summaryCards',
    'assigneeOptions',
    'customerEditUrl',
])

<div class="rounded-[1.75rem] border border-gray-200 bg-linear-to-br from-white to-primary-50/30 p-5 shadow-sm dark:border-gray-800 dark:from-gray-950 dark:to-primary-950/20">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $conversation->displayName() }}
                </h3>

                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $conversation->providerBadgeClasses() }}">
                    {{ $conversation->providerLabel() }}
                </span>

                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $conversation->handoffStatusBadgeClasses() }}">
                    {{ $conversation->handoffStatusLabel() }}
                </span>

                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $conversation->handoffPriorityBadgeClasses() }}">
                    {{ $conversation->handoffPriorityLabel() }}
                </span>

                @if($conversation->customer_id)
                    <span class="inline-flex rounded-full border border-success-200 bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:border-success-900/60 dark:bg-success-950/30 dark:text-success-200">
                        Đã gắn lead
                    </span>
                @endif
            </div>

            <div class="grid gap-3 text-sm text-gray-500 dark:text-gray-400 lg:grid-cols-3">
                @foreach($summaryCards as $card)
                    @include('filament.pages.partials.conversation-summary-card', ['card' => $card])
                @endforeach
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
                    @foreach($assigneeOptions as $staffId => $staffLabel)
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

                @if($conversation->assigned_to)
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="releaseConversation"
                        data-testid="conversation-release-claim"
                    >
                        Nhả claim
                    </x-filament::button>
                @endif

                @if($conversation->customer_id)
                    <x-filament::button
                        color="gray"
                        tag="a"
                        :href="$customerEditUrl"
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
