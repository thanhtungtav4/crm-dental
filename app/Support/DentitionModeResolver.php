<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DentitionModeResolver
{
    public const MODE_ADULT = 'adult';

    public const MODE_CHILD = 'child';

    public const MODE_AUTO = 'auto';

    public const CHILD_MAX_AGE = 12;

    public static function resolveFromBirthday(?CarbonInterface $birthday, ?CarbonInterface $asOf = null): string
    {
        if ($birthday === null) {
            return self::MODE_ADULT;
        }

        $referenceDate = $asOf ?? now();
        $age = $birthday->diffInYears($referenceDate);

        if ($age <= self::CHILD_MAX_AGE) {
            return self::MODE_CHILD;
        }

        return self::MODE_ADULT;
    }

    public static function normalize(?string $mode): string
    {
        return match ($mode) {
            self::MODE_ADULT, self::MODE_CHILD, self::MODE_AUTO => $mode,
            default => self::MODE_AUTO,
        };
    }
}
