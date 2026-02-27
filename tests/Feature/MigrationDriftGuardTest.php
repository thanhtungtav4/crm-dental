<?php

use Illuminate\Support\Facades\Artisan;

it('has no pending migrations after bootstrapping test schema', function () {
    Artisan::call('migrate:status');
    $output = Artisan::output();

    expect($output)->not->toContain('Pending');
});
