<?php

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientLoyalty;
use App\Models\PatientLoyaltyTransaction;
use App\Models\PatientRiskProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\PatientLoyaltyService;

it('runs loyalty program and applies revenue + referral rewards idempotently', function () {
    ClinicSetting::setValue('loyalty.points_per_ten_thousand_vnd', 2, [
        'group' => 'loyalty',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('loyalty.referral_bonus_referrer_points', 40, [
        'group' => 'loyalty',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('loyalty.referral_bonus_referee_points', 20, [
        'group' => 'loyalty',
        'value_type' => 'integer',
    ]);

    $referrerCustomer = Customer::factory()->create([
        'source' => 'walkin',
        'notes' => 'Khách cũ',
    ]);

    $referrer = Patient::factory()->create([
        'customer_id' => $referrerCustomer->id,
        'first_branch_id' => $referrerCustomer->branch_id,
        'phone' => '0901000001',
    ]);

    $loyaltyService = app(PatientLoyaltyService::class);
    $referrerLoyalty = $loyaltyService->ensureAccount($referrer);

    $refereeCustomer = Customer::factory()->create([
        'branch_id' => $referrerCustomer->branch_id,
        'source' => 'referral',
        'notes' => 'ref: '.$referrerLoyalty->referral_code,
    ]);

    $referee = Patient::factory()->create([
        'customer_id' => $refereeCustomer->id,
        'first_branch_id' => $refereeCustomer->branch_id,
        'phone' => '0901000002',
    ]);

    $referrerInvoice = Invoice::query()->create([
        'patient_id' => $referrer->id,
        'status' => Invoice::STATUS_ISSUED,
        'subtotal' => 500000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 500000,
        'paid_amount' => 0,
        'due_date' => now()->toDateString(),
    ]);

    $refereeInvoice = Invoice::query()->create([
        'patient_id' => $referee->id,
        'status' => Invoice::STATUS_ISSUED,
        'subtotal' => 1200000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1200000,
        'paid_amount' => 0,
        'due_date' => now()->toDateString(),
    ]);

    Payment::query()->create([
        'invoice_id' => $referrerInvoice->id,
        'amount' => 500000,
        'direction' => 'receipt',
        'method' => 'cash',
        'payment_source' => 'patient',
        'paid_at' => now(),
    ]);

    Payment::query()->create([
        'invoice_id' => $refereeInvoice->id,
        'amount' => 1200000,
        'direction' => 'receipt',
        'method' => 'transfer',
        'payment_source' => 'patient',
        'paid_at' => now(),
    ]);

    $this->artisan('growth:run-loyalty-program', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $referrerLoyalty = PatientLoyalty::query()->where('patient_id', $referrer->id)->firstOrFail();
    $refereeLoyalty = PatientLoyalty::query()->where('patient_id', $referee->id)->firstOrFail();

    expect((int) $referrerLoyalty->points_balance)->toBe(140)
        ->and((int) $refereeLoyalty->points_balance)->toBe(260)
        ->and((int) $refereeLoyalty->referred_by_patient_id)->toBe($referrer->id)
        ->and(PatientLoyaltyTransaction::query()->count())->toBe(4);

    $this->artisan('growth:run-loyalty-program', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect((int) $referrerLoyalty->fresh()->points_balance)->toBe(140)
        ->and((int) $refereeLoyalty->fresh()->points_balance)->toBe(260)
        ->and(PatientLoyaltyTransaction::query()->count())->toBe(4);
});

it('creates reactivation tickets and awards bonus after successful reactivation', function () {
    ClinicSetting::setValue('loyalty.reactivation_bonus_points', 77, [
        'group' => 'loyalty',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('loyalty.reactivation_inactive_days', 90, [
        'group' => 'loyalty',
        'value_type' => 'integer',
    ]);

    $customer = Customer::factory()->create();
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $customer->branch_id,
    ]);

    $patient->forceFill([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
    ])->saveQuietly();

    $this->artisan('growth:run-reactivation-flow', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $ticket = Note::query()
        ->where('patient_id', $patient->id)
        ->where('care_type', 'reactivation_follow_up')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);

    $ticket->update([
        'care_status' => Note::CARE_STATUS_DONE,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'branch_id' => $patient->first_branch_id,
        'status' => Appointment::STATUS_COMPLETED,
        'date' => now()->addHour(),
    ]);

    $this->artisan('growth:run-reactivation-flow', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $loyalty = PatientLoyalty::query()->where('patient_id', $patient->id)->firstOrFail();

    expect((int) $loyalty->points_balance)->toBe(77)
        ->and(PatientLoyaltyTransaction::query()
            ->where('patient_id', $patient->id)
            ->where('event_type', PatientLoyaltyTransaction::EVENT_REACTIVATION_BONUS)
            ->where('source_type', Note::class)
            ->where('source_id', $ticket->id)
            ->count())->toBe(1);

    $this->artisan('growth:run-reactivation-flow', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect(PatientLoyaltyTransaction::query()
        ->where('patient_id', $patient->id)
        ->where('event_type', PatientLoyaltyTransaction::EVENT_REACTIVATION_BONUS)
        ->count())->toBe(1);
});

it('scores patient risk and manages high risk intervention tickets', function () {
    ClinicSetting::setValue('risk.no_show_window_days', 90, [
        'group' => 'risk',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('risk.medium_threshold', 40, [
        'group' => 'risk',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('risk.high_threshold', 70, [
        'group' => 'risk',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('risk.auto_create_high_risk_ticket', true, [
        'group' => 'risk',
        'value_type' => 'boolean',
    ]);

    $customer = Customer::factory()->create();
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $customer->branch_id,
    ]);

    Appointment::factory()->count(4)->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'branch_id' => $patient->first_branch_id,
        'status' => Appointment::STATUS_NO_SHOW,
        'date' => now()->subDays(2),
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'branch_id' => $patient->first_branch_id,
        'status' => Appointment::STATUS_COMPLETED,
        'date' => now()->subDays(60),
    ]);

    $invoice = Invoice::query()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_OVERDUE,
        'subtotal' => 1500000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1500000,
        'paid_amount' => 100000,
        'due_date' => now()->subDays(10)->toDateString(),
    ]);

    Note::query()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'general_care',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'care_at' => now()->subDay(),
        'content' => 'Chăm sóc trước đó',
        'source_type' => 'patient_care',
        'source_id' => $patient->id,
    ]);

    $this->artisan('patients:score-risk', [
        '--date' => now()->toDateString(),
        '--patient_id' => $patient->id,
    ])->assertSuccessful();

    $profile = PatientRiskProfile::query()->where('patient_id', $patient->id)->firstOrFail();

    expect($profile->risk_level)->toBe(PatientRiskProfile::LEVEL_HIGH);

    $riskTicket = Note::query()
        ->where('patient_id', $patient->id)
        ->where('care_type', 'risk_high_follow_up')
        ->first();

    expect($riskTicket)->not->toBeNull()
        ->and($riskTicket->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED);

    Appointment::query()->where('patient_id', $patient->id)->update([
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'branch_id' => $patient->first_branch_id,
        'status' => Appointment::STATUS_COMPLETED,
        'date' => now()->subHour(),
    ]);

    $invoice->update([
        'status' => Invoice::STATUS_PAID,
        'paid_amount' => 1500000,
        'paid_at' => now(),
    ]);

    Note::query()
        ->where('patient_id', $patient->id)
        ->where('care_type', 'general_care')
        ->update([
            'care_status' => Note::CARE_STATUS_DONE,
        ]);

    $this->artisan('patients:score-risk', [
        '--date' => now()->toDateString(),
        '--patient_id' => $patient->id,
    ])->assertSuccessful();

    $profile = $profile->fresh();

    expect(in_array($profile->risk_level, [PatientRiskProfile::LEVEL_LOW, PatientRiskProfile::LEVEL_MEDIUM], true))->toBeTrue()
        ->and($riskTicket->fresh()->care_status)->toBe(Note::CARE_STATUS_FAILED);
});

it('renders risk scoring dashboard page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $customer = Customer::factory()->create();
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $customer->branch_id,
    ]);

    PatientRiskProfile::query()->create([
        'patient_id' => $patient->id,
        'as_of_date' => now()->toDateString(),
        'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
        'no_show_risk_score' => 82.5,
        'churn_risk_score' => 75.25,
        'risk_level' => PatientRiskProfile::LEVEL_HIGH,
        'recommended_action' => 'Ưu tiên gọi trong 24h.',
        'generated_at' => now(),
        'feature_payload' => ['source' => 'test'],
    ]);

    $this->actingAs($admin);

    $this->get(route('filament.admin.pages.risk-scoring-dashboard'))
        ->assertSuccessful()
        ->assertSee('Risk no-show/churn')
        ->assertSee($patient->full_name)
        ->assertSee('82.50');
});
