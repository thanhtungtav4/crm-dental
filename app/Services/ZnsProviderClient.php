<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZnsProviderClient
{
    public function isConfigured(): bool
    {
        return $this->configurationErrorMessage() === null;
    }

    public function configurationErrorMessage(): ?string
    {
        if (ClinicRuntimeSettings::znsSendEndpoint() === '') {
            return 'Thiếu ZNS send endpoint.';
        }

        if (trim((string) ClinicRuntimeSettings::get('zns.access_token', '')) === '') {
            return 'Thiếu ZNS access token.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   success:bool,
     *   status:int|null,
     *   provider_message_id:string|null,
     *   provider_status_code:string|null,
     *   error:string|null,
     *   response:array<string, mixed>|null
     * }
     */
    public function sendTemplate(array $payload): array
    {
        $configurationError = $this->configurationErrorMessage();

        if ($configurationError !== null) {
            return $this->failure($configurationError);
        }

        $endpoint = ClinicRuntimeSettings::znsSendEndpoint();
        $accessToken = trim((string) ClinicRuntimeSettings::get('zns.access_token', ''));

        try {
            $response = $this->httpClient($accessToken)
                ->post($endpoint, $payload);
        } catch (Throwable $throwable) {
            return $this->failure($throwable->getMessage());
        }

        $decoded = $this->decodeResponse($response->json());
        if ($response->successful()) {
            return [
                'success' => true,
                'status' => $response->status(),
                'provider_message_id' => $this->extractProviderMessageId($decoded),
                'provider_status_code' => $this->extractProviderStatusCode($decoded),
                'error' => null,
                'response' => $decoded,
            ];
        }

        return [
            'success' => false,
            'status' => $response->status(),
            'provider_message_id' => null,
            'provider_status_code' => $this->extractProviderStatusCode($decoded),
            'error' => $this->extractErrorMessage($decoded, $response->body()),
            'response' => $decoded,
        ];
    }

    protected function httpClient(string $accessToken): PendingRequest
    {
        $timeoutSeconds = ClinicRuntimeSettings::znsRequestTimeoutSeconds();

        return Http::acceptJson()
            ->asJson()
            ->timeout($timeoutSeconds)
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
        $value = data_get($response, 'data.msg_id')
            ?? data_get($response, 'data.message_id')
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

        if (is_int($value) || is_float($value)) {
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

        return $fallback !== '' ? $fallback : 'ZNS provider request failed.';
    }

    /**
     * @return array{
     *   success:false,
     *   status:null,
     *   provider_message_id:null,
     *   provider_status_code:null,
     *   error:string,
     *   response:null
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
