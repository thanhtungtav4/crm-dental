<?php

use App\Models\Disease;
use App\Models\DiseaseGroup;
use App\Models\ToothCondition;
use App\Services\PatientExamReferenceReadModelService;

it('builds ordered tooth-condition payload and injects fallback KHAC option', function (): void {
    ToothCondition::query()->create([
        'code' => 'B02',
        'name' => '(B02) Bệnh lý',
        'category' => 'Bệnh lý',
        'sort_order' => 20,
        'color' => '#ef4444',
    ]);

    ToothCondition::query()->create([
        'code' => 'A01',
        'name' => 'Implant đã hoàn tất',
        'category' => 'Phục hình',
        'sort_order' => 10,
        'color' => '#22c55e',
    ]);

    $payload = app(PatientExamReferenceReadModelService::class)->toothConditionsPayload();

    expect($payload['conditions'])->toHaveCount(3)
        ->and($payload['conditionsJson'])->toHaveCount(3)
        ->and($payload['conditionOrder'])->toBe(['A01', 'B02', 'KHAC'])
        ->and($payload['conditionsJson'][0])->toMatchArray([
            'code' => 'A01',
            'display_code' => 'A01',
        ])
        ->and($payload['conditionsJson'][1])->toMatchArray([
            'code' => 'B02',
            'display_code' => 'B02',
        ])
        ->and($payload['conditionsJson'][2])->toMatchArray([
            'code' => 'KHAC',
            'display_code' => '*',
        ]);
});

it('returns only active diagnosis options ordered by group sort order then code', function (): void {
    $laterGroup = DiseaseGroup::query()->create([
        'name' => 'Nhóm B',
        'sort_order' => 20,
    ]);

    $earlierGroup = DiseaseGroup::query()->create([
        'name' => 'Nhóm A',
        'sort_order' => 10,
    ]);

    Disease::query()->create([
        'disease_group_id' => $laterGroup->id,
        'code' => 'B02',
        'name' => 'Viêm nha chu',
        'is_active' => true,
    ]);

    Disease::query()->create([
        'disease_group_id' => $earlierGroup->id,
        'code' => 'A02',
        'name' => 'Sai khớp cắn',
        'is_active' => true,
    ]);

    Disease::query()->create([
        'disease_group_id' => $earlierGroup->id,
        'code' => 'A01',
        'name' => 'Sâu răng',
        'is_active' => true,
    ]);

    Disease::query()->create([
        'disease_group_id' => $laterGroup->id,
        'code' => 'B99',
        'name' => 'Đã ngưng sử dụng',
        'is_active' => false,
    ]);

    $options = app(PatientExamReferenceReadModelService::class)->otherDiagnosisOptions();

    expect($options)->toBe([
        [
            'code' => 'A01',
            'label' => '(A01) Sâu răng',
            'group' => 'Nhóm A',
        ],
        [
            'code' => 'A02',
            'label' => '(A02) Sai khớp cắn',
            'group' => 'Nhóm A',
        ],
        [
            'code' => 'B02',
            'label' => '(B02) Viêm nha chu',
            'group' => 'Nhóm B',
        ],
    ]);
});
