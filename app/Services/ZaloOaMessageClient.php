<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Support\ClinicRuntimeSettings;
use App\Support\ConversationProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZaloOaMessageClient implements OutboundMessageClient
{
    public function provider(): ConversationProvider
    {
        return ConversationProvider::Zalo;
    }

    public function isConfigured(): bool
    {
        return $this->configurationErrorMessage() === null;
    }

    public function configurationErrorMessage(?ConversationMessage $message = null): ?string
    {
        if (ClinicRuntimeSettings::zaloAccessToken() === '') {
            return 'Thiếu Zalo OA access token.';
        }

        if (ClinicRuntimeSettings::zaloSendEndpoint() === '') {
            return 'Thiếu Zalo OA send endpoint.';
        }

        if ($message instanceof ConversationMessage && blank($message->conversation?->external_user_id)) {
            return 'Thiếu Zalo external user id cho hội thoại.';
        }

        return null;
    }

    public function send(ConversationMessage $message): array
    {
        $message->loadMissing('conversation');

        $configurationError = $this->configurationErrorMessage($message);

        if ($configurationError !== null) {
            return $this->failure($configurationError);
        }

        $payload = [
            'recipient' => [
                'user_id' => (string) $message->conversation->external_user_id,
            ],
            'message' => [
                'text' => trim((string) $message->body),
            ],
        ];

        try {
            $response = $this->httpClient(ClinicRuntimeSettings::zaloAccessToken())
                ->post(ClinicRuntimeSettings::zaloSendEndpoint(), $payload);
        } catch (Throwable $throwable) {
            return $this->failure($throwable->getMessage());
        }

        $decoded = $this->decodeResponse($response->json());
        $providerStatusCode = $this->extractProviderStatusCode($decoded);
        $providerErrorCode = data_get($decoded, 'error');
        $isSuccess = $response->successful()
            && (
                $providerErrorCode === null
                || (is_numeric($providerErrorCode) && (int) $providerErrorCode === 0)
                || (is_string($providerErrorCode) && trim($providerErrorCode) === '0')
            );

        if ($isSuccess) {
            return [
                'success' => true,
                'status' => $response->status(),
                'provider_message_id' => $this->extractProviderMessageId($decoded),
                'provider_status_code' => $providerStatusCode,
                'error' => null,
                'response' => $decoded,
            ];
        }

        return [
            'success' => false,
            'status' => $response->status(),
            'provider_message_id' => null,
            'provider_status_code' => $providerStatusCode,
            'error' => $this->extractErrorMessage($decoded, $response->body()),
            'response' => $decoded,
        ];
    }

    protected function httpClient(string $accessToken): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry([200, 500], throw: false)
            ->withToken($accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(mixed $decoded): array
    {
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractProviderMessageId(array $response): ?string
    {
        $value = data_get($response, 'data.message_id')
            ?? data_get($response, 'data.msg_id')
            ?? data_get($response, 'message_id');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractProviderStatusCode(array $response): ?string
    {
        $value = data_get($response, 'error')
            ?? data_get($response, 'code')
            ?? data_get($response, 'status');

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractErrorMessage(array $response, ?string $fallbackBody = null): string
    {
        $message = data_get($response, 'message')
            ?? data_get($response, 'error_name')
            ?? data_get($response, 'error_description');

        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        $fallback = trim((string) $fallbackBody);

        return $fallback !== '' ? $fallback : 'Zalo OA provider request failed.';
    }

    /**
     * @return array{
     *     success:false,
     *     status:null,
     *     provider_message_id:null,
     *     provider_status_code:null,
     *     error:string,
     *     response:null
     * }
     */
    protected function failure(string $error): array
    {
        return [
            'success' => false,
            'status' => null,
            'provider_message_id' => null,
            'provider_status_code' => null,
            'error' => $error,
            'response' => null,
        ];
    }
}
