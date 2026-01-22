<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Appointment;
use App\Observers\AppointmentObserver;

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
    }
}
