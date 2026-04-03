@php
    /** @var \App\Models\Patient|null $record */
    $record = $record ?? null;
    /** @var array<string, mixed> $basicInfoPanels */
    $basicInfoPanels = $basicInfoPanels ?? [];
@endphp

<div class="crm-pane-stack-lg" wire:key="patient-{{ $record?->id }}-basic-info">
    @if($record)
        @include('filament.resources.patients.pages.partials.livewire-tab-panel', [
            'component' => \App\Filament\Resources\Patients\Widgets\PatientOverviewWidget::class,
            'parameters' => ['record' => $record],
            'wireKey' => 'patient-' . $record->id . '-overview',
        ])

        @include('filament.resources.patients.pages.partials.relation-manager-section', [
            'section' => $basicInfoPanels['contacts_section'] ?? [],
            'relationManager' => \App\Filament\Resources\Patients\RelationManagers\ContactsRelationManager::class,
            'ownerRecord' => $record,
            'pageClass' => \App\Filament\Resources\Patients\Pages\ViewPatient::class,
            'wireKey' => 'patient-' . $record->id . '-contacts',
        ])

        @include('filament.resources.patients.pages.partials.action-prompt-card', [
            'prompt' => $basicInfoPanels['activity_log_prompt'] ?? [],
        ])
    @else
        <div class="crm-empty-inline">
            <p>{{ $basicInfoPanels['empty_state_text'] ?? '' }}</p>
        </div>
    @endif
</div>
