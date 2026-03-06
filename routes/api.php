<?php

use App\Http\Controllers\Api\InternalEmrMutationController;
use App\Http\Controllers\Api\V1\MobileAppointmentController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileInvoiceSummaryController;
use App\Http\Controllers\Api\V1\MobilePatientSummaryController;
use App\Http\Controllers\Api\WebLeadController;
use App\Http\Controllers\Api\ZaloWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['web-lead.token', 'throttle:web-leads'])
    ->group(function (): void {
        Route::post('/web-leads', [WebLeadController::class, 'store'])
            ->name('api.v1.web-leads.store');
    });

Route::match(['get', 'post'], '/v1/integrations/zalo/webhook', ZaloWebhookController::class)
    ->middleware('throttle:zalo-webhook')
    ->name('api.v1.integrations.zalo.webhook');

Route::prefix('v1/emr/internal')
    ->middleware(['emr.internal.token'])
    ->group(function (): void {
        Route::post('/clinical-notes/{clinicalNote}/amend', [InternalEmrMutationController::class, 'amendClinicalNote'])
            ->name('api.v1.emr.internal.clinical-notes.amend');
    });

Route::prefix('v1/mobile')
    ->group(function (): void {
        Route::post('/auth/token', [MobileAuthController::class, 'store'])
            ->middleware('throttle:mobile-auth')
            ->name('api.v1.mobile.auth.token');

        Route::middleware(['auth:sanctum', 'ability:mobile:read', 'throttle:api-mobile'])
            ->group(function (): void {
                Route::delete('/auth/token', [MobileAuthController::class, 'destroy'])
                    ->name('api.v1.mobile.auth.logout');
                Route::get('/appointments', [MobileAppointmentController::class, 'index'])
                    ->name('api.v1.mobile.appointments.index');
                Route::get('/patients/{patient}', [MobilePatientSummaryController::class, 'show'])
                    ->name('api.v1.mobile.patients.show');
                Route::get('/invoices', [MobileInvoiceSummaryController::class, 'index'])
                    ->name('api.v1.mobile.invoices.index');
                Route::get('/openapi', function () {
                    $errorSchemas = [
                        'ErrorUnauthorized' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'Unauthenticated.'],
                            ],
                            'required' => ['message'],
                        ],
                        'ErrorForbidden' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'Bạn không có quyền truy cập dữ liệu này.'],
                            ],
                            'required' => ['message'],
                        ],
                        'ErrorValidation' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                                'errors' => [
                                    'type' => 'object',
                                    'additionalProperties' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                            'required' => ['message', 'errors'],
                        ],
                        'ErrorRateLimited' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'Too Many Attempts.'],
                            ],
                            'required' => ['message'],
                        ],
                        'ErrorNotFound' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'example' => 'No query results for model.'],
                            ],
                            'required' => ['message'],
                        ],
                    ];

                    return response()->json([
                        'openapi' => '3.0.3',
                        'info' => [
                            'title' => 'Dental CRM Mobile API',
                            'version' => '1.0.0',
                            'description' => 'Contract chính thức cho mobile/SPA. API dùng Sanctum Bearer token với ability `mobile:read`.',
                        ],
                        'servers' => [
                            [
                                'url' => url('/api/v1/mobile'),
                                'description' => 'Mobile API v1',
                            ],
                        ],
                        'tags' => [
                            ['name' => 'Auth', 'description' => 'Đăng nhập/đăng xuất token mobile'],
                            ['name' => 'Appointments', 'description' => 'Lịch hẹn theo branch user được phép truy cập'],
                            ['name' => 'Patients', 'description' => 'Thông tin tóm tắt bệnh nhân (đã scope branch)'],
                            ['name' => 'Invoices', 'description' => 'Tổng hợp hóa đơn theo branch user được phép truy cập'],
                            ['name' => 'Meta', 'description' => 'Thông tin contract OpenAPI'],
                        ],
                        'components' => [
                            'securitySchemes' => [
                                'bearerAuth' => [
                                    'type' => 'http',
                                    'scheme' => 'bearer',
                                    'bearerFormat' => 'Sanctum',
                                    'description' => 'Authorization: Bearer {token}. Token phải có ability `mobile:read`.',
                                ],
                            ],
                            'schemas' => array_merge([
                                'MobileAuthTokenRequest' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'email' => ['type' => 'string', 'format' => 'email', 'example' => 'doctor.user@example.com'],
                                        'password' => ['type' => 'string', 'minLength' => 6, 'example' => 'secret123'],
                                        'device_name' => ['type' => 'string', 'maxLength' => 120, 'nullable' => true, 'example' => 'iPhone 15 Pro'],
                                    ],
                                    'required' => ['email', 'password'],
                                ],
                                'MobileAuthTokenData' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => ['type' => 'string', 'example' => '1|g4Sa4r...'],
                                        'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer', 'example' => 12],
                                                'name' => ['type' => 'string', 'example' => 'Doctor User'],
                                                'email' => ['type' => 'string', 'format' => 'email', 'example' => 'doctor.user@example.com'],
                                            ],
                                            'required' => ['id', 'name', 'email'],
                                        ],
                                    ],
                                    'required' => ['token', 'token_type', 'user'],
                                ],
                                'SuccessMessageData' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'Đăng xuất thành công.'],
                                    ],
                                    'required' => ['message'],
                                ],
                                'PaginationMeta' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'current_page' => ['type' => 'integer', 'example' => 1],
                                        'from' => ['type' => 'integer', 'nullable' => true, 'example' => 1],
                                        'to' => ['type' => 'integer', 'nullable' => true, 'example' => 20],
                                        'last_page' => ['type' => 'integer', 'example' => 3],
                                        'per_page' => ['type' => 'integer', 'example' => 20],
                                        'total' => ['type' => 'integer', 'example' => 53],
                                    ],
                                    'required' => ['current_page', 'from', 'to', 'last_page', 'per_page', 'total'],
                                ],
                                'MobilePatientMini' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'nullable' => true, 'example' => 1001],
                                        'patient_code' => ['type' => 'string', 'nullable' => true, 'example' => 'PAT-20260201-XYZ'],
                                        'full_name' => ['type' => 'string', 'nullable' => true, 'example' => 'Nguyen Van A'],
                                        'phone' => ['type' => 'string', 'nullable' => true, 'example' => '0901234567'],
                                    ],
                                    'required' => ['id', 'patient_code', 'full_name', 'phone'],
                                ],
                                'MobileDoctorMini' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'nullable' => true, 'example' => 35],
                                        'name' => ['type' => 'string', 'nullable' => true, 'example' => 'Doctor User'],
                                    ],
                                    'required' => ['id', 'name'],
                                ],
                                'MobileBranchMini' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'nullable' => true, 'example' => 2],
                                        'name' => ['type' => 'string', 'nullable' => true, 'example' => 'Chi nhánh TP. HCM'],
                                    ],
                                    'required' => ['id', 'name'],
                                ],
                                'MobileAppointment' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'example' => 501],
                                        'date' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true, 'example' => '2026-03-02T09:00:00+07:00'],
                                        'duration_minutes' => ['type' => 'integer', 'example' => 30],
                                        'time_range_label' => ['type' => 'string', 'nullable' => true, 'example' => '09:00-09:30'],
                                        'status' => ['type' => 'string', 'example' => 'scheduled'],
                                        'status_label' => ['type' => 'string', 'example' => 'Đã đặt lịch'],
                                        'appointment_type' => ['type' => 'string', 'nullable' => true, 'example' => 'new_patient'],
                                        'chief_complaint' => ['type' => 'string', 'nullable' => true, 'example' => 'Đau răng hàm trên'],
                                        'patient' => ['$ref' => '#/components/schemas/MobilePatientMini'],
                                        'doctor' => ['$ref' => '#/components/schemas/MobileDoctorMini'],
                                        'branch' => ['$ref' => '#/components/schemas/MobileBranchMini'],
                                    ],
                                    'required' => ['id', 'date', 'duration_minutes', 'time_range_label', 'status', 'status_label', 'appointment_type', 'chief_complaint', 'patient', 'doctor', 'branch'],
                                ],
                                'MobileInvoiceSummary' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'example' => 120],
                                        'invoice_no' => ['type' => 'string', 'nullable' => true, 'example' => 'INV-QC-202603020001'],
                                        'status' => ['type' => 'string', 'nullable' => true, 'example' => 'issued'],
                                        'issued_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true, 'example' => '2026-03-02T08:30:00+07:00'],
                                        'due_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'example' => '2026-03-15'],
                                        'total_amount' => ['type' => 'number', 'format' => 'float', 'example' => 1200000],
                                        'discount_amount' => ['type' => 'number', 'format' => 'float', 'example' => 100000],
                                        'paid_amount' => ['type' => 'number', 'format' => 'float', 'example' => 300000],
                                        'remaining_amount' => ['type' => 'number', 'format' => 'float', 'example' => 900000],
                                        'patient' => ['$ref' => '#/components/schemas/MobilePatientMini'],
                                        'branch' => ['$ref' => '#/components/schemas/MobileBranchMini'],
                                    ],
                                    'required' => ['id', 'invoice_no', 'status', 'issued_at', 'due_date', 'total_amount', 'discount_amount', 'paid_amount', 'remaining_amount', 'patient', 'branch'],
                                ],
                                'MobilePatientSummary' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'example' => 1001],
                                        'patient_code' => ['type' => 'string', 'nullable' => true, 'example' => 'PAT-20260201-XYZ'],
                                        'full_name' => ['type' => 'string', 'nullable' => true, 'example' => 'Nguyen Van A'],
                                        'phone' => ['type' => 'string', 'nullable' => true, 'example' => '0901234567'],
                                        'email' => ['type' => 'string', 'nullable' => true, 'example' => 'patient@example.com'],
                                        'gender' => ['type' => 'string', 'nullable' => true, 'example' => 'female'],
                                        'birthday' => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'example' => '1993-11-20'],
                                        'address' => ['type' => 'string', 'nullable' => true, 'example' => '123 Le Loi, Q1'],
                                        'first_visit_reason' => ['type' => 'string', 'nullable' => true, 'example' => 'Niềng răng'],
                                        'branch' => ['$ref' => '#/components/schemas/MobileBranchMini'],
                                        'primary_doctor' => ['$ref' => '#/components/schemas/MobileDoctorMini'],
                                        'wallet' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'balance' => ['type' => 'number', 'format' => 'float', 'example' => 250000],
                                                'total_deposit' => ['type' => 'number', 'format' => 'float', 'example' => 1500000],
                                                'total_spent' => ['type' => 'number', 'format' => 'float', 'example' => 1250000],
                                            ],
                                            'required' => ['balance', 'total_deposit', 'total_spent'],
                                        ],
                                        'risk_profile' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'risk_level' => ['type' => 'string', 'nullable' => true, 'example' => 'medium'],
                                                'no_show_score' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'example' => 0.45],
                                                'churn_score' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'example' => 0.25],
                                                'as_of_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'example' => '2026-03-01'],
                                            ],
                                            'required' => ['risk_level', 'no_show_score', 'churn_score', 'as_of_date'],
                                        ],
                                    ],
                                    'required' => ['id', 'patient_code', 'full_name', 'phone', 'email', 'gender', 'birthday', 'address', 'first_visit_reason', 'branch', 'primary_doctor', 'wallet', 'risk_profile'],
                                ],
                                'SuccessEnvelopeAuthToken' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => ['$ref' => '#/components/schemas/MobileAuthTokenData'],
                                    ],
                                    'required' => ['success', 'data'],
                                ],
                                'SuccessEnvelopeMessage' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => ['$ref' => '#/components/schemas/SuccessMessageData'],
                                    ],
                                    'required' => ['success', 'data'],
                                ],
                                'SuccessEnvelopeAppointmentList' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/MobileAppointment'],
                                        ],
                                        'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                                    ],
                                    'required' => ['success', 'data', 'meta'],
                                ],
                                'SuccessEnvelopeInvoiceList' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => [
                                            'type' => 'array',
                                            'items' => ['$ref' => '#/components/schemas/MobileInvoiceSummary'],
                                        ],
                                        'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                                    ],
                                    'required' => ['success', 'data', 'meta'],
                                ],
                                'SuccessEnvelopePatientSummary' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => ['$ref' => '#/components/schemas/MobilePatientSummary'],
                                    ],
                                    'required' => ['success', 'data'],
                                ],
                            ], $errorSchemas),
                        ],
                        'paths' => [
                            '/api/v1/mobile/auth/token' => [
                                'post' => [
                                    'tags' => ['Auth'],
                                    'summary' => 'Đăng nhập mobile và cấp bearer token',
                                    'security' => [],
                                    'requestBody' => [
                                        'required' => true,
                                        'content' => [
                                            'application/json' => [
                                                'schema' => ['$ref' => '#/components/schemas/MobileAuthTokenRequest'],
                                                'examples' => [
                                                    'default' => [
                                                        'value' => [
                                                            'email' => 'doctor.user@example.com',
                                                            'password' => 'secret123',
                                                            'device_name' => 'iPhone 15 Pro',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'responses' => [
                                        '200' => [
                                            'description' => 'Đăng nhập thành công',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/SuccessEnvelopeAuthToken'],
                                                ],
                                            ],
                                        ],
                                        '422' => [
                                            'description' => 'Sai thông tin đăng nhập hoặc lỗi validation',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorValidation'],
                                                ],
                                            ],
                                        ],
                                        '429' => [
                                            'description' => 'Vượt giới hạn request',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorRateLimited'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'delete' => [
                                    'tags' => ['Auth'],
                                    'summary' => 'Thu hồi token hiện tại',
                                    'security' => [['bearerAuth' => []]],
                                    'responses' => [
                                        '200' => [
                                            'description' => 'Đăng xuất thành công',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/SuccessEnvelopeMessage'],
                                                ],
                                            ],
                                        ],
                                        '401' => [
                                            'description' => 'Thiếu hoặc sai bearer token',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorUnauthorized'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '/api/v1/mobile/appointments' => [
                                'get' => [
                                    'tags' => ['Appointments'],
                                    'summary' => 'Danh sách lịch hẹn theo branch access',
                                    'security' => [['bearerAuth' => []]],
                                    'parameters' => [
                                        ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
                                        ['name' => 'date_from', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                                        ['name' => 'date_to', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                                        ['name' => 'branch_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                                        ['name' => 'patient_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                                        ['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                                    ],
                                    'responses' => [
                                        '200' => [
                                            'description' => 'Danh sách lịch hẹn',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/SuccessEnvelopeAppointmentList'],
                                                ],
                                            ],
                                        ],
                                        '401' => [
                                            'description' => 'Thiếu hoặc sai bearer token',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorUnauthorized'],
                                                ],
                                            ],
                                        ],
                                        '422' => [
                                            'description' => 'Lỗi validation query params',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorValidation'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '/api/v1/mobile/patients/{patient}' => [
                                'get' => [
                                    'tags' => ['Patients'],
                                    'summary' => 'Thông tin tóm tắt bệnh nhân',
                                    'security' => [['bearerAuth' => []]],
                                    'parameters' => [
                                        ['name' => 'patient', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                                    ],
                                    'responses' => [
                                        '200' => [
                                            'description' => 'Chi tiết tóm tắt bệnh nhân',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/SuccessEnvelopePatientSummary'],
                                                ],
                                            ],
                                        ],
                                        '401' => [
                                            'description' => 'Thiếu hoặc sai bearer token',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorUnauthorized'],
                                                ],
                                            ],
                                        ],
                                        '403' => [
                                            'description' => 'Không có quyền truy cập patient thuộc branch khác',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorForbidden'],
                                                ],
                                            ],
                                        ],
                                        '404' => [
                                            'description' => 'Không tìm thấy bệnh nhân',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorNotFound'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '/api/v1/mobile/invoices' => [
                                'get' => [
                                    'tags' => ['Invoices'],
                                    'summary' => 'Danh sách tổng hợp hóa đơn theo branch access',
                                    'security' => [['bearerAuth' => []]],
                                    'parameters' => [
                                        ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
                                        ['name' => 'date_from', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                                        ['name' => 'date_to', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                                        ['name' => 'branch_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                                        ['name' => 'patient_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                                        ['name' => 'status', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                                    ],
                                    'responses' => [
                                        '200' => [
                                            'description' => 'Danh sách hóa đơn',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/SuccessEnvelopeInvoiceList'],
                                                ],
                                            ],
                                        ],
                                        '401' => [
                                            'description' => 'Thiếu hoặc sai bearer token',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorUnauthorized'],
                                                ],
                                            ],
                                        ],
                                        '422' => [
                                            'description' => 'Lỗi validation query params',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorValidation'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '/api/v1/mobile/openapi' => [
                                'get' => [
                                    'tags' => ['Meta'],
                                    'summary' => 'Lấy OpenAPI contract hiện hành',
                                    'security' => [['bearerAuth' => []]],
                                    'responses' => [
                                        '200' => [
                                            'description' => 'OpenAPI contract',
                                        ],
                                        '401' => [
                                            'description' => 'Thiếu hoặc sai bearer token',
                                            'content' => [
                                                'application/json' => [
                                                    'schema' => ['$ref' => '#/components/schemas/ErrorUnauthorized'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]);
                })->name('api.v1.mobile.openapi');
            });
    });
