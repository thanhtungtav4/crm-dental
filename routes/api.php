<?php

use App\Http\Controllers\Api\WebLeadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['web-lead.token', 'throttle:web-leads'])
    ->group(function (): void {
        Route::post('/web-leads', [WebLeadController::class, 'store'])
            ->name('api.v1.web-leads.store');
    });
