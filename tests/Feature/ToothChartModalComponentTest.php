<?php

use App\Models\PatientToothCondition;
use App\Models\ToothCondition;
use App\Support\ToothChartModalViewState;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;

it('renders tooth chart modal from presenter payload instead of inline blade php', function (): void {
    $caries = new ToothCondition([
        'code' => 'C',
        'name' => 'Sâu răng',
        'color' => '#ef4444',
    ]);
    $caries->setAttribute('id', 11);

    $implant = new ToothCondition([
        'code' => 'I',
        'name' => 'Implant',
        'color' => '#2563eb',
    ]);
    $implant->setAttribute('id', 22);

    $adultCurrent = new PatientToothCondition([
        'tooth_number' => '18',
        'treatment_status' => PatientToothCondition::STATUS_CURRENT,
    ]);
    $adultCurrent->setRelation('condition', $caries);

    $adultInTreatment = new PatientToothCondition([
        'tooth_number' => '11',
        'treatment_status' => PatientToothCondition::STATUS_IN_TREATMENT,
    ]);
    $adultInTreatment->setRelation('condition', $implant);

    $childCompleted = new PatientToothCondition([
        'tooth_number' => '51',
        'treatment_status' => PatientToothCondition::STATUS_COMPLETED,
    ]);
    $childCompleted->setRelation('condition', $caries);

    $chart = app(ToothChartModalViewState::class)->build(new Collection([
        $adultCurrent,
        $adultInTreatment,
        $childCompleted,
    ]));

    $blade = File::get(resource_path('views/filament/components/tooth-chart-modal.blade.php'));
    $legendPartial = File::get(resource_path('views/filament/components/partials/tooth-chart-modal-legend.blade.php'));
    $rowsPartial = File::get(resource_path('views/filament/components/partials/tooth-chart-modal-rows.blade.php'));
    $summaryPartial = File::get(resource_path('views/filament/components/partials/tooth-chart-modal-summary.blade.php'));
    $toothConditionsRelationManager = File::get(base_path('app/Filament/Resources/Patients/RelationManagers/ToothConditionsRelationManager.php'));
    $diagnosisRelationManager = File::get(base_path('app/Filament/Resources/Patients/RelationManagers/DiagnosisAndTreatmentRelationManager.php'));

    $adultRow = collect($chart['rows'][0]['teeth'])->keyBy('number');
    $childRow = collect($chart['rows'][1]['teeth'])->keyBy('number');

    expect($blade)
        ->not->toContain('@php')
        ->toContain("@include('filament.components.partials.tooth-chart-modal-legend', ['legendItems' => \$chart['legend']])")
        ->toContain("@include('filament.components.partials.tooth-chart-modal-rows', ['rows' => \$chart['rows']])")
        ->toContain("@include('filament.components.partials.tooth-chart-modal-summary', ['chart' => \$chart])")
        ->and($legendPartial)->toContain("@props(['legendItems'])")
        ->and($legendPartial)->toContain('@foreach($legendItems as $legendItem)')
        ->and($rowsPartial)->toContain("@props(['rows'])")
        ->and($rowsPartial)->toContain('@foreach($rows as $row)')
        ->and($summaryPartial)->toContain("@props(['chart'])")
        ->and($summaryPartial)->toContain("@foreach(\$chart['summary'] as \$summaryItem)")
        ->and($toothConditionsRelationManager)->toContain('app(ToothChartModalViewState::class)->build(')
        ->and($diagnosisRelationManager)->toContain("'chart' => \$this->toothChartModalData(),")
        ->and($diagnosisRelationManager)->toContain('protected function toothChartModalData(): array')
        ->and($diagnosisRelationManager)->toContain('app(ToothChartModalViewState::class)->build(')
        ->and($chart['palette'])->toHaveCount(2)
        ->and($chart['legend'])->toHaveCount(3)
        ->and($chart['has_conditions'])->toBeTrue()
        ->and($chart['summary_heading'])->toBe('Tình trạng răng hiện tại')
        ->and($chart['footer_note'])->toBe('Click vào răng để xem chi tiết hoặc thêm tình trạng mới từ bảng bên trên.')
        ->and($adultRow['18']['button_classes'])->toContain('bg-gray-100 border-gray-400 text-gray-700')
        ->and($adultRow['18']['tooltip'])->toBe('Răng 18 - Sâu răng')
        ->and($adultRow['11']['button_classes'])->toContain('bg-red-100 border-red-500 text-red-700')
        ->and($childRow['51']['button_classes'])->toContain('bg-green-100 border-green-500 text-green-700')
        ->and($chart['summary'][0])->toHaveKeys(['name', 'tooth_numbers', 'condition_class']);
});
