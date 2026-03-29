<?php

use App\Services\PatientExamIndicationStateService;

it('normalizes selected indications and keeps keys unique', function (): void {
    $normalized = app(PatientExamIndicationStateService::class)->normalizeSelected([
        'Panorama',
        ' panorama ',
        '',
        null,
        'ext',
    ]);

    expect($normalized)->toBe([
        'panorama',
        'ext',
    ]);
});

it('normalizes indication images against the selected indication list', function (): void {
    $normalized = app(PatientExamIndicationStateService::class)->normalizeImages([
        'Panorama' => ['a.png', '', null],
        'int' => 'b.png',
        'xray' => ['c.png'],
    ], ['panorama', 'int']);

    expect($normalized)->toBe([
        'panorama' => ['a.png'],
        'int' => ['b.png'],
    ]);
});

it('toggles indication state and clears stale images and uploads when deselecting', function (): void {
    $service = app(PatientExamIndicationStateService::class);

    $selectedState = $service->toggle(
        indications: ['panorama'],
        indicationImages: ['panorama' => ['first.png']],
        tempUploads: ['panorama' => ['pending']],
        type: 'ext',
    );

    expect($selectedState['indications'])->toBe([
        'panorama',
        'ext',
    ]);

    $deselectedState = $service->toggle(
        indications: $selectedState['indications'],
        indicationImages: [
            'panorama' => ['first.png'],
            'ext' => ['second.png'],
        ],
        tempUploads: [
            'panorama' => ['pending'],
            'ext' => ['queued'],
        ],
        type: 'ext',
    );

    expect($deselectedState['indications'])->toBe(['panorama'])
        ->and($deselectedState['indicationImages'])->toBe([
            'panorama' => ['first.png'],
        ])
        ->and($deselectedState['tempUploads'])->toBe([
            'panorama' => ['pending'],
        ]);
});
