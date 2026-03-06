<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('encrypts sensitive patient medical record contact and insurance fields at rest', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $record = PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'insurance_provider' => 'Bao Viet',
        'insurance_number' => 'BHYT-123456',
        'emergency_contact_name' => 'Nguyen Thi Nguoi Than',
        'emergency_contact_phone' => '0901234567',
        'emergency_contact_email' => 'nguoi-than@example.test',
        'emergency_contact_relationship' => 'Vo',
        'updated_by' => $doctor->id,
    ])->fresh();

    expect($record?->insurance_provider)->toBe('Bao Viet')
        ->and($record?->insurance_number)->toBe('BHYT-123456')
        ->and($record?->emergency_contact_name)->toBe('Nguyen Thi Nguoi Than')
        ->and($record?->emergency_contact_phone)->toBe('0901234567')
        ->and($record?->emergency_contact_email)->toBe('nguoi-than@example.test')
        ->and($record?->emergency_contact_relationship)->toBe('Vo')
        ->and($record?->hasInsuranceInformation())->toBeTrue()
        ->and($record?->hasEmergencyContact())->toBeTrue();

    $raw = DB::table('patient_medical_records')
        ->where('id', $record?->id)
        ->first([
            'insurance_provider',
            'insurance_number',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_email',
            'emergency_contact_relationship',
        ]);

    expect((string) ($raw?->insurance_provider ?? ''))->not->toBe('Bao Viet')
        ->and((string) ($raw?->insurance_number ?? ''))->not->toBe('BHYT-123456')
        ->and((string) ($raw?->emergency_contact_name ?? ''))->not->toBe('Nguyen Thi Nguoi Than')
        ->and((string) ($raw?->emergency_contact_phone ?? ''))->not->toBe('0901234567')
        ->and((string) ($raw?->emergency_contact_email ?? ''))->not->toBe('nguoi-than@example.test')
        ->and((string) ($raw?->emergency_contact_relationship ?? ''))->not->toBe('Vo');
});
