<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Support\BranchAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $patientIdFromRequest = request()->integer('patient_id') ?: null;
        $planIdFromRequest = request()->integer('treatment_plan_id') ?: null;
        $sessionIdFromRequest = request()->integer('treatment_session_id') ?: null;

        if (empty($data['treatment_session_id']) && $sessionIdFromRequest) {
            $data['treatment_session_id'] = $sessionIdFromRequest;
        }

        if (empty($data['treatment_plan_id']) && $planIdFromRequest) {
            $data['treatment_plan_id'] = $planIdFromRequest;
        }

        if (empty($data['patient_id']) && $patientIdFromRequest) {
            $data['patient_id'] = $patientIdFromRequest;
        }

        if (! empty($data['treatment_session_id'])) {
            $session = TreatmentSession::query()
                ->select(['id', 'treatment_plan_id'])
                ->find((int) $data['treatment_session_id']);

            if ($session) {
                $data['treatment_plan_id'] = $data['treatment_plan_id'] ?: $session->treatment_plan_id;
            }
        }

        if (! empty($data['treatment_plan_id'])) {
            $plan = TreatmentPlan::query()
                ->select(['id', 'patient_id', 'branch_id'])
                ->find((int) $data['treatment_plan_id']);

            if ($plan) {
                $data['patient_id'] = $plan->patient_id;
                $data['branch_id'] = $data['branch_id'] ?? $plan->branch_id;
            }
        }

        if (empty($data['branch_id']) && ! empty($data['patient_id'])) {
            $data['branch_id'] = \App\Models\Patient::query()
                ->whereKey((int) $data['patient_id'])
                ->value('first_branch_id');
        }

        if (is_numeric($data['branch_id'] ?? null)) {
            BranchAccess::assertCanAccessBranch(
                branchId: (int) $data['branch_id'],
                field: 'patient_id',
                message: 'Bạn không thể tạo hóa đơn cho hồ sơ thuộc chi nhánh ngoài phạm vi được phân quyền.',
            );
        }

        $subtotal = max(0, round((float) ($data['subtotal'] ?? 0), 2));
        $discountAmount = max(0, round((float) ($data['discount_amount'] ?? 0), 2));
        $taxAmount = max(0, round((float) ($data['tax_amount'] ?? 0), 2));

        $data['subtotal'] = $subtotal;
        $data['discount_amount'] = $discountAmount;
        $data['tax_amount'] = $taxAmount;
        $data['total_amount'] = Invoice::calculateTotalAmount($subtotal, $discountAmount, $taxAmount);
        $data['paid_amount'] = max(0, round((float) ($data['paid_amount'] ?? 0), 2));
        $data['invoice_no'] = filled($data['invoice_no'] ?? null)
            ? (string) $data['invoice_no']
            : Invoice::generateInvoiceNo();

        $status = (string) ($data['status'] ?? Invoice::STATUS_DRAFT);
        if (! array_key_exists($status, Invoice::statusOptions())) {
            $status = Invoice::STATUS_DRAFT;
        }

        if (in_array($status, [Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID, Invoice::STATUS_OVERDUE], true)) {
            $status = Invoice::STATUS_ISSUED;
        }

        $data['status'] = $status;
        if ($status === Invoice::STATUS_DRAFT) {
            $data['issued_at'] = null;
        } elseif (blank($data['issued_at'] ?? null)) {
            $data['issued_at'] = now();
        }

        return $data;
    }
}
