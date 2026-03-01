<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MobilePatientSummaryResource;
use App\Models\Patient;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;

class MobilePatientSummaryController extends Controller
{
    public function show(Patient $patient): JsonResponse
    {
        BranchAccess::assertCanAccessBranch(
            branchId: $patient->first_branch_id ? (int) $patient->first_branch_id : null,
            field: 'patient_id',
            message: 'Bạn không có quyền truy cập hồ sơ bệnh nhân ở chi nhánh này.',
        );

        $patient->loadMissing([
            'branch:id,name',
            'primaryDoctor:id,name',
            'wallet:id,patient_id,balance,total_deposit,total_spent',
            'latestRiskProfile:id,patient_id,risk_level,no_show_score,churn_score,as_of_date',
        ]);

        return response()->json([
            'success' => true,
            'data' => MobilePatientSummaryResource::make($patient)->resolve(),
        ]);
    }
}
