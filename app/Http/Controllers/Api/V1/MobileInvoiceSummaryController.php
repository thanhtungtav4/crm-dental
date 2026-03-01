<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobileIndexRequest;
use App\Http\Resources\Api\V1\MobileInvoiceSummaryResource;
use App\Models\Invoice;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class MobileInvoiceSummaryController extends Controller
{
    public function index(MobileIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $branchIds = BranchAccess::accessibleBranchIds($request->user());

        $query = Invoice::query()
            ->with(['patient:id,patient_code,full_name,phone', 'branch:id,name'])
            ->latest('created_at');

        if ($branchIds === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('branch_id', $branchIds);
        }

        if (filled($validated['branch_id'] ?? null)) {
            $requestedBranchId = (int) $validated['branch_id'];

            if (! in_array($requestedBranchId, $branchIds, true)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('branch_id', $requestedBranchId);
            }
        }

        if (filled($validated['patient_id'] ?? null)) {
            $query->where('patient_id', (int) $validated['patient_id']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', (string) $validated['status']);
        }

        if (filled($validated['date_from'] ?? null)) {
            $query->whereDate('issued_at', '>=', (string) $validated['date_from']);
        }

        if (filled($validated['date_to'] ?? null)) {
            $query->whereDate('issued_at', '<=', (string) $validated['date_to']);
        }

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return response()->json([
            'success' => true,
            'data' => MobileInvoiceSummaryResource::collection($paginator->getCollection())->resolve(),
            'meta' => Arr::only($paginator->toArray(), [
                'current_page',
                'from',
                'to',
                'last_page',
                'per_page',
                'total',
            ]),
        ]);
    }
}
