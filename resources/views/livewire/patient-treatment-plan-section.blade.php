<div class="space-y-4">
    @include('livewire.partials.patient-treatment-plan.list-section', [
        'panel' => $viewState['list_panel'],
    ])

    @include('livewire.partials.patient-treatment-plan.plan-modal', [
        'panel' => $viewState['plan_modal'],
    ])

    @include('livewire.partials.patient-treatment-plan.procedure-modal', [
        'panel' => $viewState['procedure_modal'],
    ])
</div>
