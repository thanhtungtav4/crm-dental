<?php

use App\Models\User;

it('requires authentication for mobile openapi endpoint', function (): void {
    $this->getJson('/api/v1/mobile/openapi')
        ->assertUnauthorized();
});

it('returns full openapi contract for mobile api', function (): void {
    $user = User::factory()->create();

    $token = $user->createToken('mobile-openapi', ['mobile:read'])->plainTextToken;

    $response = $this->getJson('/api/v1/mobile/openapi', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertOk()
        ->assertJsonPath('openapi', '3.0.3')
        ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer')
        ->assertJsonPath('paths./api/v1/mobile/auth/token.post.requestBody.required', true)
        ->assertJsonPath('paths./api/v1/mobile/appointments.get.responses.200.description', 'Danh sách lịch hẹn')
        ->assertJsonPath('paths./api/v1/mobile/patients/{patient}.get.responses.403.description', 'Không có quyền truy cập patient thuộc branch khác')
        ->assertJsonPath('paths./api/v1/mobile/invoices.get.responses.200.description', 'Danh sách hóa đơn')
        ->assertJsonPath('paths./api/v1/mobile/openapi.get.responses.200.description', 'OpenAPI contract')
        ->assertJsonPath('info.description', 'Contract chính thức cho mobile/SPA. API dùng Sanctum Bearer token với ability `mobile:read`.');

    $schemas = $response->json('components.schemas');

    expect($schemas)->toBeArray()->toHaveKeys([
        'MobileAuthTokenRequest',
        'MobileAuthTokenData',
        'MobileAppointment',
        'MobileInvoiceSummary',
        'MobilePatientSummary',
        'SuccessEnvelopeAuthToken',
        'SuccessEnvelopeAppointmentList',
        'SuccessEnvelopeInvoiceList',
        'SuccessEnvelopePatientSummary',
        'ErrorUnauthorized',
        'ErrorForbidden',
        'ErrorValidation',
        'ErrorRateLimited',
    ]);
});
