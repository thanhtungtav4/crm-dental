<?php

use App\Http\Controllers\Api\InternalEmrMutationController;
use App\Http\Controllers\Api\V1\MobileAppointmentController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileInvoiceSummaryController;
use App\Http\Controllers\Api\V1\MobilePatientSummaryController;
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

Route::prefix('v1/mobile')
    ->group(function (): void {
        Route::post('/auth/token', [MobileAuthController::class, 'store'])
            ->middleware('throttle:api-mobile')
            ->name('api.v1.mobile.auth.token');

        Route::middleware(['auth:sanctum', 'ability:mobile:read', 'throttle:api-mobile'])
            ->group(function (): void {
                Route::delete('/auth/token', [MobileAuthController::class, 'destroy'])
                    ->name('api.v1.mobile.auth.logout');
                Route::get('/appointments', [MobileAppointmentController::class, 'index'])
                    ->name('api.v1.mobile.appointments.index');
                Route::get('/patients/{patient}', [MobilePatientSummaryController::class, 'show'])
                    ->name('api.v1.mobile.patients.show');
                Route::get('/invoices', [MobileInvoiceSummaryController::class, 'index'])
                    ->name('api.v1.mobile.invoices.index');
                Route::get('/openapi', function () {
                    return response()->json([
                        'openapi' => '3.0.3',
                        'info' => [
                            'title' => 'Dental CRM Mobile API',
                            'version' => '1.0.0',
                        ],
                        'paths' => [
                            '/api/v1/mobile/auth/token' => ['post' => ['summary' => 'Login and issue token']],
                            '/api/v1/mobile/appointments' => ['get' => ['summary' => 'List appointments']],
                            '/api/v1/mobile/patients/{patient}' => ['get' => ['summary' => 'Patient summary']],
                            '/api/v1/mobile/invoices' => ['get' => ['summary' => 'List invoice summaries']],
                        ],
                    ]);
                })->name('api.v1.mobile.openapi');
            });
    });
