<?php

use Illuminate\Support\Facades\File;

it('uses info button color for customers list header create action', function (): void {
    $phpPath = app_path('Filament/Resources/Customers/Pages/ListCustomers.php');
    $php = File::get($phpPath);

    expect($php)->toContain('CreateAction::make()');
    expect($php)->toContain("->color('info')");
});

it('uses info button color for appointments list header actions', function (): void {
    $phpPath = app_path('Filament/Resources/Appointments/Pages/ListAppointments.php');
    $php = File::get($phpPath);

    expect($php)->toContain('CreateAction::make()');
    expect($php)->toContain("->color('info')");
    expect($php)->toContain("Action::make('calendar')");
});
