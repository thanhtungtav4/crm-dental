<?php

namespace App\Http\Middleware;

use App\Services\IntegrationProviderRuntimeGate;
use App\Services\IntegrationSecretRotationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebLeadToken
{
    public function __construct(
        protected IntegrationProviderRuntimeGate $integrationProviderRuntimeGate,
        protected IntegrationSecretRotationService $integrationSecretRotationService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->integrationProviderRuntimeGate->webLeadIngressStatus();

        if ($status['state'] !== 'ready') {
            return new JsonResponse([
                'message' => $status['message'],
            ], 503);
        }

        $incomingToken = (string) ($request->bearerToken() ?: $request->header('X-Web-Lead-Token', ''));

        if (
            $incomingToken === ''
            || ! $this->integrationSecretRotationService->matches('web_lead.api_token', $incomingToken)
        ) {
            return new JsonResponse([
                'message' => 'Token không hợp lệ.',
            ], 401);
        }

        return $next($request);
    }
}
