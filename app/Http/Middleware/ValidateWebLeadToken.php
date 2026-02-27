<?php

namespace App\Http\Middleware;

use App\Models\ClinicSetting;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebLeadToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = filter_var(
            ClinicSetting::getValue('web_lead.enabled', config('services.web_lead.enabled', false)),
            FILTER_VALIDATE_BOOLEAN,
        );

        if (! $enabled) {
            return new JsonResponse([
                'message' => 'Web lead API chưa được bật.',
            ], 503);
        }

        $configuredToken = (string) ClinicSetting::getValue(
            'web_lead.api_token',
            config('services.web_lead.token', ''),
        );

        if ($configuredToken === '') {
            return new JsonResponse([
                'message' => 'Web lead API token chưa được cấu hình.',
            ], 503);
        }

        $incomingToken = (string) ($request->bearerToken() ?: $request->header('X-Web-Lead-Token', ''));

        if ($incomingToken === '' || ! hash_equals($configuredToken, $incomingToken)) {
            return new JsonResponse([
                'message' => 'Token không hợp lệ.',
            ], 401);
        }

        return $next($request);
    }
}
