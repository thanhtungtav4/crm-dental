<?php

namespace App\Support;

use Illuminate\Support\Str;

class IdentitySearchHash
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

    public static function phone(string $namespace, ?string $phone): ?string
    {
        return static::hash($namespace.'-phone', static::normalizePhone($phone));
    }

    public static function email(string $namespace, ?string $email): ?string
    {
        return static::hash($namespace.'-email', static::normalizeEmail($email));
    }

    public static function value(string $namespace, ?string $normalizedValue): ?string
    {
        return static::hash($namespace, $normalizedValue);
    }

    protected static function hash(string $namespace, ?string $normalizedValue): ?string
    {
        return $normalizedValue === null
            ? null
            : hash('sha256', $namespace.'|'.$normalizedValue);
    }
}
