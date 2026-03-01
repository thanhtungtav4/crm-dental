<?php

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\Consent;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\VisitEpisode;
use Illuminate\Support\Facades\DB;

it('encrypts phi fields at rest while keeping model read/write behavior', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'medical_history' => 'Tiểu đường type 2 và tăng huyết áp.',
    ]);

    PatientMedicalRecord::query()->create([
        'patient_id' => $patient->id,
        'additional_notes' => 'Dị ứng thuốc tê nhóm amid.',
    ]);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => '2026-03-11 09:00:00',
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-11',
        'examination_note' => 'Răng 26 đau khi gõ.',
        'general_exam_notes' => 'Niêm mạc bình thường.',
        'recommendation_notes' => 'Theo dõi thêm 48 giờ.',
        'treatment_plan_note' => 'Ưu tiên nội nha răng 26.',
        'other_diagnosis' => 'Nghi viêm tủy không hồi phục.',
    ]);

    $order = ClinicalOrder::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'branch_id' => $branch->id,
        'ordered_by' => $doctor->id,
        'order_type' => 'xray',
        'notes' => 'Chụp panorama kiểm tra quanh chóp.',
    ]);

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_PRELIMINARY,
        'interpretation' => 'Thấu quang quanh chóp răng 26.',
        'notes' => 'Đề nghị điều trị nội nha.',
    ]);

    $prescription = Prescription::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'visit_episode_id' => $encounter->id,
        'treatment_date' => '2026-03-11',
        'notes' => 'Uống sau ăn no.',
    ]);

    PrescriptionItem::query()->create([
        'prescription_id' => $prescription->id,
        'medication_name' => 'Paracetamol',
        'dosage' => '500mg',
        'quantity' => 10,
        'unit' => 'viên',
        'instructions' => 'Uống khi đau',
        'duration' => '5 ngày',
        'notes' => 'Tối đa 4 viên/ngày.',
    ]);

    Consent::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'consent_type' => 'treatment',
        'consent_version' => 'v1',
        'status' => Consent::STATUS_PENDING,
        'note' => 'Đã giải thích rủi ro điều trị.',
    ]);

    $rawPatient = DB::table('patients')->where('id', $patient->id)->value('medical_history');
    $rawRecord = DB::table('patient_medical_records')->where('patient_id', $patient->id)->value('additional_notes');
    $rawNote = DB::table('clinical_notes')->where('id', $note->id)->value('examination_note');
    $rawOrder = DB::table('clinical_orders')->where('id', $order->id)->value('notes');
    $rawResult = DB::table('clinical_results')->where('id', $result->id)->value('interpretation');
    $rawPrescription = DB::table('prescriptions')->where('id', $prescription->id)->value('notes');
    $rawPrescriptionItem = DB::table('prescription_items')->where('prescription_id', $prescription->id)->value('notes');
    $rawConsent = DB::table('consents')->where('patient_id', $patient->id)->value('note');

    expect((string) $rawPatient)->not->toBe('Tiểu đường type 2 và tăng huyết áp.')
        ->and((string) $rawRecord)->not->toBe('Dị ứng thuốc tê nhóm amid.')
        ->and((string) $rawNote)->not->toBe('Răng 26 đau khi gõ.')
        ->and((string) $rawOrder)->not->toBe('Chụp panorama kiểm tra quanh chóp.')
        ->and((string) $rawResult)->not->toBe('Thấu quang quanh chóp răng 26.')
        ->and((string) $rawPrescription)->not->toBe('Uống sau ăn no.')
        ->and((string) $rawPrescriptionItem)->not->toBe('Tối đa 4 viên/ngày.')
        ->and((string) $rawConsent)->not->toBe('Đã giải thích rủi ro điều trị.');

    expect($patient->fresh()->medical_history)->toBe('Tiểu đường type 2 và tăng huyết áp.')
        ->and(PatientMedicalRecord::query()->where('patient_id', $patient->id)->first()?->additional_notes)->toBe('Dị ứng thuốc tê nhóm amid.')
        ->and($note->fresh()->examination_note)->toBe('Răng 26 đau khi gõ.')
        ->and($order->fresh()->notes)->toBe('Chụp panorama kiểm tra quanh chóp.')
        ->and($result->fresh()->interpretation)->toBe('Thấu quang quanh chóp răng 26.')
        ->and($prescription->fresh()->notes)->toBe('Uống sau ăn no.')
        ->and(PrescriptionItem::query()->where('prescription_id', $prescription->id)->first()?->notes)->toBe('Tối đa 4 viên/ngày.')
        ->and(Consent::query()->where('patient_id', $patient->id)->first()?->note)->toBe('Đã giải thích rủi ro điều trị.');
});

it('treats legacy empty-string encrypted payloads as null instead of crashing', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    DB::table('clinical_notes')->insert([
        'patient_id' => $patient->id,
        'doctor_id' => null,
        'branch_id' => $branch->id,
        'date' => '2026-03-11',
        'examination_note' => '',
        'general_exam_notes' => '',
        'recommendation_notes' => null,
        'treatment_plan_note' => '',
        'indications' => json_encode([]),
        'diagnoses' => json_encode([]),
        'other_diagnosis' => '',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $note = ClinicalNote::query()->latest('id')->firstOrFail();

    expect($note->examination_note)->toBeNull()
        ->and($note->general_exam_notes)->toBeNull()
        ->and($note->treatment_plan_note)->toBeNull()
        ->and($note->other_diagnosis)->toBeNull();
});
