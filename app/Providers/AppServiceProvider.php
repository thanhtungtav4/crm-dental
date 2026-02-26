<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentSession;
use App\Observers\AppointmentObserver;
use App\Observers\NoteObserver;
use App\Observers\PaymentObserver;
use App\Observers\PlanItemObserver;
use App\Observers\PrescriptionObserver;
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
        Payment::observe(PaymentObserver::class);
        Prescription::observe(PrescriptionObserver::class);
        TreatmentSession::observe(TreatmentSessionObserver::class);
    }
}
