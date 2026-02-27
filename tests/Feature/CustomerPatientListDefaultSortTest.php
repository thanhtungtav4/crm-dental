<?php

use Illuminate\Support\Facades\File;

it('sorts customers list by newest created_at first by default', function (): void {
    $content = File::get(app_path('Filament/Resources/Customers/Tables/CustomersTable.php'));

    expect($content)->toContain("->defaultSort('created_at', direction: 'desc')");
});

it('sorts patients list by newest created_at first by default', function (): void {
    $content = File::get(app_path('Filament/Resources/Patients/Tables/PatientsTable.php'));

    expect($content)->toContain("->defaultSort('created_at', direction: 'desc')");
});
