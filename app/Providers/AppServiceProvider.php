<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Consent;
use App\Models\InsuranceClaim;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Observers\AppointmentObserver;
use App\Observers\ConsentObserver;
use App\Observers\InsuranceClaimObserver;
use App\Observers\NoteObserver;
use App\Observers\PatientMedicalRecordObserver;
use App\Observers\PatientObserver;
use App\Observers\PaymentObserver;
use App\Observers\PlanItemObserver;
use App\Observers\PrescriptionObserver;
use App\Observers\TreatmentPlanObserver;
use App\Observers\TreatmentSessionAuditObserver;
use App\Observers\TreatmentSessionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Appointment Observer
        Appointment::observe(AppointmentObserver::class);
        Note::observe(NoteObserver::class);
        PlanItem::observe(PlanItemObserver::class);
        Patient::observe(PatientObserver::class);
        PatientMedicalRecord::observe(PatientMedicalRecordObserver::class);
        Payment::observe(PaymentObserver::class);
        Prescription::observe(PrescriptionObserver::class);
        TreatmentPlan::observe(TreatmentPlanObserver::class);
        TreatmentSession::observe(TreatmentSessionObserver::class);
        TreatmentSession::observe(TreatmentSessionAuditObserver::class);
        Consent::observe(ConsentObserver::class);
        InsuranceClaim::observe(InsuranceClaimObserver::class);
    }
}
