<?php

namespace App\Support;

use Illuminate\Support\Str;

class PatientIdentityNormalizer
{
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '84')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized !== '' ? $normalized : null;
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
        return static::hash('customer-phone', static::normalizePhone($phone));
    }

    public static function patientPhoneSearchHash(?string $phone): ?string
    {
        return static::hash('patient-phone', static::normalizePhone($phone));
    }

    public static function customerEmailSearchHash(?string $email): ?string
    {
        return static::hash('customer-email', static::normalizeEmail($email));
    }

    public static function patientEmailSearchHash(?string $email): ?string
    {
        return static::hash('patient-email', static::normalizeEmail($email));
    }

    public static function identityHash(string $type, ?string $value): ?string
    {
        return static::hash($type, $value);
    }

    protected static function hash(string $prefix, ?string $normalizedValue): ?string
    {
        return $normalizedValue === null
            ? null
            : hash('sha256', $prefix.'|'.$normalizedValue);
    }
}
