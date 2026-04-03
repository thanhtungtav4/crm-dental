<div class="flex items-center gap-4 border-b border-gray-200" role="tablist" aria-label="Danh mục chăm sóc khách hàng">
    @foreach($tabsPanel['rendered_tabs'] as $tab)
        <button
            type="button"
            id="{{ $tab['button_id'] }}"
            wire:click="setActiveTab('{{ $tab['key'] }}')"
            role="tab"
            aria-selected="{{ $tab['is_active'] ? 'true' : 'false' }}"
            aria-controls="{{ $tab['panel_id'] }}"
            tabindex="{{ $tab['tab_index'] }}"
            class="px-4 py-2 text-sm font-medium border-b-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 {{ $tab['button_class'] }}"
        >
            {{ $tab['label'] }}
        </button>
    @endforeach
</div>
