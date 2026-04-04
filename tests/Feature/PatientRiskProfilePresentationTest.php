<?php

use App\Models\PatientRiskProfile;

it('provides shared presentation metadata for patient risk levels', function (): void {
    expect(PatientRiskProfile::levelOptions())->toBe([
        PatientRiskProfile::LEVEL_LOW => 'Thấp',
        PatientRiskProfile::LEVEL_MEDIUM => 'Trung bình',
        PatientRiskProfile::LEVEL_HIGH => 'Cao',
    ])->and(PatientRiskProfile::levelLabel(PatientRiskProfile::LEVEL_HIGH))->toBe('Cao')
        ->and(PatientRiskProfile::levelLabel(PatientRiskProfile::LEVEL_MEDIUM))->toBe('Trung bình')
        ->and(PatientRiskProfile::levelLabel(PatientRiskProfile::LEVEL_LOW))->toBe('Thấp')
        ->and(PatientRiskProfile::levelLabel('unknown'))->toBe('Không xác định')
        ->and(PatientRiskProfile::levelColor(PatientRiskProfile::LEVEL_HIGH))->toBe('danger')
        ->and(PatientRiskProfile::levelColor(PatientRiskProfile::LEVEL_MEDIUM))->toBe('warning')
        ->and(PatientRiskProfile::levelColor(PatientRiskProfile::LEVEL_LOW))->toBe('success')
        ->and(PatientRiskProfile::levelBadgePayload(PatientRiskProfile::LEVEL_HIGH))->toBe([
            'label' => 'Cao',
            'color' => 'danger',
        ])
        ->and(PatientRiskProfile::formatScore(12.345))->toBe('12.35')
        ->and(PatientRiskProfile::formatCount(12345))->toBe('12.345')
        ->and(PatientRiskProfile::summaryStatsPayload([
            'total' => 12,
            'high' => 3,
            'medium' => 4,
            'low' => 5,
            'average_no_show' => 61.234,
            'average_churn' => 44.2,
            'active_intervention_tickets' => 2,
        ]))->toBe([
            ['label' => 'Tổng profile', 'value' => '12'],
            ['label' => 'Risk cao', 'value' => '3'],
            ['label' => 'Risk trung bình', 'value' => '4'],
            ['label' => 'Risk thấp', 'value' => '5'],
            ['label' => 'Avg no-show risk', 'value' => '61.23'],
            ['label' => 'Avg churn risk', 'value' => '44.20'],
            ['label' => 'Ticket can thiệp đang mở', 'value' => '2'],
        ]);
});
