<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;

class ClinicSetting extends Model
{
    /**
     * Per-request cache to avoid repetitive DB lookups for runtime settings.
     *
     * @var array<string, mixed>
     */
    protected static array $runtimeCache = [];

    protected $fillable = [
        'group',
        'key',
        'label',
        'value',
        'value_type',
        'is_secret',
        'is_active',
        'sort_order',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('key');
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$runtimeCache)) {
            return static::$runtimeCache[$key];
        }

        try {
            $setting = static::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->first();
        } catch (QueryException $exception) {
            if (! static::isMissingTableException($exception)) {
                throw $exception;
            }

            static::$runtimeCache[$key] = $default;

            return $default;
        }

        if (! $setting) {
            static::$runtimeCache[$key] = $default;

            return $default;
        }

        $value = $setting->decodeValue($default);
        static::$runtimeCache[$key] = $value;

        return $value;
    }

    public static function setValue(string $key, mixed $value, array $meta = []): self
    {
        $record = static::query()->firstOrNew(['key' => $key]);

        $record->fill([
            'group' => $meta['group'] ?? $record->group ?? 'integration',
            'label' => $meta['label'] ?? $record->label ?? $key,
            'value_type' => $meta['value_type'] ?? $record->value_type ?? 'text',
            'is_secret' => (bool) ($meta['is_secret'] ?? $record->is_secret ?? false),
            'is_active' => (bool) ($meta['is_active'] ?? $record->is_active ?? true),
            'sort_order' => (int) ($meta['sort_order'] ?? $record->sort_order ?? 0),
            'description' => $meta['description'] ?? $record->description,
        ]);

        $record->value = $record->encodeValue($value);
        $record->save();
        static::$runtimeCache[$key] = $record->decodeValue();

        return $record;
    }

    protected function decodeValue(mixed $default = null): mixed
    {
        if ($this->value === null) {
            return $default;
        }

        $value = $this->value;

        if ($this->is_secret && filled($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                // Keep backward compatibility if old rows were stored as plain text.
            }
        }

        return match ($this->value_type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => is_numeric($value) ? (int) $value : 0,
            'json' => is_string($value) ? (json_decode($value, true) ?? []) : (array) $value,
            default => (string) $value,
        };
    }

    protected function encodeValue(mixed $value): ?string
    {
        $normalized = match ($this->value_type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'integer' => (string) ((int) $value),
            'json' => json_encode($value ?? [], JSON_UNESCAPED_UNICODE),
            default => filled($value) ? (string) $value : null,
        };

        if ($normalized === null || ! $this->is_secret) {
            return $normalized;
        }

        return Crypt::encryptString($normalized);
    }

    protected static function isMissingTableException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'no such table')
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'base table or view not found');
    }
}
