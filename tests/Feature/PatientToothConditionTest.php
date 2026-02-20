<?php

use App\Models\Patient;
use App\Models\PatientToothCondition;
use App\Models\ToothCondition;
use App\Models\TreatmentPlan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->patient = Patient::factory()->create();

    // Seed tooth conditions
    $this->toothCondition = ToothCondition::firstOrCreate(
        ['code' => 'K02'],
        [
            'name' => '(K02) Sâu răng',
            'category' => 'diagnosis',
            'color' => '#ef4444',
        ]
    );
});

describe('PatientToothCondition Model', function () {

    it('can create a tooth condition for a patient', function () {
        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '11',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_CURRENT,
        ]);

        expect($condition->exists)->toBeTrue();
        expect($condition->tooth_number)->toBe('11');
        expect($condition->treatment_status)->toBe('current');
    });

    it('sets diagnosed_at automatically', function () {
        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '21',
            'tooth_condition_id' => $this->toothCondition->id,
        ]);

        expect($condition->diagnosed_at)->not->toBeNull();
        expect($condition->diagnosed_at->isToday())->toBeTrue();
    });

    it('sets diagnosed_by when authenticated', function () {
        $this->actingAs($this->user);

        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '22',
            'tooth_condition_id' => $this->toothCondition->id,
        ]);

        expect($condition->diagnosed_by)->toBe($this->user->id);
    });

    it('belongs to a patient', function () {
        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '23',
            'tooth_condition_id' => $this->toothCondition->id,
        ]);

        expect($condition->patient)->toBeInstanceOf(Patient::class);
        expect($condition->patient->id)->toBe($this->patient->id);
    });

    it('belongs to a tooth condition', function () {
        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '24',
            'tooth_condition_id' => $this->toothCondition->id,
        ]);

        expect($condition->condition)->toBeInstanceOf(ToothCondition::class);
        expect($condition->condition->code)->toBe('K02');
    });

    it('can start treatment', function () {
        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '25',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_CURRENT,
        ]);

        $condition->startTreatment();

        expect($condition->fresh()->treatment_status)->toBe(PatientToothCondition::STATUS_IN_TREATMENT);
    });

    it('can complete treatment', function () {
        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '26',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_IN_TREATMENT,
        ]);

        $condition->completeTreatment();

        expect($condition->fresh()->treatment_status)->toBe(PatientToothCondition::STATUS_COMPLETED);
        expect($condition->fresh()->completed_at)->not->toBeNull();
    });

    it('returns correct status color', function () {
        $current = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '27',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_CURRENT,
        ]);

        $inTreatment = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '28',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_IN_TREATMENT,
        ]);

        $completed = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '18',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_COMPLETED,
        ]);

        expect($current->status_color)->toBe('#6B7280'); // Gray
        expect($inTreatment->status_color)->toBe('#EF4444'); // Red
        expect($completed->status_color)->toBe('#10B981'); // Green
    });

    it('can scope by treatment status', function () {
        PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '31',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_CURRENT,
        ]);

        PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '32',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_IN_TREATMENT,
        ]);

        PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '33',
            'tooth_condition_id' => $this->toothCondition->id,
            'treatment_status' => PatientToothCondition::STATUS_COMPLETED,
        ]);

        expect(PatientToothCondition::byStatus('current')->count())->toBe(1);
        expect(PatientToothCondition::byStatus('in_treatment')->count())->toBe(1);
        expect(PatientToothCondition::active()->count())->toBe(2);
    });

    it('can be soft deleted', function () {
        $condition = PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '34',
            'tooth_condition_id' => $this->toothCondition->id,
        ]);

        $condition->delete();

        expect(PatientToothCondition::find($condition->id))->toBeNull();
        expect(PatientToothCondition::withTrashed()->find($condition->id))->not->toBeNull();
    });

    it('enforces unique constraint on patient + tooth + condition', function () {
        PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '35',
            'tooth_condition_id' => $this->toothCondition->id,
        ]);

        expect(fn() => PatientToothCondition::create([
            'patient_id' => $this->patient->id,
            'tooth_number' => '35',
            'tooth_condition_id' => $this->toothCondition->id,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});

describe('PatientToothCondition Static Helpers', function () {

    it('returns adult upper teeth', function () {
        $teeth = PatientToothCondition::getAdultTeethUpper();

        expect($teeth)->toBeArray();
        expect($teeth)->toHaveCount(16);
        expect($teeth[0])->toBe('18');
        expect($teeth[15])->toBe('28');
    });

    it('returns adult lower teeth', function () {
        $teeth = PatientToothCondition::getAdultTeethLower();

        expect($teeth)->toBeArray();
        expect($teeth)->toHaveCount(16);
        expect($teeth[0])->toBe('48');
        expect($teeth[15])->toBe('38');
    });

    it('returns child upper teeth', function () {
        $teeth = PatientToothCondition::getChildTeethUpper();

        expect($teeth)->toBeArray();
        expect($teeth)->toHaveCount(10);
        expect($teeth[0])->toBe('55');
        expect($teeth[9])->toBe('65');
    });

    it('returns child lower teeth', function () {
        $teeth = PatientToothCondition::getChildTeethLower();

        expect($teeth)->toBeArray();
        expect($teeth)->toHaveCount(10);
        expect($teeth[0])->toBe('85');
        expect($teeth[9])->toBe('75');
    });

    it('returns all teeth combined', function () {
        $allTeeth = PatientToothCondition::getAllTeethNumbers();

        expect($allTeeth)->toBeArray();
        expect($allTeeth)->toHaveCount(52); // 16 + 10 + 10 + 16
    });

    it('returns status options in Vietnamese', function () {
        $options = PatientToothCondition::getStatusOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveKey('current');
        expect($options)->toHaveKey('in_treatment');
        expect($options)->toHaveKey('completed');
        expect($options['current'])->toBe('Tình trạng hiện tại');
    });
});
