<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\VisitEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('runs the core CRM lifecycle without breaking cross-module flows', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $lead = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'lead',
    ]);

    $firstAppointment = Appointment::create([
        'customer_id' => $lead->id,
        'patient_id' => null,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'appointment_type' => 'consultation',
        'appointment_kind' => 'booking',
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $firstAppointment->update([
        'status' => Appointment::STATUS_CONFIRMED,
    ]);
    $firstAppointment->update([
        'status' => Appointment::STATUS_IN_PROGRESS,
    ]);
    $firstAppointment->update([
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    $firstAppointment->refresh();
    $lead->refresh();

    /** @var Patient $patient */
    $patient = Patient::query()->findOrFail($firstAppointment->patient_id);

    expect($firstAppointment->patient_id)->not->toBeNull()
        ->and($patient->customer_id)->toBe($lead->id)
        ->and($lead->status)->toBe('converted');

    $episode = VisitEpisode::query()
        ->where('appointment_id', $firstAppointment->id)
        ->first();

    expect($episode)->not->toBeNull()
        ->and($episode->status)->toBe(VisitEpisode::STATUS_COMPLETED);

    $plan = TreatmentPlan::create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'title' => 'Core CRM flow treatment plan',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Điều trị tổng quát',
        'quantity' => 1,
        'price' => 1200000,
        'estimated_cost' => 1200000,
        'actual_cost' => 0,
        'required_visits' => 2,
        'completed_visits' => 0,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'patient_approved' => true,
    ]);

    $planItem->completeVisit();

    expect($planItem->fresh()->status)->toBe(PlanItem::STATUS_IN_PROGRESS)
        ->and($planItem->fresh()->progress_percentage)->toBe(50);

    $session = TreatmentSession::create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => now(),
        'procedure' => 'Hoàn thành giai đoạn 1',
        'status' => 'done',
        'notes' => 'Theo dõi sau điều trị',
    ]);

    $postTreatmentTicket = Note::query()
        ->where('source_type', TreatmentSession::class)
        ->where('source_id', $session->id)
        ->where('care_type', 'post_treatment_follow_up')
        ->first();

    expect($postTreatmentTicket)->not->toBeNull()
        ->and($postTreatmentTicket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);

    $invoice = Invoice::create([
        'treatment_session_id' => $session->id,
        'treatment_plan_id' => $plan->id,
        'patient_id' => $patient->id,
        'invoice_no' => 'INV-SMOKE-' . strtoupper(Str::random(6)),
        'subtotal' => 1200000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1200000,
        'status' => Invoice::STATUS_ISSUED,
        'issued_at' => now(),
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $invoice->recordPayment(
        amount: 300000,
        method: 'cash',
        notes: 'Thu đợt 1',
    );

    $invoice->refresh();

    expect((float) $invoice->paid_amount)->toBe(300000.0)
        ->and($invoice->status)->toBe(Invoice::STATUS_PARTIAL);

    $rescheduledAppointment = Appointment::create([
        'customer_id' => $lead->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDays(2),
        'appointment_type' => 'follow_up',
        'appointment_kind' => 're_exam',
        'duration_minutes' => 20,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $rescheduledAppointment->update([
        'status' => Appointment::STATUS_RESCHEDULED,
        'reschedule_reason' => 'Bệnh nhân dời lịch tái khám',
    ]);

    $appointmentReminderTicket = Note::query()
        ->where('source_type', Appointment::class)
        ->where('source_id', $rescheduledAppointment->id)
        ->where('care_type', 'appointment_reminder')
        ->first();

    expect($appointmentReminderTicket)->not->toBeNull()
        ->and($appointmentReminderTicket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);

    $declinedPlanItem = PlanItem::create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Hạng mục chưa chốt',
        'quantity' => 1,
        'price' => 800000,
        'estimated_cost' => 800000,
        'actual_cost' => 0,
        'required_visits' => 1,
        'completed_visits' => 0,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
        'patient_approved' => false,
    ]);

    $declinedPlanItem->update([
        'approval_status' => PlanItem::APPROVAL_DECLINED,
        'approval_decline_reason' => 'Bệnh nhân cần thời gian cân đối tài chính',
    ]);

    $treatmentFollowUpTicket = Note::query()
        ->where('source_type', PlanItem::class)
        ->where('source_id', $declinedPlanItem->id)
        ->where('care_type', 'treatment_plan_follow_up')
        ->first();

    expect($treatmentFollowUpTicket)->not->toBeNull()
        ->and($treatmentFollowUpTicket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);
});
