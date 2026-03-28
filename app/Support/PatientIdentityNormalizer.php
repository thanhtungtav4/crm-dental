<?php

namespace App\Support;

class PatientIdentityNormalizer
{
    public static function normalizePhone(?string $phone): ?string
    {
        return IdentitySearchHash::normalizePhone($phone);
    }

    public static function normalizeEmail(?string $email): ?string
    {
        return IdentitySearchHash::normalizeEmail($email);
    }

    public static function normalizeCccd(?string $cccd): ?string
    {
        if ($cccd === null || trim($cccd) === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', strtoupper(trim($cccd))) ?: '';

        return $normalized !== '' ? $normalized : null;
    }

    public static function customerPhoneSearchHash(?string $phone): ?string
    {
        return IdentitySearchHash::phone('customer', $phone);
    }

    public static function patientPhoneSearchHash(?string $phone): ?string
    {
        return IdentitySearchHash::phone('patient', $phone);
    }

    public static function customerEmailSearchHash(?string $email): ?string
    {
        return IdentitySearchHash::email('customer', $email);
    }

    public static function patientEmailSearchHash(?string $email): ?string
    {
        return IdentitySearchHash::email('patient', $email);
    }

    public static function identityHash(string $type, ?string $value): ?string
    {
        return IdentitySearchHash::value($type, $value);
    }
}
