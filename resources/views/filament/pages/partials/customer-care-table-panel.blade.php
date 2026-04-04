<div
    id="{{ $activeTabView['panel_id'] }}"
    role="tabpanel"
    aria-labelledby="{{ $activeTabView['labelled_by'] }}"
    tabindex="0"
    class="pt-4 focus:outline-none"
>
    {{ $this->table }}
</div>
