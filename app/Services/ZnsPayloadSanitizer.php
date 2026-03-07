<?php

namespace App\Services;

use App\Support\PatientIdentityNormalizer;

class ZnsPayloadSanitizer
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitizeProviderRequest(?array $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $templateData = data_get($payload, 'template_data');
        $keys = is_array($templateData)
            ? array_values(array_filter(array_map(
                static fn (mixed $key): ?string => is_string($key) && trim($key) !== '' ? trim($key) : null,
                array_keys($templateData),
            )))
            : [];

        sort($keys);

        return $this->filterNulls([
            'template_id' => $this->trimString(data_get($payload, 'template_id')),
            'tracking_id' => $this->trimString(data_get($payload, 'tracking_id')),
            'campaign_code' => $this->trimString(data_get($payload, 'campaign_code')),
            'phone_masked' => $this->maskPhone(data_get($payload, 'phone')),
            'phone_search_hash' => self::phoneSearchHash(data_get($payload, 'phone')),
            'template_data_keys' => $keys === [] ? null : $keys,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitizeProviderResponse(?array $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return $this->filterNulls([
            'error' => $this->trimString(data_get($payload, 'error')),
            'code' => $this->trimString(data_get($payload, 'code')),
            'status' => $this->trimString(data_get($payload, 'status')),
            'message' => $this->trimString(data_get($payload, 'message')),
            'error_name' => $this->trimString(data_get($payload, 'error_name')),
            'error_description' => $this->trimString(data_get($payload, 'error_description')),
            'provider_message_id' => $this->trimString(
                data_get($payload, 'data.msg_id')
                    ?? data_get($payload, 'data.message_id')
                    ?? data_get($payload, 'message_id')
            ),
        ]);
    }

    public static function phoneSearchHash(mixed $phone): ?string
    {
        $normalized = PatientIdentityNormalizer::normalizePhone(is_scalar($phone) ? (string) $phone : null);

        return $normalized === null
            ? null
            : hash('sha256', 'zns-phone|'.$normalized);
    }

    public function maskPhone(mixed $phone): ?string
    {
        $normalized = PatientIdentityNormalizer::normalizePhone(is_scalar($phone) ? (string) $phone : null);

        if ($normalized === null || $normalized === '') {
            return null;
        }

        $length = strlen($normalized);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 4)).substr($normalized, -4);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function filterNulls(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => ! ($value === null || $value === [] || $value === ''));
    }

    protected function trimString(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
