<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class ActionGate
{
    public static function authorize(string $permission, string $message): void
    {
        $user = auth()->user();

        if (! $user && app()->runningInConsole()) {
            if (app()->runningUnitTests()) {
                return;
            }

            $user = self::resolveConsoleActor($permission);

            if ($user !== null) {
                auth()->setUser($user);
            }
        }

        if (! $user) {
            $details = app()->runningInConsole()
                ? ' Thiếu user phiên console hợp lệ. Cấu hình CARE_AUTOMATION_ACTOR_USER_ID với tài khoản có quyền tương ứng.'
                : '';

            throw ValidationException::withMessages([
                'authorization' => $message.$details,
            ]);
        }

        if (! $user->can($permission)) {
            throw ValidationException::withMessages([
                'authorization' => $message,
            ]);
        }
    }

    protected static function resolveConsoleActor(string $permission): ?Authenticatable
    {
        $actorId = config('care.automation_actor_user_id');

        if (! filled($actorId)) {
            return null;
        }

        $actor = User::query()->find((int) $actorId);

        if (! $actor) {
            return null;
        }

        return $actor->can($permission) ? $actor : null;
    }
}
