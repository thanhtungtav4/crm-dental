<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookWebhookEvent;
use App\Services\ConversationProviderManager;
use App\Services\IntegrationOperationalPayloadSanitizer;
use App\Services\IntegrationProviderRuntimeGate;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FacebookWebhookController extends Controller
{
    public function __construct(
        protected ConversationProviderManager $conversationProviderManager,
        protected IntegrationOperationalPayloadSanitizer $integrationOperationalPayloadSanitizer,
        protected IntegrationProviderRuntimeGate $integrationProviderRuntimeGate,
    ) {}

    public function __invoke(Request $request): Response
    {
        $runtimeStatus = $request->isMethod('post')
            ? $this->integrationProviderRuntimeGate->facebookWebhookDeliveryStatus()
            : $this->integrationProviderRuntimeGate->facebookWebhookVerifyStatus();

        if ($runtimeStatus['state'] !== 'ready') {
            return response()->json([
                'ok' => false,
                'message' => $runtimeStatus['message'],
            ], 503);
        }

        if ($request->isMethod('get')) {
            return $this->verify($request);
        }

        $signatureErrorResponse = $this->rejectInvalidWebhookSignature($request);
        if ($signatureErrorResponse instanceof JsonResponse) {
            return $signatureErrorResponse;
        }

        $payload = $request->all();
        $events = $this->extractMessagingEvents($payload);
        $createdEvents = 0;

        foreach ($events as $eventPayload) {
            $eventFingerprint = $this->resolveEventFingerprint($eventPayload);
            $event = FacebookWebhookEvent::query()->firstOrCreate(
                ['event_fingerprint' => $eventFingerprint],
                [
                    'event_name' => $this->resolveEventName($eventPayload),
                    'page_id' => $this->stringValue(data_get($eventPayload, 'page_id')),
                    'sender_id' => $this->stringValue(data_get($eventPayload, 'sender.id')),
                    'recipient_id' => $this->stringValue(data_get($eventPayload, 'recipient.id')),
                    'payload' => $this->integrationOperationalPayloadSanitizer->sanitizeFacebookWebhookPayload($eventPayload),
                    'received_at' => now(),
                    'processed_at' => now(),
                ],
            );

            if ($event->wasRecentlyCreated) {
                $createdEvents++;
            }

            try {
                $this->conversationProviderManager
                    ->inboundNormalizerFor('facebook')
                    ->normalize($event, $eventPayload);
            } catch (Throwable $throwable) {
                report($throwable);

                logger()->warning('facebook.webhook.normalize_failed', [
                    'event_fingerprint' => $eventFingerprint,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $createdEvents === 0 ? 'Duplicate webhook ignored.' : 'Webhook accepted.',
            'duplicate' => $createdEvents === 0,
            'processed_events' => count($events),
        ]);
    }

    protected function verify(Request $request): Response
    {
        $mode = trim((string) $request->query('hub_mode', $request->query('hub.mode', '')));
        $challenge = trim((string) $request->query('hub_challenge', $request->query('hub.challenge', '')));

        if ($mode !== 'subscribe' || $challenge === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook verification request.',
            ], 403);
        }

        $tokenErrorResponse = $this->rejectInvalidVerifyToken($request);

        if ($tokenErrorResponse instanceof JsonResponse) {
            return $tokenErrorResponse;
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    protected function rejectInvalidVerifyToken(Request $request): ?JsonResponse
    {
        $verifyToken = trim((string) $request->query('hub_verify_token', $request->query('hub.verify_token', '')));
        $expectedToken = ClinicRuntimeSettings::facebookWebhookVerifyToken();

        if ($verifyToken !== '' && $expectedToken !== '' && hash_equals($expectedToken, $verifyToken)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Invalid webhook token.',
        ], 403);
    }

    protected function rejectInvalidWebhookSignature(Request $request): ?JsonResponse
    {
        $signature = trim((string) $request->header('x-hub-signature-256', ''));
        $secret = ClinicRuntimeSettings::facebookAppSecret();

        if ($signature === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Missing webhook signature.',
            ], 403);
        }

        $normalizedSignature = Str::lower($signature);
        $normalizedSignature = Str::startsWith($normalizedSignature, 'sha256=')
            ? trim((string) Str::after($normalizedSignature, 'sha256='))
            : $normalizedSignature;

        $expectedSignature = hash_hmac('sha256', (string) $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $normalizedSignature)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook signature.',
            ], 403);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function extractMessagingEvents(array $payload): array
    {
        $events = [];

        foreach ((array) data_get($payload, 'entry', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $pageId = $this->stringValue(data_get($entry, 'id'));
            $entryTime = data_get($entry, 'time');

            foreach ((array) data_get($entry, 'messaging', []) as $messagingEvent) {
                if (! is_array($messagingEvent)) {
                    continue;
                }

                $events[] = array_merge($messagingEvent, [
                    'object' => $this->stringValue(data_get($payload, 'object')),
                    'page_id' => $pageId,
                    'entry_time' => $entryTime,
                ]);
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveEventFingerprint(array $payload): string
    {
        $messageId = $this->stringValue(data_get($payload, 'message.mid'));

        if ($messageId !== '') {
            return hash('sha256', 'facebook-message:'.$messageId);
        }

        $payloadHash = $this->payloadHash($payload);

        return hash('sha256', implode('|', [
            'facebook-composite',
            $this->resolveEventName($payload),
            $this->stringValue(data_get($payload, 'page_id')),
            $this->stringValue(data_get($payload, 'sender.id')),
            $this->stringValue(data_get($payload, 'recipient.id')),
            $this->stringValue(data_get($payload, 'timestamp') ?? data_get($payload, 'entry_time')),
            $payloadHash,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveEventName(array $payload): string
    {
        if (is_array(data_get($payload, 'message'))) {
            return data_get($payload, 'message.is_echo') === true ? 'message_echo' : 'message';
        }

        if (is_array(data_get($payload, 'read'))) {
            return 'read';
        }

        if (is_array(data_get($payload, 'delivery'))) {
            return 'delivery';
        }

        if (is_array(data_get($payload, 'postback'))) {
            return 'postback';
        }

        return 'unknown';
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

    protected function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }
}
