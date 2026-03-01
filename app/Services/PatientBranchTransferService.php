<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchTransferRequest;
use App\Models\Patient;
use App\Models\User;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientBranchTransferService
{
    public function requestTransfer(
        Patient $patient,
        int $toBranchId,
        ?int $actorId = null,
        ?string $reason = null,
        ?string $note = null,
    ): BranchTransferRequest {
        ActionGate::authorize(
            ActionPermission::PATIENT_BRANCH_TRANSFER,
            'Bạn không có quyền chuyển bệnh nhân liên chi nhánh.',
        );

        $this->assertTargetBranch($patient, $toBranchId, $actorId);

        $pendingExists = BranchTransferRequest::query()
            ->where('patient_id', $patient->id)
            ->where('to_branch_id', $toBranchId)
            ->where('status', BranchTransferRequest::STATUS_PENDING)
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Yêu cầu chuyển chi nhánh này đang chờ xử lý.',
            ]);
        }

        return BranchTransferRequest::query()->create([
            'patient_id' => $patient->id,
            'from_branch_id' => $patient->first_branch_id,
            'to_branch_id' => $toBranchId,
            'status' => BranchTransferRequest::STATUS_PENDING,
            'requested_by' => $actorId ?? auth()->id(),
            'requested_at' => now(),
            'reason' => $reason,
            'note' => $note,
        ]);
    }

    public function applyTransferRequest(BranchTransferRequest $transferRequest, ?int $actorId = null): BranchTransferRequest
    {
        ActionGate::authorize(
            ActionPermission::PATIENT_BRANCH_TRANSFER,
            'Bạn không có quyền chuyển bệnh nhân liên chi nhánh.',
        );

        return DB::transaction(function () use ($transferRequest, $actorId): BranchTransferRequest {
            $request = BranchTransferRequest::query()
                ->lockForUpdate()
                ->findOrFail($transferRequest->id);

            if ($request->status !== BranchTransferRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Yêu cầu chuyển chi nhánh không còn ở trạng thái chờ xử lý.',
                ]);
            }

            $patient = Patient::query()
                ->lockForUpdate()
                ->findOrFail($request->patient_id);

            $this->assertTargetBranch($patient, (int) $request->to_branch_id, $actorId);

            $patient->branchTransferLogNote = $this->buildTransferLogNote(
                reason: $request->reason,
                note: $request->note,
            );
            $patient->branchTransferActorId = $actorId ?? auth()->id();
            $patient->first_branch_id = $request->to_branch_id;
            $patient->updated_by = $actorId ?? auth()->id();
            $patient->save();

            $request->status = BranchTransferRequest::STATUS_APPLIED;
            $request->decided_by = $actorId ?? auth()->id();
            $request->decided_at = now();
            $request->applied_at = now();
            $request->metadata = array_merge($request->metadata ?? [], [
                'old_branch_id' => $request->from_branch_id,
                'new_branch_id' => $request->to_branch_id,
            ]);
            $request->save();

            AuditLog::record(
                entityType: AuditLog::ENTITY_BRANCH_TRANSFER,
                entityId: $request->id,
                action: AuditLog::ACTION_TRANSFER,
                actorId: $actorId ?? auth()->id(),
                metadata: [
                    'patient_id' => $patient->id,
                    'from_branch_id' => $request->from_branch_id,
                    'to_branch_id' => $request->to_branch_id,
                    'reason' => $request->reason,
                ],
            );

            return $request->fresh();
        }, attempts: 3);
    }

    public function transferDirect(
        Patient $patient,
        int $toBranchId,
        ?int $actorId = null,
        ?string $reason = null,
        ?string $note = null,
    ): BranchTransferRequest {
        $request = $this->requestTransfer(
            patient: $patient,
            toBranchId: $toBranchId,
            actorId: $actorId,
            reason: $reason,
            note: $note,
        );

        return $this->applyTransferRequest($request, $actorId);
    }

    public function rejectTransferRequest(BranchTransferRequest $transferRequest, ?int $actorId = null, ?string $note = null): BranchTransferRequest
    {
        ActionGate::authorize(
            ActionPermission::PATIENT_BRANCH_TRANSFER,
            'Bạn không có quyền chuyển bệnh nhân liên chi nhánh.',
        );

        if ($transferRequest->status !== BranchTransferRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Yêu cầu chuyển chi nhánh không còn ở trạng thái chờ xử lý.',
            ]);
        }

        $transferRequest->status = BranchTransferRequest::STATUS_REJECTED;
        $transferRequest->decided_by = $actorId ?? auth()->id();
        $transferRequest->decided_at = now();
        $transferRequest->note = trim(implode(' | ', array_filter([$transferRequest->note, $note])));
        $transferRequest->save();

        return $transferRequest;
    }

    protected function assertTargetBranch(Patient $patient, int $toBranchId, ?int $actorId = null): void
    {
        if ((int) ($patient->first_branch_id ?? 0) === $toBranchId) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Bệnh nhân đã thuộc chi nhánh này.',
            ]);
        }

        $branch = Branch::query()
            ->whereKey($toBranchId)
            ->where('active', true)
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Chi nhánh nhận không hợp lệ hoặc đã ngưng hoạt động.',
            ]);
        }

        $actor = $this->resolveActor($actorId);

        if (
            $actor instanceof User
            && ! $actor->hasRole('Admin')
            && ! $actor->canAccessBranch($toBranchId)
        ) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Bạn không có quyền chuyển bệnh nhân sang chi nhánh này.',
            ]);
        }
    }

    protected function buildTransferLogNote(?string $reason = null, ?string $note = null): string
    {
        $payload = array_filter([
            filled($reason) ? 'Ly do: '.$reason : null,
            filled($note) ? 'Ghi chu: '.$note : null,
        ]);

        if ($payload === []) {
            return 'Chuyển chi nhánh';
        }

        return 'Chuyển chi nhánh | '.implode(' | ', $payload);
    }

    protected function resolveActor(?int $actorId = null): ?User
    {
        if (is_numeric($actorId)) {
            return User::query()->find((int) $actorId);
        }

        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }
}
