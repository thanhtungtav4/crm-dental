@php
    /** @var \App\Models\Patient $record */
    $record = $record;
    /** @var array<string, mixed> $renderedPaymentPanel */
    $renderedPaymentPanel = $renderedPaymentPanel ?? [];
@endphp

<div class="crm-payment-tab" wire:key="patient-{{ $record->id }}-payments">
    @include('filament.resources.patients.pages.partials.payment-summary-panel', [
        'panel' => $renderedPaymentPanel,
    ])

    @foreach($renderedPaymentPanel['blocks'] ?? [] as $block)
        @include('filament.resources.patients.pages.partials.relation-manager-block', [
            'block' => $block,
            'ownerRecord' => $record,
            'pageClass' => \App\Filament\Resources\Patients\Pages\ViewPatient::class,
            'wireKey' => 'patient-' . $record->id . '-payment-block-' . $block['key'],
        ])
    @endforeach
</div>
