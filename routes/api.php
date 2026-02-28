<?php

use App\Http\Controllers\Api\InternalEmrMutationController;
use App\Http\Controllers\Api\WebLeadController;
use App\Http\Controllers\Api\ZaloWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['web-lead.token', 'throttle:web-leads'])
    ->group(function (): void {
        Route::post('/web-leads', [WebLeadController::class, 'store'])
            ->name('api.v1.web-leads.store');
    });

Route::match(['get', 'post'], '/v1/integrations/zalo/webhook', ZaloWebhookController::class)
    ->name('api.v1.integrations.zalo.webhook');

Route::prefix('v1/emr/internal')
    ->middleware(['emr.internal.token'])
    ->group(function (): void {
        Route::post('/clinical-notes/{clinicalNote}/amend', [InternalEmrMutationController::class, 'amendClinicalNote'])
            ->name('api.v1.emr.internal.clinical-notes.amend');
    });
