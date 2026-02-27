<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ActionGate
{
    public static function authorize(string $permission, string $message): void
    {
        $user = auth()->user();

        if (! $user) {
            if (! app()->runningInConsole()) {
                throw ValidationException::withMessages([
                    'authorization' => $message,
                ]);
            }

            return;
        }

        if (! $user->can($permission)) {
            throw ValidationException::withMessages([
                'authorization' => $message,
            ]);
        }
    }
}
