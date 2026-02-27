<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWebLeadRequest;
use App\Services\WebLeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class WebLeadController extends Controller
{
    public function __construct(
        protected WebLeadIngestionService $webLeadIngestionService,
    ) {}

    public function store(StoreWebLeadRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->webLeadIngestionService->ingest(
            payload: Arr::except($validated, ['idempotency_key']),
            requestId: (string) $validated['idempotency_key'],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $customer = $result['customer'];
        $statusCode = ($result['created'] && ! $result['replayed']) ? 201 : 200;

        return response()->json([
            'message' => $result['created'] ? 'Lead đã được tạo.' : 'Lead đã được hợp nhất.',
            'data' => [
                'request_id' => $result['ingestion']->request_id,
                'customer_id' => $customer->id,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
                'status' => $customer->status,
                'branch_id' => $customer->branch_id,
                'created' => $result['created'],
                'replayed' => $result['replayed'],
            ],
        ], $statusCode);
    }
}
