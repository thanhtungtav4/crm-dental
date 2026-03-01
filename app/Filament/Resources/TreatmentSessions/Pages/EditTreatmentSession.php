<?php

namespace App\Filament\Resources\TreatmentSessions\Pages;

use App\Filament\Resources\TreatmentSessions\TreatmentSessionResource;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Support\BranchAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditTreatmentSession extends EditRecord
{
    protected static string $resource = TreatmentSessionResource::class;

    public ?string $returnUrl = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->returnUrl = $this->sanitizeReturnUrl(request()->query('return_url'));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->resolveReturnUrl() ?? static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $planId = is_numeric($data['treatment_plan_id'] ?? null)
            ? (int) $data['treatment_plan_id']
            : null;

        if ($planId === null) {
            throw ValidationException::withMessages([
                'treatment_plan_id' => 'Vui lòng chọn kế hoạch điều trị.',
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
            message: 'Bạn không thể cập nhật phiên điều trị cho kế hoạch thuộc chi nhánh ngoài phạm vi được phân quyền.',
        );

        $planItemId = is_numeric($data['plan_item_id'] ?? null)
            ? (int) $data['plan_item_id']
            : null;

        if ($planItemId !== null) {
            $isPlanItemValid = PlanItem::query()
                ->whereKey($planItemId)
                ->where('treatment_plan_id', $planId)
                ->exists();

            if (! $isPlanItemValid) {
                throw ValidationException::withMessages([
                    'plan_item_id' => 'Hạng mục điều trị không thuộc kế hoạch đã chọn.',
                ]);
            }
        }

        return $data;
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
