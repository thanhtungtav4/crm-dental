<div class="space-y-4">
    @include('livewire.partials.patient-treatment-plan.list-section', [
        'viewState' => $viewState,
    ])

    @include('livewire.partials.patient-treatment-plan.plan-modal', [
        'isVisible' => $showPlanModal,
        'viewState' => $viewState,
        'diagnosisOptions' => $diagnosisOptions,
    ])

    @include('livewire.partials.patient-treatment-plan.procedure-modal', [
        'isVisible' => $showProcedureModal,
        'selectedCategoryId' => $selectedCategoryId,
        'categories' => $categories,
        'viewState' => $viewState,
    ])
</div>
