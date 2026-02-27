<?php

namespace App\Services;

use App\Models\EmrSyncEvent;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class EmrIntegrationService
{
    /**
     * @return array{success:bool,message:string,status:int|null,response:array<string,mixed>|null}
     */
    public function authenticate(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'EMR chưa cấu hình đầy đủ (base_url/api_key).',
                'status' => null,
                'response' => null,
            ];
        }

        return $this->sendRequest(
            endpoint: '/api/emr/authenticate',
            payload: [
                'clinic_code' => ClinicRuntimeSettings::emrClinicCode(),
                'provider' => ClinicRuntimeSettings::emrProvider(),
            ],
        );
    }

    /**
     * @return array{success:bool,message:string,status:int|null,url:string|null,response:array<string,mixed>|null}
     */
    public function resolveConfigUrl(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'EMR chưa cấu hình đầy đủ (base_url/api_key).',
                'status' => null,
                'url' => null,
                'response' => null,
            ];
        }

        $result = $this->sendRequest(
            endpoint: '/api/emr/config-url',
            payload: [
                'clinic_code' => ClinicRuntimeSettings::emrClinicCode(),
                'provider' => ClinicRuntimeSettings::emrProvider(),
            ],
        );

        $url = (string) data_get($result['response'], 'url', data_get($result['response'], 'config_url', ''));

        return [
            ...$result,
            'url' => $url !== '' ? $url : null,
        ];
    }

    /**
     * @return array{
     *  success:bool,
     *  message:string,
     *  status:int|null,
     *  response:array<string,mixed>|null,
     *  external_patient_id:string|null
     * }
     */
    public function pushPatientPayload(EmrSyncEvent $event): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'EMR chưa cấu hình đầy đủ (base_url/api_key).',
                'status' => null,
                'response' => null,
                'external_patient_id' => null,
            ];
        }

        $result = $this->sendRequest(
            endpoint: '/api/emr/patients/sync',
            payload: [
                'event_key' => $event->event_key,
                'event_type' => $event->event_type,
                'branch_id' => $event->branch_id,
                'patient_id' => $event->patient_id,
                'payload_checksum' => $event->payload_checksum,
                'payload' => $event->payload,
                'clinic_code' => ClinicRuntimeSettings::emrClinicCode(),
                'provider' => ClinicRuntimeSettings::emrProvider(),
            ],
        );

        $externalPatientId = data_get($result['response'], 'external_patient_id')
            ?? data_get($result['response'], 'patient_id');

        return [
            ...$result,
            'external_patient_id' => filled($externalPatientId) ? (string) $externalPatientId : null,
        ];
    }

    public function isConfigured(): bool
    {
        return ClinicRuntimeSettings::isEmrEnabled()
            && ClinicRuntimeSettings::emrBaseUrl() !== ''
            && ClinicRuntimeSettings::emrApiKey() !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success:bool,message:string,status:int|null,response:array<string,mixed>|null}
     */
    protected function sendRequest(string $endpoint, array $payload): array
    {
        try {
            $response = $this->httpClient()->post($endpoint, $payload);
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => $throwable->getMessage(),
                'status' => null,
                'response' => null,
            ];
        }

        $responseData = $this->decodeResponse($response);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => (string) data_get($responseData, 'message', 'OK'),
                'status' => $response->status(),
                'response' => $responseData,
            ];
        }

        return [
            'success' => false,
            'message' => (string) data_get($responseData, 'message', $response->body() ?: 'Unknown error'),
            'status' => $response->status(),
            'response' => $responseData,
        ];
    }

    protected function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(rtrim(ClinicRuntimeSettings::emrBaseUrl(), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->withHeaders([
                'X-EMR-API-KEY' => ClinicRuntimeSettings::emrApiKey(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(Response $response): array
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }
}
