<?php

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\File;

it('eager loads hot-path appointment relations in the resource query', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $query = AppointmentResource::getEloquentQuery();
    $eagerLoads = array_keys($query->getEagerLoads());

    expect($eagerLoads)
        ->toContain('branch')
        ->toContain('customer')
        ->toContain('doctor')
        ->toContain('operationOverrideBy')
        ->toContain('overbookingOverrideBy')
        ->toContain('patient');
});

it('keeps status editing guarded in the appointment form and wires guided actions', function (): void {
    $formSchema = File::get(app_path('Filament/Resources/Appointments/Schemas/AppointmentForm.php'));
    $tableConfig = File::get(app_path('Filament/Resources/Appointments/Tables/AppointmentsTable.php'));
    $editPage = File::get(app_path('Filament/Resources/Appointments/Pages/EditAppointment.php'));

    expect($formSchema)
        ->toContain('->disabled(fn (?Model $record): bool => $record !== null)')
        ->toContain('->dehydrated(fn (?Model $record): bool => $record === null)')
        ->toContain('Đổi trạng thái bằng các action chuyên biệt để lưu đúng lý do và audit trail.')
        ->toContain('protected static function shouldShowRescheduleReason')
        ->and($tableConfig)->toContain('AppointmentStatusActions::confirm()')
        ->and($tableConfig)->toContain('AppointmentStatusActions::cancel()')
        ->and($editPage)->toContain('AppointmentStatusActions::confirm(fn () => $this->getRecord())')
        ->and($editPage)->toContain('AppointmentStatusActions::cancel(fn () => $this->getRecord())');
});
