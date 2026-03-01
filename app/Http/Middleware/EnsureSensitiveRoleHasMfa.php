<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSensitiveRoleHasMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests() && ! (bool) config('care.security_enforce_in_tests', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $this->requiresMfa($user)) {
            return $next($request);
        }

        if ($this->isMfaSetupRoute($request)) {
            return $next($request);
        }

        if ($this->hasAnyMfaMethod($user)) {
            return $next($request);
        }

        $this->recordBlockAudit($user, $request);

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort(403, 'Tài khoản yêu cầu bật MFA trước khi thao tác dữ liệu nhạy cảm.');
        }

        return redirect()->route('filament.admin.pages.my-profile', [
            'mfa_required' => 1,
        ]);
    }

    protected function requiresMfa(User $user): bool
    {
        $requiredRoles = ClinicRuntimeSettings::securityMfaRequiredRoles();

        if ($requiredRoles === []) {
            return false;
        }

        return $user->hasAnyRole($requiredRoles);
    }

    protected function hasAnyMfaMethod(User $user): bool
    {
        if ($user->two_factor_confirmed_at !== null) {
            return true;
        }

        return $user->passkeys()->exists();
    }

    protected function isMfaSetupRoute(Request $request): bool
    {
        $routeName = (string) optional($request->route())->getName();

        if ($routeName === '') {
            return false;
        }

        if ($routeName === 'filament.admin.pages.my-profile') {
            return true;
        }

        return str_starts_with($routeName, 'filament.admin.auth.');
    }

    protected function recordBlockAudit(User $user, Request $request): void
    {
        $sessionKey = 'security.mfa_blocked_once';
        if ($request->session()->get($sessionKey) === true) {
            return;
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_SECURITY,
            entityId: (int) $user->id,
            action: AuditLog::ACTION_BLOCK,
            actorId: (int) $user->id,
            metadata: [
                'reason' => 'mfa_required',
                'route' => optional($request->route())->getName(),
                'path' => $request->path(),
            ],
        );

        $request->session()->put($sessionKey, true);
    }
}
