<x-filament::section>
    <div class="space-y-4">
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
