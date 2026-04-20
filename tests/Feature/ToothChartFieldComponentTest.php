<?php

use Illuminate\Support\Facades\File;

it('moves tooth chart view preparation into a dedicated field component', function (): void {
    $php = File::get(app_path('Filament/Forms/Components/ToothChart.php'));
    $viewConfig = File::get(app_path('Support/ToothChartViewConfig.php'));

    expect($php)
        ->toContain('class ToothChart extends Field')
        ->toContain('use App\\Support\\ToothChartViewConfig;')
        ->toContain("protected string \$view = 'filament.forms.components.tooth-chart';")
        ->toContain('$viewConfig = app(ToothChartViewConfig::class);')
        ->toContain("'conditions' => \$conditions")
        ->toContain("'conditionsJson' => \$this->conditionJson(\$conditions)")
        ->toContain("'conditionOrder' => \$this->conditionOrder(\$conditions)")
        ->toContain("'dentitionOptions' => \$viewConfig->dentitionOptions()")
        ->toContain("'dentitionOptionActiveStyle' => \$viewConfig->dentitionOptionActiveStyle()")
        ->toContain("'dentitionOptionIdleStyle' => \$viewConfig->dentitionOptionIdleStyle()")
        ->toContain("'toothRows' => \$viewConfig->toothRows(self::ADULT_TEETH, self::CHILD_TEETH)")
        ->toContain("'treatmentLegend' => \$viewConfig->treatmentLegend()")
        ->toContain("'selectionHint' => \$viewConfig->selectionHint()")
        ->toContain("'toothDiagnosisStatePath' => \$toothDiagnosisStatePath")
        ->toContain("'dentitionModeStatePath' => str_replace('tooth_diagnosis_data', 'tooth_chart_dentition_mode', \$toothDiagnosisStatePath)")
        ->toContain("'defaultDentitionModeStatePath' => str_replace('tooth_diagnosis_data', 'tooth_chart_default_dentition_mode', \$toothDiagnosisStatePath)");

    expect($viewConfig)
        ->toContain('class ToothChartViewConfig')
        ->toContain('function dentitionOptions')
        ->toContain('function toothRows')
        ->toContain('function treatmentLegend')
        ->toContain('function selectionHint');
});

it('wires tooth chart forms through the dedicated field component', function (): void {
    $treatmentPlanForm = File::get(app_path('Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanForm.php'));
    $clinicalNotesRelationManager = File::get(app_path('Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php'));

    expect($treatmentPlanForm)
        ->toContain('use App\\Filament\\Forms\\Components\\ToothChart;')
        ->toContain("ToothChart::make('tooth_diagnosis_data')")
        ->not->toContain("->view('filament.forms.components.tooth-chart')");

    expect($clinicalNotesRelationManager)
        ->toContain('use App\\Filament\\Forms\\Components\\ToothChart;')
        ->toContain("ToothChart::make('tooth_diagnosis_data')")
        ->not->toContain("ViewField::make('tooth_diagnosis_data')")
        ->not->toContain("->view('filament.forms.components.tooth-chart')");
});
