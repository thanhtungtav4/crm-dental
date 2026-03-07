<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class NullableEncryptedWithPlaintextFallback implements CastsAttributes
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
            return Crypt::decryptString((string) $value);
        } catch (DecryptException $exception) {
            Log::warning('Không thể giải mã dữ liệu ZNS string, fallback về plaintext hiện hữu.', [
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'attribute' => $key,
                'message' => $exception->getMessage(),
            ]);

            return (string) $value;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return '';
        }

        return Crypt::encryptString((string) $value);
    }
}
