<?php

namespace App\Http\Middleware;

use App\Services\AutomationActorResolver;
use App\Services\IntegrationProviderRuntimeGate;
use App\Services\IntegrationSecretRotationService;
use App\Support\ActionPermission;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateInternalEmrToken
{
    public function __construct(
        protected IntegrationProviderRuntimeGate $integrationProviderRuntimeGate,
        protected IntegrationSecretRotationService $integrationSecretRotationService,
        protected AutomationActorResolver $automationActorResolver,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->integrationProviderRuntimeGate->emrInternalIngressStatus();

        if ($status['state'] !== 'ready') {
            return new JsonResponse([
                'message' => $status['message'],
            ], 503);
        }

        $incomingToken = (string) ($request->bearerToken() ?: $request->header('X-EMR-API-KEY', ''));

        if (
            $incomingToken === ''
            || ! $this->integrationSecretRotationService->matches('emr.api_key', $incomingToken)
        ) {
            return new JsonResponse([
                'message' => 'Token không hợp lệ.',
            ], 401);
        }

        $actor = $this->automationActorResolver->resolveForPermission(
            permission: ActionPermission::EMR_CLINICAL_WRITE,
            enforceRequiredRole: true,
            failOnPrivilegedRoles: true,
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
