@props([
    'record',
    'renderedTreatmentProgressPanel' => [],
])

<div class="crm-pane-stack-lg" wire:key="patient-{{ $record->id }}-exam-treatment">
    @livewire('patient-exam-form', ['patient' => $record], key('patient-' . $record->id . '-exam-form'))

    @livewire('patient-treatment-plan-section', ['patientId' => $record->id], key('patient-' . $record->id . '-treatment-plan'))

    @include('filament.resources.patients.pages.partials.treatment-progress-panel', [
        'panel' => $renderedTreatmentProgressPanel,
    ])
</div>
