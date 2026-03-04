<?php

use Illuminate\Foundation\Http\Kernel;
use SolutionForest\FilamentFirewall\Middleware\WhitelistRangeMiddleware;

it('registers firewall whitelist middleware globally', function () {
    $middleware = app(Kernel::class)->getGlobalMiddleware();

    expect($middleware)->toContain(WhitelistRangeMiddleware::class);
});

it('registers firewall resource routes in admin panel', function () {
    expect(route('filament.admin.resources.firewall-ips.index', absolute: false))
        ->toBe('/admin/firewall-ips');
});
