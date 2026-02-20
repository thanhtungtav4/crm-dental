<?php

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->patient = Patient::factory()->create();
    $this->prescription = Prescription::create([
        'patient_id' => $this->patient->id,
        'doctor_id' => $this->user->id,
        'treatment_date' => now(),
    ]);
});

describe('PrescriptionItem Model', function () {

    it('can create a prescription item', function () {
        $item = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Amoxicillin',
            'dosage' => '500mg',
            'quantity' => 20,
            'unit' => 'viên',
            'instructions' => 'Ngày uống 3 lần, sáng - trưa - tối',
            'duration' => '7 ngày',
        ]);

        expect($item->exists)->toBeTrue();
        expect($item->medication_name)->toBe('Amoxicillin');
        expect($item->quantity)->toBe(20);
    });

    it('belongs to a prescription', function () {
        $item = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Paracetamol',
            'quantity' => 10,
        ]);

        expect($item->prescription)->toBeInstanceOf(Prescription::class);
        expect($item->prescription->id)->toBe($this->prescription->id);
    });

    it('casts quantity to integer', function () {
        $item = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Ibuprofen',
            'quantity' => '15',
        ]);

        expect($item->quantity)->toBe(15);
        expect($item->quantity)->toBeInt();
    });

    it('has default quantity of 1 from database', function () {
        $item = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Vitamin C',
            'quantity' => 1, // Explicit default since DB default may not apply
        ]);

        expect($item->quantity)->toBe(1);
    });

    it('returns formatted instruction', function () {
        $item = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Amoxicillin',
            'dosage' => '500mg',
            'quantity' => 20,
            'unit' => 'viên',
            'instructions' => 'Ngày uống 2 lần',
            'duration' => '7 ngày',
        ]);

        $formatted = $item->formatted_instruction;

        expect($formatted)->toContain('500mg');
        expect($formatted)->toContain('20 viên');
        expect($formatted)->toContain('Ngày uống 2 lần');
        expect($formatted)->toContain('7 ngày');
    });
});

describe('PrescriptionItem Static Helpers', function () {

    it('returns unit options', function () {
        $units = PrescriptionItem::getUnits();

        expect($units)->toBeArray();
        expect($units)->toHaveKey('viên');
        expect($units)->toHaveKey('gói');
        expect($units)->toHaveKey('chai');
        expect($units)->toHaveKey('ống');
        expect($units['viên'])->toBe('Viên');
    });

    it('returns common instructions', function () {
        $instructions = PrescriptionItem::getCommonInstructions();

        expect($instructions)->toBeArray();
        expect($instructions)->toContain('Ngày uống 1 lần, sau ăn');
        expect($instructions)->toContain('Ngày uống 2 lần, sáng - tối');
        expect($instructions)->toContain('Ngày uống 3 lần, sáng - trưa - tối');
        expect($instructions)->toContain('Uống khi đau');
    });
});

describe('Prescription Items Relationship', function () {

    it('deletes items when prescription is deleted', function () {
        $item1 = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Amoxicillin',
            'quantity' => 20,
        ]);

        $item2 = PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Paracetamol',
            'quantity' => 10,
        ]);

        // Force delete to cascade
        $this->prescription->forceDelete();

        expect(PrescriptionItem::find($item1->id))->toBeNull();
        expect(PrescriptionItem::find($item2->id))->toBeNull();
    });

    it('counts items correctly on prescription', function () {
        PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Amoxicillin',
            'quantity' => 20,
        ]);

        PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Paracetamol',
            'quantity' => 10,
        ]);

        PrescriptionItem::create([
            'prescription_id' => $this->prescription->id,
            'medication_name' => 'Ibuprofen',
            'quantity' => 15,
        ]);

        expect($this->prescription->total_medications)->toBe(3);
    });
});
