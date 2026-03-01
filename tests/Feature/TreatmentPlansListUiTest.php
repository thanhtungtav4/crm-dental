<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\File;

it('uses table-first layout with key columns for treatment plan list', function (): void {
    $tableConfig = File::get(app_path('Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php'));

    expect($tableConfig)
        ->toContain("->defaultSort('updated_at', 'desc')")
        ->toContain("TextColumn::make('title')")
        ->toContain("TextColumn::make('patient.full_name')")
        ->toContain("TextColumn::make('status')")
        ->toContain("TextColumn::make('priority')")
        ->toContain("TextColumn::make('progress_percentage')")
        ->toContain("TextColumn::make('visit_summary')")
        ->toContain("TextColumn::make('total_cost')")
        ->toContain("TextColumn::make('expected_end_date')")
        ->toContain("SelectFilter::make('doctor_id')")
        ->toContain("Action::make('open_patient_profile')")
        ->toContain("'tab' => 'exam-treatment'")
        ->not->toContain('->contentGrid([');
});

it('renders treatment plan list page after ui refactor', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.treatment-plans.index'))
        ->assertSuccessful()
        ->assertSee('Kế hoạch điều trị');
});
