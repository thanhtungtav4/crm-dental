<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminSessionIdleTimeout
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

        if ($this->isAuthenticationRoute($request)) {
            return $next($request);
        }

        $timeoutMinutes = ClinicRuntimeSettings::securitySessionIdleTimeoutMinutes();
        $sessionKey = 'security.admin_last_activity_at';
        $nowTimestamp = now()->getTimestamp();
        $lastActivityAt = $request->session()->get($sessionKey);

        if (is_numeric($lastActivityAt)) {
            $idleSeconds = $nowTimestamp - (int) $lastActivityAt;

            if ($idleSeconds > ($timeoutMinutes * 60)) {
                $this->recordTimeoutAudit($user, $idleSeconds, $timeoutMinutes);
                $this->terminateSession($request);

                return redirect()->route('filament.admin.auth.login');
            }
        }

        $request->session()->put($sessionKey, $nowTimestamp);

        return $next($request);
    }

    protected function isAuthenticationRoute(Request $request): bool
    {
        $routeName = (string) optional($request->route())->getName();

        if ($routeName === '') {
            return false;
        }

        return str_starts_with($routeName, 'filament.admin.auth.');
    }

    protected function recordTimeoutAudit(User $user, int $idleSeconds, int $timeoutMinutes): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_SECURITY,
            entityId: (int) $user->id,
            action: AuditLog::ACTION_FAIL,
            actorId: (int) $user->id,
            metadata: [
                'reason' => 'session_idle_timeout',
                'idle_seconds' => $idleSeconds,
                'timeout_minutes' => $timeoutMinutes,
            ],
        );
    }

    protected function terminateSession(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
