<?php

namespace App\Support;

use App\Services\ActionPermissionBaselineService;
use App\Services\AutomationActorResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class ActionGate
{
    public static function authorize(string $permission, string $message): void
    {
        self::ensureActionPermissionBaseline($permission);

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
                ? ' Thiếu scheduler actor hợp lệ. Kiểm tra scheduler.automation_actor_user_id và role/permission của service account.'
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

    protected static function ensureActionPermissionBaseline(string $permission): void
    {
        if (! str_starts_with($permission, 'Action:')) {
            return;
        }

        $guard = (string) config('auth.defaults.guard', 'web');

        $exists = Permission::query()
            ->where('name', $permission)
            ->where('guard_name', $guard)
            ->exists();

        if ($exists) {
            return;
        }

        app(ActionPermissionBaselineService::class)->sync();
    }

    protected static function resolveConsoleActor(string $permission): ?Authenticatable
    {
        return app(AutomationActorResolver::class)->resolveForPermission(
            permission: $permission,
            enforceRequiredRole: true,
        );
    }
}
