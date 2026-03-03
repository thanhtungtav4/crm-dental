<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleCalendarIntegrationService
{
    /**
     * @return array{success:bool,message:string,status:int|null,response:array<string,mixed>|null,calendar_id:string|null,account_email:string|null}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).',
                'status' => null,
                'response' => null,
                'calendar_id' => null,
                'account_email' => null,
            ];
        }

        $result = $this->sendRequest(
            method: 'GET',
            endpoint: '/calendars/'.rawurlencode(ClinicRuntimeSettings::googleCalendarCalendarId()),
        );

        return [
            ...$result,
            'calendar_id' => filled(data_get($result['response'], 'id'))
                ? (string) data_get($result['response'], 'id')
                : null,
            'account_email' => filled(data_get($result['response'], 'summary'))
                ? (string) data_get($result['response'], 'summary')
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success:bool,message:string,status:int|null,response:array<string,mixed>|null,google_event_id:string|null,updated:string|null}
     */
    public function upsertEvent(?string $googleEventId, array $payload): array
    {
        if ($googleEventId !== null && trim($googleEventId) !== '') {
            $result = $this->sendRequest(
                method: 'PUT',
                endpoint: sprintf(
                    '/calendars/%s/events/%s',
                    rawurlencode(ClinicRuntimeSettings::googleCalendarCalendarId()),
                    rawurlencode(trim($googleEventId)),
                ),
                payload: $payload,
            );

            return [
                ...$result,
                'google_event_id' => filled(data_get($result['response'], 'id'))
                    ? (string) data_get($result['response'], 'id')
                    : trim($googleEventId),
                'updated' => filled(data_get($result['response'], 'updated'))
                    ? (string) data_get($result['response'], 'updated')
                    : null,
            ];
        }

        $result = $this->sendRequest(
            method: 'POST',
            endpoint: '/calendars/'.rawurlencode(ClinicRuntimeSettings::googleCalendarCalendarId()).'/events',
            payload: $payload,
        );

        return [
            ...$result,
            'google_event_id' => filled(data_get($result['response'], 'id'))
                ? (string) data_get($result['response'], 'id')
                : null,
            'updated' => filled(data_get($result['response'], 'updated'))
                ? (string) data_get($result['response'], 'updated')
                : null,
        ];
    }

    /**
     * @return array{success:bool,message:string,status:int|null,response:array<string,mixed>|null}
     */
    public function deleteEvent(string $googleEventId): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).',
                'status' => null,
                'response' => null,
            ];
        }

        if (trim($googleEventId) === '') {
            return [
                'success' => true,
                'message' => 'Không có Google event id để xóa.',
                'status' => 200,
                'response' => null,
            ];
        }

        $result = $this->sendRequest(
            method: 'DELETE',
            endpoint: sprintf(
                '/calendars/%s/events/%s',
                rawurlencode(ClinicRuntimeSettings::googleCalendarCalendarId()),
                rawurlencode(trim($googleEventId)),
            ),
        );

        if (($result['status'] ?? null) === 404) {
            return [
                ...$result,
                'success' => true,
                'message' => 'Google event không tồn tại trên remote, coi như đã xóa.',
            ];
        }

        return $result;
    }

    public function isConfigured(): bool
    {
        return ClinicRuntimeSettings::isGoogleCalendarEnabled()
            && ClinicRuntimeSettings::googleCalendarClientId() !== ''
            && ClinicRuntimeSettings::googleCalendarClientSecret() !== ''
            && ClinicRuntimeSettings::googleCalendarRefreshToken() !== ''
            && ClinicRuntimeSettings::googleCalendarCalendarId() !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success:bool,message:string,status:int|null,response:array<string,mixed>|null}
     */
    protected function sendRequest(string $method, string $endpoint, array $payload = []): array
    {
        $tokenResult = $this->issueAccessToken();

        if (($tokenResult['success'] ?? false) !== true) {
            return [
                'success' => false,
                'message' => (string) ($tokenResult['message'] ?? 'Không thể lấy access token Google.'),
                'status' => $tokenResult['status'] ?? null,
                'response' => $tokenResult['response'] ?? null,
            ];
        }

        $accessToken = trim((string) data_get($tokenResult, 'access_token'));

        if ($accessToken === '') {
            return [
                'success' => false,
                'message' => 'Google OAuth không trả về access_token hợp lệ.',
                'status' => null,
                'response' => $tokenResult['response'] ?? null,
            ];
        }

        try {
            $request = $this->calendarHttpClient($accessToken);
            $response = match (strtoupper($method)) {
                'GET' => $request->get($endpoint),
                'POST' => $request->post($endpoint, $payload),
                'PUT' => $request->put($endpoint, $payload),
                'DELETE' => $request->delete($endpoint),
                default => throw new \InvalidArgumentException('Unsupported method: '.$method),
            };
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
            'message' => (string) data_get($responseData, 'error.message', $response->body() ?: 'Google API error'),
            'status' => $response->status(),
            'response' => $responseData,
        ];
    }

    /**
     * @return array{success:bool,message:string,status:int|null,response:array<string,mixed>|null,access_token:string|null}
     */
    protected function issueAccessToken(): array
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->retry([200, 500], throw: false)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => ClinicRuntimeSettings::googleCalendarClientId(),
                    'client_secret' => ClinicRuntimeSettings::googleCalendarClientSecret(),
                    'refresh_token' => ClinicRuntimeSettings::googleCalendarRefreshToken(),
                    'grant_type' => 'refresh_token',
                ]);
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => $throwable->getMessage(),
                'status' => null,
                'response' => null,
                'access_token' => null,
            ];
        }

        $responseData = $this->decodeResponse($response);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'OAuth token issued',
                'status' => $response->status(),
                'response' => $responseData,
                'access_token' => filled(data_get($responseData, 'access_token'))
                    ? (string) data_get($responseData, 'access_token')
                    : null,
            ];
        }

        return [
            'success' => false,
            'message' => (string) data_get($responseData, 'error_description', data_get($responseData, 'error', 'Google OAuth error')),
            'status' => $response->status(),
            'response' => $responseData,
            'access_token' => null,
        ];
    }

    protected function calendarHttpClient(string $accessToken): PendingRequest
    {
        return Http::baseUrl('https://www.googleapis.com/calendar/v3')
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry([200, 500], throw: false)
            ->withToken($accessToken);
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
