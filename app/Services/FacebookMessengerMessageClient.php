<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Support\ClinicRuntimeSettings;
use App\Support\ConversationProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class FacebookMessengerMessageClient implements OutboundMessageClient
{
    public function provider(): ConversationProvider
    {
        return ConversationProvider::Facebook;
    }

    public function configurationErrorMessage(?ConversationMessage $message = null): ?string
    {
        if (ClinicRuntimeSettings::facebookPageAccessToken() === '') {
            return 'Thiếu Facebook Page Access Token.';
        }

        if (ClinicRuntimeSettings::facebookSendEndpoint() === '') {
            return 'Thiếu Messenger send endpoint.';
        }

        if ($message instanceof ConversationMessage && blank($message->conversation?->external_user_id)) {
            return 'Thiếu Facebook PSID cho hội thoại.';
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
            'messaging_type' => 'RESPONSE',
            'recipient' => [
                'id' => (string) $message->conversation->external_user_id,
            ],
            'message' => [
                'text' => trim((string) $message->body),
            ],
        ];

        try {
            $response = $this->httpClient(ClinicRuntimeSettings::facebookPageAccessToken())
                ->post(ClinicRuntimeSettings::facebookSendEndpoint(), $payload);
        } catch (Throwable $throwable) {
            return $this->failure($throwable->getMessage());
        }

        $decoded = $this->decodeResponse($response->json());
        $providerStatusCode = $this->extractProviderStatusCode($decoded);
        $errorMessage = data_get($decoded, 'error.message');
        $isSuccess = $response->successful() && ! is_string($errorMessage);

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

    protected function httpClient(string $pageAccessToken): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(15)
            ->retry([200, 500], throw: false)
            ->withQueryParameters([
                'access_token' => $pageAccessToken,
            ]);
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
        $value = data_get($response, 'message_id');

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
        $value = data_get($response, 'error.code')
            ?? data_get($response, 'error.error_subcode')
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
        $message = data_get($response, 'error.message');

        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        $fallback = trim((string) $fallbackBody);

        return $fallback !== '' ? $fallback : 'Facebook Messenger provider request failed.';
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
