<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZaloWebhookEvent;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ZaloWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! ClinicRuntimeSettings::boolean('zalo.enabled', false)) {
            return response()->json([
                'ok' => false,
                'message' => 'Zalo OA integration chưa bật.',
            ], 503);
        }

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

        $payload = $request->all();
        $eventFingerprint = $this->resolveEventFingerprint($payload);
        $eventName = trim((string) data_get($payload, 'event_name', ''));
        $eventId = trim((string) data_get($payload, 'event_id', ''));
        $oaId = trim((string) data_get($payload, 'oa_id', ''));

        $event = ZaloWebhookEvent::query()->firstOrCreate(
            ['event_fingerprint' => $eventFingerprint],
            [
                'event_name' => $eventName !== '' ? $eventName : null,
                'event_id' => $eventId !== '' ? $eventId : null,
                'oa_id' => $oaId !== '' ? $oaId : null,
                'payload' => $payload,
                'received_at' => now(),
                'processed_at' => now(),
            ],
        );

        $isDuplicate = ! $event->wasRecentlyCreated;
        logger()->info('zalo.webhook.received', [
            'event_name' => $eventName,
            'timestamp' => $request->input('timestamp'),
            'oa_id' => $oaId,
            'event_fingerprint' => $eventFingerprint,
            'duplicate' => $isDuplicate,
        ]);

        return response()->json([
            'ok' => true,
            'message' => $isDuplicate ? 'Duplicate webhook ignored.' : 'Webhook accepted.',
            'duplicate' => $isDuplicate,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveEventFingerprint(array $payload): string
    {
        $candidate = trim((string) (data_get($payload, 'event_id')
            ?? data_get($payload, 'message.msg_id')
            ?? data_get($payload, 'message.message_id')
            ?? data_get($payload, 'msg_id')
            ?? ''));

        if ($candidate !== '') {
            return hash('sha256', 'zalo-event:'.$candidate);
        }

        $eventName = trim((string) data_get($payload, 'event_name', ''));
        $oaId = trim((string) data_get($payload, 'oa_id', ''));
        $timestamp = trim((string) data_get($payload, 'timestamp', ''));
        $sender = trim((string) (data_get($payload, 'sender.id') ?? data_get($payload, 'from.uid') ?? ''));

        if ($eventName !== '' && $timestamp !== '') {
            return hash('sha256', implode('|', [
                'zalo-composite',
                $eventName,
                $oaId,
                $timestamp,
                $sender,
            ]));
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($payloadJson) || trim($payloadJson) === '') {
            $payloadJson = Str::uuid()->toString();
        }

        return hash('sha256', 'zalo-payload:'.$payloadJson);
    }
}
