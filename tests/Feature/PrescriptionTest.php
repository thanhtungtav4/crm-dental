<?php

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->patient = Patient::factory()->create();
});

describe('Prescription Model', function () {

    it('can create a prescription with auto-generated code', function () {
        $prescription = Prescription::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
            'treatment_date' => now(),
        ]);

        expect($prescription->prescription_code)->toStartWith('DT');
        expect(strlen($prescription->prescription_code))->toBeGreaterThanOrEqual(10);
    });

    it('generates unique prescription codes', function () {
        $codes = [];

        for ($i = 0; $i < 5; $i++) {
            $prescription = Prescription::create([
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->user->id,
                'treatment_date' => now(),
            ]);
            $codes[] = $prescription->prescription_code;
        }

        expect(count(array_unique($codes)))->toBe(5);
    });

    it('belongs to a patient', function () {
        $prescription = Prescription::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
        ]);

        expect($prescription->patient)->toBeInstanceOf(Patient::class);
        expect($prescription->patient->id)->toBe($this->patient->id);
    });

    it('belongs to a doctor', function () {
        $prescription = Prescription::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
        ]);

        expect($prescription->doctor)->toBeInstanceOf(User::class);
        expect($prescription->doctor->id)->toBe($this->user->id);
    });

    it('has many prescription items', function () {
        $prescription = Prescription::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medication_name' => 'Amoxicillin',
            'dosage' => '500mg',
            'quantity' => 20,
            'unit' => 'viên',
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medication_name' => 'Paracetamol',
            'dosage' => '500mg',
            'quantity' => 10,
            'unit' => 'viên',
        ]);

        expect($prescription->items)->toHaveCount(2);
    });

    it('can be soft deleted', function () {
        $prescription = Prescription::factory()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
        ]);

        $prescription->delete();

        expect(Prescription::find($prescription->id))->toBeNull();
        expect(Prescription::withTrashed()->find($prescription->id))->not->toBeNull();
    });

    it('can scope by patient', function () {
        // Create prescriptions directly, not using factory to avoid code conflicts
        for ($i = 0; $i < 3; $i++) {
            Prescription::create([
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->user->id,
                'treatment_date' => now(),
            ]);
        }

        $anotherPatient = Patient::factory()->create();
        for ($i = 0; $i < 2; $i++) {
            Prescription::create([
                'patient_id' => $anotherPatient->id,
                'doctor_id' => $this->user->id,
                'treatment_date' => now(),
            ]);
        }

        expect(Prescription::forPatient($this->patient->id)->count())->toBe(3);
    });

    it('can scope by doctor', function () {
        // Create prescriptions directly
        for ($i = 0; $i < 2; $i++) {
            Prescription::create([
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->user->id,
                'treatment_date' => now(),
            ]);
        }

        $anotherDoctor = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Prescription::create([
                'patient_id' => $this->patient->id,
                'doctor_id' => $anotherDoctor->id,
                'treatment_date' => now(),
            ]);
        }

        expect(Prescription::byDoctor($this->user->id)->count())->toBe(2);
    });

    it('sets created_by automatically when authenticated', function () {
        $this->actingAs($this->user);

        $prescription = Prescription::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
            'treatment_date' => now(),
        ]);

        expect($prescription->created_by)->toBe($this->user->id);
    });
});

describe('Prescription Code Generation', function () {

    it('generates code with correct format', function () {
        $code = Prescription::generatePrescriptionCode();

        expect($code)->toMatch('/^DT\d{10}$/');
    });

    it('increments code number within same day', function () {
        $first = Prescription::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
            'treatment_date' => now(),
        ]);

        $second = Prescription::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->user->id,
            'treatment_date' => now(),
        ]);

        $firstNumber = (int) substr($first->prescription_code, -4);
        $secondNumber = (int) substr($second->prescription_code, -4);

        expect($secondNumber)->toBe($firstNumber + 1);
    });
});
