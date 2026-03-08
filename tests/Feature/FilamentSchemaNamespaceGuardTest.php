<?php

it('does not use legacy filament namespaces in filament schemas', function (): void {
    $forbiddenPatterns = [
        'Filament\\Forms\\Components\\Section',
        'Filament\\Forms\\Components\\Grid',
        'Filament\\Forms\\Components\\Fieldset',
        'Filament\\Forms\\Get',
        'Filament\\Forms\\Set',
        'Filament\\Forms\\Components\\Utilities\\Get',
        'Filament\\Forms\\Components\\Utilities\\Set',
        'Filament\\Infolists\\Components\\Section',
        'Filament\\Infolists\\Components\\Grid',
        'Filament\\Infolists\\Components\\Fieldset',
        'Forms\\Components\\Section::',
        'Forms\\Components\\Grid::',
        'Forms\\Components\\Fieldset::',
        'Infolists\\Components\\Section::',
        'Infolists\\Components\\Grid::',
        'Infolists\\Components\\Fieldset::',
    ];

    $violations = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator(app_path('Filament'))
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $contents = file_get_contents($path);

        if ($contents === false) {
            continue;
        }

        foreach ($forbiddenPatterns as $pattern) {
            if (str_contains($contents, $pattern)) {
                $violations[] = [
                    'file' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
                    'pattern' => $pattern,
                ];
            }
        }
    }

    expect($violations)->toBeEmpty();
});
