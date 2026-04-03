<x-filament-panels::page>
    <div
        class="crm-patient-record-page"
        x-data="@include('filament.resources.patients.pages.partials.workspace-shell-state')"
        x-init="@include('filament.resources.patients.pages.partials.workspace-shell-init')"
    >
        {{-- Patient Overview Card --}}
        <div class="crm-section-block">
            @include('filament.resources.patients.pages.partials.patient-overview-card', [
                'overviewCard' => $this->workspaceViewState['overview_card'] ?? [],
            ])
        </div>

        {{-- Tabs Navigation --}}
        <div class="crm-section-block">
            @include('filament.resources.patients.pages.partials.workspace-tabs-nav', [
                'tabs' => $this->workspaceViewState['rendered_tabs'] ?? [],
            ])


            {{-- Tab Content --}}
            <div
                id="{{ $this->workspaceViewState['active_panel_id'] ?? '' }}"
                role="tabpanel"
                aria-labelledby="{{ $this->workspaceViewState['active_tab_button_id'] ?? '' }}"
                tabindex="0"
            >
                @include($this->activeWorkspaceTabView['partial'], $this->activeWorkspaceTabView['data'])
            </div>

            <div
                x-cloak
                x-show="copiedMessage"
                x-transition.opacity.duration.150ms
                class="crm-copy-toast"
                x-text="copiedMessage"
            ></div>

        </div>
    </div>
</x-filament-panels::page>
