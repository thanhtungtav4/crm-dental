<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\Request;

class ZaloWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $verifyToken = trim((string) $request->query('hub_verify_token', $request->input('verify_token', '')));
        $expectedToken = trim((string) ClinicRuntimeSettings::get('zalo.webhook_token', ''));

        if ($verifyToken === '' || $expectedToken === '' || ! hash_equals($expectedToken, $verifyToken)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook token.',
            ], 403);
        }

        $challenge = trim((string) $request->query('hub_challenge', $request->input('challenge', '')));

        if ($request->isMethod('get') && $challenge !== '') {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        logger()->info('zalo.webhook.received', [
            'event_name' => $request->input('event_name'),
            'timestamp' => $request->input('timestamp'),
            'oa_id' => $request->input('oa_id'),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Webhook accepted.',
        ]);
    }
}
