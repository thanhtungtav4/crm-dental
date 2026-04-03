@props([
    'tabs',
])

<div class="crm-top-tabs-shell">
    <div class="crm-top-tabs-mobile">
        <label for="patient-workspace-tab-select" class="crm-top-tabs-mobile-label">
            Khu vực làm việc
        </label>

        <div class="crm-top-tabs-mobile-field">
            <select
                id="patient-workspace-tab-select"
                class="crm-top-tabs-select"
                aria-label="Chọn khu vực làm việc hồ sơ bệnh nhân"
                wire:change="setActiveTab($event.target.value)"
            >
                @foreach($tabs as $tab)
                    <option value="{{ $tab['id'] }}" @selected($tab['aria_selected'] === 'true')>
                        {{ $tab['label'] }}@if($tab['count'] !== null) ({{ $tab['count'] }})@endif
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <nav x-ref="topTabs" class="crm-top-tabs crm-top-tabs-nav crm-top-tabs-desktop" role="tablist" aria-label="Khu vực làm việc hồ sơ bệnh nhân">
        @foreach($tabs as $tab)
            <button
                type="button"
                id="{{ $tab['button_id'] }}"
                wire:click="setActiveTab('{{ $tab['id'] }}')"
                role="tab"
                aria-selected="{{ $tab['aria_selected'] }}"
                aria-controls="{{ $tab['panel_id'] }}"
                tabindex="{{ $tab['tabindex'] }}"
                class="{{ $tab['button_class'] }}"
            >
                <span>{{ $tab['label'] }}</span>
                @if($tab['count'] !== null)
                    <span class="crm-top-tab-count">{{ $tab['count'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>
</div>
