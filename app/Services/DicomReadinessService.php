<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Facades\Http;
use Throwable;

class DicomReadinessService
{
    /**
     * @return array{
     *     enabled:bool,
     *     ready:bool,
     *     checks:array<int, array{code:string,passed:bool,message:string}>,
     *     config:array{base_url:string,facility_code:string,timeout_seconds:int,token_configured:bool},
     *     probe:array{attempted:bool,ok:bool,http_status:int|null,error:string|null},
     *     checked_at:string
     * }
     */
    public function snapshot(bool $probeEndpoint = false): array
    {
        $enabled = ClinicRuntimeSettings::dicomIntegrationEnabled();
        $baseUrl = ClinicRuntimeSettings::dicomBaseUrl();
        $facilityCode = ClinicRuntimeSettings::dicomFacilityCode();
        $timeoutSeconds = ClinicRuntimeSettings::dicomTimeoutSeconds();
        $token = ClinicRuntimeSettings::dicomAuthToken();

        $checks = [
            $this->checkResult(
                'config_base_url',
                $baseUrl !== '',
                $baseUrl !== '' ? 'Đã cấu hình DICOM base URL.' : 'Thiếu DICOM base URL.',
            ),
            $this->checkResult(
                'config_facility_code',
                $facilityCode !== '',
                $facilityCode !== '' ? 'Đã cấu hình DICOM facility code.' : 'Thiếu DICOM facility code.',
            ),
            $this->checkResult(
                'config_token',
                $token !== '',
                $token !== '' ? 'Đã cấu hình DICOM auth token.' : 'Thiếu DICOM auth token.',
            ),
        ];

        $probe = [
            'attempted' => false,
            'ok' => false,
            'http_status' => null,
            'error' => null,
        ];

        if ($enabled && $probeEndpoint && $baseUrl !== '') {
            $probe = $this->probeEndpoint($baseUrl, $timeoutSeconds, $token);
            $checks[] = $this->checkResult(
                'endpoint_probe',
                $probe['ok'],
                $probe['ok']
                    ? 'Probe endpoint DICOM thành công.'
                    : 'Probe endpoint DICOM thất bại.',
            );
        }

        $requiredChecksPassed = collect($checks)
            ->filter(fn (array $check): bool => str_starts_with($check['code'], 'config_'))
            ->every(fn (array $check): bool => $check['passed'] === true);
        $probeOk = ! $probeEndpoint || ! $enabled || $probe['ok'] === true;

        return [
            'enabled' => $enabled,
            'ready' => (! $enabled) || ($requiredChecksPassed && $probeOk),
            'checks' => $checks,
            'config' => [
                'base_url' => $baseUrl,
                'facility_code' => $facilityCode,
                'timeout_seconds' => $timeoutSeconds,
                'token_configured' => $token !== '',
            ],
            'probe' => $probe,
            'checked_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array{code:string,passed:bool,message:string}
     */
    protected function checkResult(string $code, bool $passed, string $message): array
    {
        return [
            'code' => $code,
            'passed' => $passed,
            'message' => $message,
        ];
    }

    /**
     * @return array{attempted:bool,ok:bool,http_status:int|null,error:string|null}
     */
    protected function probeEndpoint(string $baseUrl, int $timeoutSeconds, string $token): array
    {
        $probeUrl = rtrim($baseUrl, '/').'/health';

        try {
            $response = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->when($token !== '', fn ($request) => $request->withToken($token))
                ->get($probeUrl);

            return [
                'attempted' => true,
                'ok' => $response->successful(),
                'http_status' => $response->status(),
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            return [
                'attempted' => true,
                'ok' => false,
                'http_status' => null,
                'error' => $throwable->getMessage(),
            ];
        }
    }
}
