<?php

use App\Models\Appointment;
use App\Models\TreatmentPlan;

it('creates treatment plans and appointments through factories without relying on a nullable faker property', function (): void {
    $plan = TreatmentPlan::factory()->create();
    $appointment = Appointment::factory()->create();

    expect($plan->id)->not->toBeNull()
        ->and($plan->title)->not->toBe('')
        ->and($appointment->id)->not->toBeNull()
        ->and($appointment->status)->not->toBe('');
});

it('does not keep legacy this-faker access in application factories', function (): void {
    $factoryFiles = collect(glob(database_path('factories/*.php')) ?: [])
        ->filter(fn (string $path): bool => ! str_ends_with($path, 'UserFactory.php'));

    expect($factoryFiles)->not->toBeEmpty();

    $factoryFiles->each(function (string $path): void {
        expect(file_get_contents($path))->not->toContain('$this->faker');
    });
});
