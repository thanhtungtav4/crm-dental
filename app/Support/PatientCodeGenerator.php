<?php

namespace App\Support;

use App\Models\Patient;
use Carbon\CarbonInterface;
use RuntimeException;

class PatientCodeGenerator
{
    public static function generate(?CarbonInterface $forDate = null): string
    {
        $date = ($forDate ?? now())->format('Ymd');
        $attempts = 0;

        do {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $code = "PAT-{$date}-{$suffix}";
            $exists = Patient::withTrashed()->where('patient_code', $code)->exists();
            $attempts++;
        } while ($exists && $attempts < 20);

        if ($exists) {
            throw new RuntimeException('Không thể sinh mã bệnh nhân duy nhất. Vui lòng thử lại.');
        }

        return $code;
    }

    public static function isStandard(?string $code): bool
    {
        if (! $code) {
            return false;
        }

        return (bool) preg_match('/^PAT-\d{8}-[A-Z0-9]{6}$/', $code);
    }
}
