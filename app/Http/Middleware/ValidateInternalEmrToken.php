<?php

namespace App\Http\Middleware;

use App\Models\ClinicSetting;
use App\Services\AutomationActorResolver;
use App\Support\ActionPermission;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateInternalEmrToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = filter_var(
            ClinicSetting::getValue('emr.enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        if (! $enabled) {
            return new JsonResponse([
                'message' => 'EMR internal API chưa được bật.',
            ], 503);
        }

        $configuredToken = trim((string) ClinicSetting::getValue('emr.api_key', ''));

        if ($configuredToken === '') {
            return new JsonResponse([
                'message' => 'EMR API key chưa được cấu hình.',
            ], 503);
        }

        $incomingToken = (string) ($request->bearerToken() ?: $request->header('X-EMR-API-KEY', ''));

        if ($incomingToken === '' || ! hash_equals($configuredToken, $incomingToken)) {
            return new JsonResponse([
                'message' => 'Token không hợp lệ.',
            ], 401);
        }

        $actor = app(AutomationActorResolver::class)->resolveForPermission(
            permission: ActionPermission::EMR_CLINICAL_WRITE,
            enforceRequiredRole: true,
        );

        if (! $actor) {
            return new JsonResponse([
                'message' => 'Không có service account hợp lệ cho EMR internal API.',
            ], 503);
        }

        Auth::setUser($actor);

        return $next($request);
    }
}
