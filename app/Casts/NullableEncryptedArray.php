<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use JsonException;

class NullableEncryptedArray implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString((string) $value), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (DecryptException|JsonException $exception) {
            $fallback = json_decode((string) $value, true);

            if (is_array($fallback)) {
                Log::warning('Không thể giải mã dữ liệu ZNS array, fallback về JSON raw hiện hữu.', [
                    'model' => $model::class,
                    'model_id' => $model->getKey(),
                    'attribute' => $key,
                    'message' => $exception->getMessage(),
                ]);

                return $fallback;
            }

            Log::warning('Không thể giải mã dữ liệu ZNS array đã mã hóa, fallback về null.', [
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'attribute' => $key,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Crypt::encryptString($encoded);
    }
}
