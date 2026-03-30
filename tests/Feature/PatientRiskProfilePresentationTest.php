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
        ->and(PatientRiskProfile::levelColor(PatientRiskProfile::LEVEL_LOW))->toBe('success');
});
