<?php

use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PrescriptionController;
use Illuminate\Support\Facades\Route;

Route::passkeys();

Route::get('/', function () {
    return view('welcome');
});

// Serve favicon explicitly if the web server isn’t serving public assets.
Route::get('/favicon.ico', function () {
    $path = public_path('favicon.ico');
    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'image/x-icon',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/print/prescriptions/{prescription}', [PrescriptionController::class, 'print'])
        ->middleware('can:view,prescription')
        ->name('prescriptions.print');
    Route::get('/print/invoices/{invoice}', InvoicePrintController::class)
        ->middleware('can:view,invoice')
        ->name('invoices.print');
    Route::post('/print/invoices/{invoice}/export', [InvoicePrintController::class, 'markExported'])
        ->middleware('can:view,invoice')
        ->name('invoices.mark-exported');
    Route::get('/print/payments/{payment}', PaymentReceiptController::class)
        ->middleware('can:view,payment')
        ->name('payments.print');
});
