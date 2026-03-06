<?php

namespace App\Filament\Resources\TreatmentPlans\Pages;

use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Services\TreatmentAssignmentAuthorizer;
use App\Services\TreatmentPlanWorkflowService;
use App\Support\BranchAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CreateTreatmentPlan extends CreateRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    public ?string $returnUrl = null;

    public function mount(): void
    {
        parent::mount();

        $this->returnUrl = $this->sanitizeReturnUrl(request()->query('return_url'));

        if ($this->returnUrl !== null) {
            return;
        }

        $patientId = request()->integer('patient_id');
        if ($patientId > 0) {
            $this->returnUrl = route('filament.admin.resources.patients.view', [
                'record' => $patientId,
                'tab' => 'exam-treatment',
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (is_numeric($data['branch_id'] ?? null)) {
            BranchAccess::assertCanAccessBranch(
                branchId: (int) $data['branch_id'],
                field: 'branch_id',
                message: 'Bạn không thể tạo kế hoạch điều trị ở chi nhánh ngoài phạm vi được phân quyền.',
            );
        }

        $data = app(TreatmentAssignmentAuthorizer::class)->sanitizeTreatmentPlanFormData(auth()->user(), $data);

        return app(TreatmentPlanWorkflowService::class)->prepareCreatePayload($data);
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
