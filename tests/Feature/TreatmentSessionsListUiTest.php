<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\File;

it('groups treatment sessions by patient for easier operational scan', function (): void {
    $tableConfig = File::get(app_path('Filament/Resources/TreatmentSessions/Tables/TreatmentSessionsTable.php'));

    expect($tableConfig)
        ->toContain("Group::make('treatmentPlan.patient_id')")
        ->toContain("->label('Bệnh nhân')")
        ->toContain("->defaultGroup('treatmentPlan.patient_id')")
        ->toContain("TextColumn::make('treatmentPlan.patient.full_name')")
        ->toContain("TextColumn::make('treatmentPlan.title')");
});

it('renders treatment sessions list page after grouping by patient', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.treatment-sessions.index'))
        ->assertSuccessful()
        ->assertSee('Phiên điều trị');
});
