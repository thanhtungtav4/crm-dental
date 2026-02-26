<?php

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\PlanItem;
use App\Models\RecallRule;
use App\Models\Service;
use App\Models\TreatmentPlan;

it('generates recall tickets from completed plan items and closes stale recall tickets', function () {
    $plan = TreatmentPlan::factory()->create([
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $service = Service::query()->create([
        'name' => 'Cạo vôi định kỳ',
        'default_price' => 250000,
    ]);

    RecallRule::query()->create([
        'branch_id' => $plan->branch_id,
        'service_id' => $service->id,
        'name' => 'Recall 30 ngày',
        'offset_days' => 30,
        'care_channel' => 'call',
        'priority' => 1,
        'is_active' => true,
    ]);

    $completedItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => $service->id,
        'name' => 'Cạo vôi răng',
        'status' => PlanItem::STATUS_COMPLETED,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'completed_at' => now()->subDay()->toDateString(),
    ]);

    $pendingItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Hạng mục chờ',
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    Note::query()->create([
        'patient_id' => $plan->patient_id,
        'customer_id' => $plan->patient?->customer_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'recall_recare',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'care_at' => now()->addDay(),
        'content' => 'Stale recall',
        'source_type' => PlanItem::class,
        'source_id' => $pendingItem->id,
    ]);

    $this->artisan('care:generate-recall-tickets', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $recallTicket = Note::query()
        ->where('source_type', PlanItem::class)
        ->where('source_id', $completedItem->id)
        ->where('care_type', 'recall_recare')
        ->first();

    expect($recallTicket)->not->toBeNull()
        ->and($recallTicket->care_channel)->toBe('call')
        ->and($recallTicket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED)
        ->and($recallTicket->content)->toContain('30 ngày');

    $staleTicket = Note::query()
        ->where('source_type', PlanItem::class)
        ->where('source_id', $pendingItem->id)
        ->where('care_type', 'recall_recare')
        ->first();

    expect($staleTicket)->not->toBeNull()
        ->and($staleTicket->care_status)->toBe(Note::CARE_STATUS_FAILED);
});

it('creates and resolves no-show recovery tickets', function () {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_NO_SHOW,
        'date' => now()->subHours(6),
    ]);

    $this->artisan('appointments:run-no-show-recovery', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $ticket = Note::query()
        ->where('source_type', Appointment::class)
        ->where('source_id', $appointment->id)
        ->where('care_type', 'no_show_recovery')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);

    $appointment->update([
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $this->artisan('appointments:run-no-show-recovery', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect($ticket->fresh()->care_status)->toBe(Note::CARE_STATUS_FAILED);
});

it('creates and resolves treatment plan follow-up tickets for unapproved plan items', function () {
    $plan = TreatmentPlan::factory()->create([
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $item = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Niềng răng mắc cài',
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    $item->forceFill([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ])->saveQuietly();

    $this->artisan('care:run-plan-follow-up', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $ticket = Note::query()
        ->where('source_type', PlanItem::class)
        ->where('source_id', $item->id)
        ->where('care_type', 'treatment_plan_follow_up')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);

    $item->update([
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    $this->artisan('care:run-plan-follow-up', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect($ticket->fresh()->care_status)->toBe(Note::CARE_STATUS_FAILED);
});

it('creates and resolves invoice aging reminders based on balance status', function () {
    $invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 900000,
        'paid_amount' => 0,
        'due_date' => now()->subDays(4)->toDateString(),
    ]);

    $this->artisan('finance:run-invoice-aging-reminders', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $ticket = Note::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('care_type', 'payment_reminder')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);

    $invoice->recordPayment(900000, 'cash');

    $this->artisan('finance:run-invoice-aging-reminders', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect($ticket->fresh()->care_status)->toBe(Note::CARE_STATUS_FAILED);
});
