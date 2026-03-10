<?php

use Illuminate\Support\Facades\File;

it('uses dark-mode friendly states in the tooth selector component', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-selector.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('class="tooth-selector text-gray-700 dark:text-gray-200"')
        ->and($blade)->toContain('dark:bg-primary-500/15 dark:text-primary-100')
        ->and($blade)->toContain('dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300 dark:hover:border-gray-500')
        ->and($blade)->toContain('dark:border-gray-600')
        ->and($blade)->toContain('dark:text-gray-300');
});
