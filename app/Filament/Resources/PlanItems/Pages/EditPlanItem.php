<?php

namespace App\Filament\Resources\PlanItems\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\PlanItems\PlanItemResource;
use App\Models\TreatmentPlan;
use App\Services\TreatmentDeletionGuardService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditPlanItem extends EditRecord
{
    protected static string $resource = PlanItemResource::class;

    public ?string $returnUrl = null;

    public ?int $patientIdContext = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->patientIdContext = request()->integer('patient_id') ?: null;
        $this->assertRecordMatchesPatientContext();

        $this->returnUrl = $this->sanitizeReturnUrl(request()->query('return_url'));

        if ($this->returnUrl === null) {
            $this->returnUrl = $this->resolvePatientExamTreatmentUrl();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_patient_exam_treatment')
                ->label('Về hồ sơ BN')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->url(fn (): ?string => $this->resolvePatientExamTreatmentUrl())
                ->visible(fn (): bool => filled($this->resolvePatientExamTreatmentUrl())),
            DeleteAction::make()
                ->visible(fn (): bool => app(TreatmentDeletionGuardService::class)->canDeletePlanItem($this->getRecord()))
                ->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->resolveReturnUrl() ?? static::getResource()::getUrl('index');
    }

    private function resolveReturnUrl(): ?string
    {
        return $this->sanitizeReturnUrl($this->returnUrl);
    }

    private function resolvePatientExamTreatmentUrl(): ?string
    {
        $patientId = $this->patientIdContext;

        if (! $patientId && is_numeric($this->record?->treatmentPlan?->patient_id ?? null)) {
            $patientId = (int) $this->record->treatmentPlan->patient_id;
        }

        if (! $patientId && is_numeric($this->record?->treatment_plan_id ?? null)) {
            $patientId = (int) (TreatmentPlan::query()
                ->whereKey((int) $this->record->treatment_plan_id)
                ->value('patient_id') ?? 0);
        }

        if (! $patientId) {
            return null;
        }

        return PatientResource::getUrl('view', [
            'record' => $patientId,
            'tab' => 'exam-treatment',
        ]);
    }

    private function assertRecordMatchesPatientContext(): void
    {
        if (! $this->patientIdContext) {
            return;
        }

        $this->record->loadMissing('treatmentPlan:id,patient_id');

        $recordPatientId = is_numeric($this->record?->treatmentPlan?->patient_id ?? null)
            ? (int) $this->record->treatmentPlan->patient_id
            : 0;

        if (! $recordPatientId && is_numeric($this->record?->treatment_plan_id ?? null)) {
            $recordPatientId = (int) (TreatmentPlan::query()
                ->whereKey((int) $this->record->treatment_plan_id)
                ->value('patient_id') ?? 0);
        }

        abort_unless($recordPatientId === $this->patientIdContext, 403);
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
