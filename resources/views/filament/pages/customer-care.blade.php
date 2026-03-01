<x-filament::section>
    <div class="space-y-4">
        @php
            $slaSummary = $this->slaSummary;
        @endphp

        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60">
                <p class="text-xs text-gray-500">Ticket đang mở</p>
                <p class="text-xl font-semibold">{{ number_format($slaSummary['total_open']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-400/30 dark:bg-rose-500/10">
                <p class="text-xs text-rose-700 dark:text-rose-300">Quá hạn SLA</p>
                <p class="text-xl font-semibold text-rose-700 dark:text-rose-300">{{ number_format($slaSummary['overdue']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-400/30 dark:bg-amber-500/10">
                <p class="text-xs text-amber-700 dark:text-amber-300">Đến hạn hôm nay</p>
                <p class="text-xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format($slaSummary['due_today']) }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-400/30 dark:bg-blue-500/10">
                <p class="text-xs text-blue-700 dark:text-blue-300">Queue No-show</p>
                <p class="text-xl font-semibold text-blue-700 dark:text-blue-300">{{ number_format($slaSummary['priority_no_show']) }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-3 dark:border-violet-400/30 dark:bg-violet-500/10">
                <p class="text-xs text-violet-700 dark:text-violet-300">Queue Recall</p>
                <p class="text-xl font-semibold text-violet-700 dark:text-violet-300">{{ number_format($slaSummary['priority_recall']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                <p class="text-xs text-emerald-700 dark:text-emerald-300">Queue Follow-up</p>
                <p class="text-xl font-semibold text-emerald-700 dark:text-emerald-300">{{ number_format($slaSummary['priority_follow_up']) }}</p>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Theo kênh</h3>
                <div class="mt-2 space-y-1.5 text-sm">
                    @forelse($slaSummary['by_channel'] as $row)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-300">{{ $row['label'] }}</span>
                            <span class="font-semibold">{{ number_format($row['total']) }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">Chưa có dữ liệu.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Theo chi nhánh</h3>
                <div class="mt-2 space-y-1.5 text-sm">
                    @forelse($slaSummary['by_branch'] as $row)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-300">{{ $row['label'] }}</span>
                            <span class="font-semibold">{{ number_format($row['total']) }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">Chưa có dữ liệu.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Theo nhân viên</h3>
                <div class="mt-2 space-y-1.5 text-sm">
                    @forelse($slaSummary['by_staff'] as $row)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-300">{{ $row['label'] }}</span>
                            <span class="font-semibold">{{ number_format($row['total']) }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">Chưa có dữ liệu.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4 border-b border-gray-200">
            @foreach($this->getTabs() as $tabKey => $tabLabel)
                <button
                    type="button"
                    wire:click="setActiveTab('{{ $tabKey }}')"
                    class="px-4 py-2 text-sm font-medium border-b-2 {{ $activeTab === $tabKey ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament::section>
