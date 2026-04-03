<div class="fixed inset-0 z-[1000] flex items-end justify-center overflow-y-auto bg-slate-950/45 p-3 backdrop-blur-sm sm:items-center sm:p-6">
    <div class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-3xl min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:max-h-[calc(100dvh-3rem)]">
        <div class="border-b border-slate-100 px-4 py-4 sm:px-6 dark:border-slate-800">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $leadModalView['heading'] }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $leadModalView['description'] }}
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
                            @foreach($leadModalView['branch_options'] as $branchId => $branchLabel)
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
                            @foreach($leadModalView['assignee_options'] as $staffId => $staffLabel)
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
                    @foreach($leadModalView['summary_cards'] as $card)
                        <div>
                            <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                            <p class="mt-1 font-medium">{{ $card['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-slate-100 bg-white px-4 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-filament::button type="button" color="gray" wire:click="closeLeadForm">
                        {{ $leadModalView['close_label'] }}
                    </x-filament::button>

                    <x-filament::button type="submit" color="primary" wire:loading.attr="disabled" wire:target="createLead" data-testid="lead-save">
                        {{ $leadModalView['submit_label'] }}
                    </x-filament::button>
                </div>
            </div>
        </form>
    </div>
</div>
