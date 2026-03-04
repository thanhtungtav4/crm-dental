<?php

namespace App\Filament\Resources\TreatmentSessions\Pages;

use App\Filament\Resources\TreatmentSessions\TreatmentSessionResource;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Support\BranchAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateTreatmentSession extends CreateRecord
{
    protected static string $resource = TreatmentSessionResource::class;

    public ?string $returnUrl = null;

    public function mount(): void
    {
        parent::mount();

        $this->returnUrl = $this->sanitizeReturnUrl(request()->query('return_url'));

        if ($this->returnUrl !== null) {
            return;
        }

        $patientId = request()->integer('patient_id');

        if ($patientId <= 0) {
            $treatmentPlanId = request()->integer('treatment_plan_id');

            if ($treatmentPlanId > 0) {
                $patientId = (int) (TreatmentPlan::query()->whereKey($treatmentPlanId)->value('patient_id') ?? 0);
            }
        }

        if ($patientId > 0) {
            $this->returnUrl = route('filament.admin.resources.patients.view', [
                'record' => $patientId,
                'tab' => 'exam-treatment',
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $planId = is_numeric($data['treatment_plan_id'] ?? null)
            ? (int) $data['treatment_plan_id']
            : null;

        if ($planId === null) {
            throw ValidationException::withMessages([
                'treatment_plan_id' => 'Vui lòng chọn kế hoạch điều trị trước khi lưu phiên.',
            ]);
        }

        $plan = TreatmentPlan::query()
            ->select(['id', 'branch_id', 'doctor_id'])
            ->find($planId);

        if (! $plan) {
            throw ValidationException::withMessages([
                'treatment_plan_id' => 'Kế hoạch điều trị không tồn tại hoặc đã bị xoá.',
            ]);
        }

        BranchAccess::assertCanAccessBranch(
            branchId: $plan->branch_id !== null ? (int) $plan->branch_id : null,
            field: 'treatment_plan_id',
            message: 'Bạn không thể tạo phiên điều trị cho kế hoạch thuộc chi nhánh ngoài phạm vi được phân quyền.',
        );

        if (! is_numeric($data['doctor_id'] ?? null) && $plan->doctor_id !== null) {
            $data['doctor_id'] = (int) $plan->doctor_id;
        }

        $planItemId = is_numeric($data['plan_item_id'] ?? null)
            ? (int) $data['plan_item_id']
            : null;

        if ($planItemId === null) {
            $planItemId = PlanItem::query()
                ->where('treatment_plan_id', $planId)
                ->orderBy('id')
                ->value('id');
        }

        if (! $planItemId) {
            throw ValidationException::withMessages([
                'plan_item_id' => 'Kế hoạch này chưa có hạng mục điều trị. Hãy tạo hạng mục trước khi thêm ngày điều trị.',
            ]);
        }

        $isPlanItemValid = PlanItem::query()
            ->whereKey($planItemId)
            ->where('treatment_plan_id', $planId)
            ->exists();

        if (! $isPlanItemValid) {
            throw ValidationException::withMessages([
                'plan_item_id' => 'Hạng mục điều trị không thuộc kế hoạch đã chọn.',
            ]);
        }

        $data['plan_item_id'] = $planItemId;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->resolveReturnUrl() ?? static::getResource()::getUrl('index');
    }

    private function resolveReturnUrl(): ?string
    {
        return $this->sanitizeReturnUrl($this->returnUrl);
    }

    private function sanitizeReturnUrl(mixed $returnUrl): ?string
    {
        if (! is_string($returnUrl) || trim($returnUrl) === '') {
            return null;
        }

        $candidateUrl = trim($returnUrl);

        if (str_starts_with($candidateUrl, '/')) {
            $candidateUrl = url($candidateUrl);
        } else {
            $appBaseUrl = rtrim(url('/'), '/');
            if (! ($candidateUrl === $appBaseUrl || str_starts_with($candidateUrl, $appBaseUrl.'/'))) {
                return null;
            }
        }

        $parsedUrl = parse_url($candidateUrl);
        if ($parsedUrl === false) {
            return null;
        }

        $path = '/'.ltrim((string) ($parsedUrl['path'] ?? '/'), '/');
        if ($this->isDisallowedReturnPath($path)) {
            return null;
        }

        $pathWithQuery = $path.(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');
        if (! $this->isGetAccessiblePath($pathWithQuery)) {
            return null;
        }

        return $candidateUrl;
    }

    private function isDisallowedReturnPath(string $path): bool
    {
        return $path === '/livewire/update' || str_starts_with($path, '/livewire/');
    }

    private function isGetAccessiblePath(string $pathWithQuery): bool
    {
        try {
            app('router')->getRoutes()->match(Request::create($pathWithQuery, Request::METHOD_GET));

            return true;
        } catch (MethodNotAllowedHttpException|NotFoundHttpException) {
            return false;
        }
    }
}
