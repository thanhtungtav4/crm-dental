<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IdempotencyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AmendClinicalNoteRequest;
use App\Models\ClinicalNote;
use App\Services\InternalEmrMutationService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class InternalEmrMutationController extends Controller
{
    public function __construct(
        protected InternalEmrMutationService $internalEmrMutationService,
    ) {}

    public function amendClinicalNote(AmendClinicalNoteRequest $request, ClinicalNote $clinicalNote): JsonResponse
    {
        ActionGate::authorize(
            ActionPermission::EMR_CLINICAL_WRITE,
            'Bạn không có quyền cập nhật dữ liệu lâm sàng EMR.',
        );

        $validated = $request->validated();

        try {
            $result = $this->internalEmrMutationService->amendClinicalNote(
                clinicalNote: $clinicalNote,
                payload: Arr::except($validated, ['idempotency_key']),
                requestId: (string) $validated['idempotency_key'],
                actorId: auth()->id(),
            );
        } catch (IdempotencyConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => $result['replayed']
                ? 'Yêu cầu đã được replay từ cache idempotency.'
                : 'Đã cập nhật phiếu khám.',
            'data' => [
                'request_id' => $result['mutation']->request_id,
                'mutation_type' => $result['mutation']->mutation_type,
                'replayed' => $result['replayed'],
                'clinical_note' => $result['data'],
            ],
        ], (int) $result['status_code']);
    }
}
