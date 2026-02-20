<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\TreatmentSession;
use App\Observers\AppointmentObserver;
use App\Observers\PrescriptionObserver;
use App\Observers\TreatmentSessionObserver;

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
        Prescription::observe(PrescriptionObserver::class);
        TreatmentSession::observe(TreatmentSessionObserver::class);
    }
}
