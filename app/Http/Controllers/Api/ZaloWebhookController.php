<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZaloWebhookEvent;
use App\Services\IntegrationOperationalPayloadSanitizer;
use App\Services\IntegrationProviderRuntimeGate;
use App\Services\IntegrationSecretRotationService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ZaloWebhookController extends Controller
{
    public function __construct(
        protected IntegrationProviderRuntimeGate $integrationProviderRuntimeGate,
        protected IntegrationOperationalPayloadSanitizer $integrationOperationalPayloadSanitizer,
        protected IntegrationSecretRotationService $integrationSecretRotationService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $runtimeStatus = $request->isMethod('post')
            ? $this->integrationProviderRuntimeGate->zaloWebhookDeliveryStatus()
            : $this->integrationProviderRuntimeGate->zaloWebhookVerifyStatus();

        if ($runtimeStatus['state'] !== 'ready') {
            return response()->json([
                'ok' => false,
                'message' => $runtimeStatus['message'],
            ], 503);
        }

        $challenge = trim((string) $request->query('hub_challenge', $request->input('challenge', '')));

        if ($request->isMethod('get') && $challenge !== '') {
            $tokenErrorResponse = $this->rejectInvalidVerifyToken($request);
            if ($tokenErrorResponse instanceof JsonResponse) {
                return $tokenErrorResponse;
            }

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        if ($request->isMethod('post')) {
            $signatureErrorResponse = $this->rejectInvalidWebhookSignature($request);
            if ($signatureErrorResponse instanceof JsonResponse) {
                return $signatureErrorResponse;
            }
        } else {
            $tokenErrorResponse = $this->rejectInvalidVerifyToken($request);
            if ($tokenErrorResponse instanceof JsonResponse) {
                return $tokenErrorResponse;
            }
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
                'payload' => $this->integrationOperationalPayloadSanitizer->sanitizeZaloWebhookPayload($payload),
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
        $payloadHash = $this->payloadHash($payload);
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
                $payloadHash,
            ]));
        }

        return hash('sha256', 'zalo-payload:'.$payloadHash);
    }

    protected function rejectInvalidVerifyToken(Request $request): ?JsonResponse
    {
        $verifyToken = trim((string) $request->query('hub_verify_token', $request->input('verify_token', '')));
        $expectedToken = trim((string) ClinicRuntimeSettings::get('zalo.webhook_token', ''));

        if (
            $verifyToken !== ''
            && $expectedToken !== ''
            && $this->integrationSecretRotationService->matches('zalo.webhook_token', $verifyToken)
        ) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Invalid webhook token.',
        ], 403);
    }

    protected function rejectInvalidWebhookSignature(Request $request): ?JsonResponse
    {
        $secret = trim((string) ClinicRuntimeSettings::get('zalo.app_secret', ''));

        $signature = trim((string) (
            $request->header('x-zalo-signature')
            ?? $request->header('x-oa-signature')
            ?? $request->header('x-zns-signature')
            ?? $request->input('signature', '')
        ));

        $normalizedSignature = $this->normalizeSignature($signature);
        if ($normalizedSignature === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Missing webhook signature.',
            ], 403);
        }

        $rawBody = (string) $request->getContent();
        $payload = $request->all();

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($payloadJson) || trim($payloadJson) === '') {
            $payloadJson = '{}';
        }

        $canonicalPayloadJson = json_encode($this->sortPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($canonicalPayloadJson) || trim($canonicalPayloadJson) === '') {
            $canonicalPayloadJson = '{}';
        }

        $candidatePayloads = array_values(array_unique(array_filter([
            $rawBody,
            $payloadJson,
            $canonicalPayloadJson,
        ], static fn (string $candidate): bool => trim($candidate) !== '')));

        foreach ($candidatePayloads as $candidatePayload) {
            $expectedHex = hash_hmac('sha256', $candidatePayload, $secret);

            if (hash_equals($expectedHex, $normalizedSignature)) {
                return null;
            }

            $expectedBase64 = base64_encode(hash_hmac('sha256', $candidatePayload, $secret, true));
            if (hash_equals($expectedBase64, $normalizedSignature)) {
                return null;
            }
        }

        return response()->json([
            'ok' => false,
            'message' => 'Invalid webhook signature.',
        ], 403);
    }

    protected function normalizeSignature(string $signature): string
    {
        $normalized = trim($signature);
        $normalized = Str::lower($normalized);

        if (Str::startsWith($normalized, 'sha256=')) {
            return trim((string) Str::after($normalized, 'sha256='));
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function payloadHash(array $payload): string
    {
        $payloadJson = json_encode($this->sortPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payloadJson) || trim($payloadJson) === '') {
            $payloadJson = Str::uuid()->toString();
        }

        return hash('sha256', $payloadJson);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sortPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortPayload($value);
            }
        }

        ksort($payload);

        return $payload;
    }
}
