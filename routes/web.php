<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;

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
