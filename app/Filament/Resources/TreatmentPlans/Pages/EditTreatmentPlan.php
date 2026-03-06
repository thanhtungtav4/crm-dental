<?php

namespace App\Filament\Resources\TreatmentPlans\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use App\Services\TreatmentAssignmentAuthorizer;
use App\Services\TreatmentDeletionGuardService;
use App\Services\TreatmentPlanWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditTreatmentPlan extends EditRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    public ?string $returnUrl = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->returnUrl = $this->sanitizeReturnUrl(request()->query('return_url'));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = app(TreatmentAssignmentAuthorizer::class)->sanitizeTreatmentPlanFormData(auth()->user(), $data);

        return app(TreatmentPlanWorkflowService::class)->prepareEditablePayload($this->getRecord(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_plan')
                ->label('Duyệt kế hoạch')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->requiresConfirmation()
                ->successNotificationTitle('Đã duyệt kế hoạch điều trị')
                ->visible(fn (): bool => TreatmentPlan::canTransitionStatus($this->getRecord()->status, TreatmentPlan::STATUS_APPROVED))
                ->action(function (): void {
                    app(TreatmentPlanWorkflowService::class)->approve($this->getRecord());
                    $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                }),
            Action::make('start_plan')
                ->label('Bắt đầu điều trị')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->successNotificationTitle('Đã chuyển kế hoạch sang đang thực hiện')
                ->visible(fn (): bool => TreatmentPlan::canTransitionStatus($this->getRecord()->status, TreatmentPlan::STATUS_IN_PROGRESS))
                ->action(function (): void {
                    app(TreatmentPlanWorkflowService::class)->start($this->getRecord());
                    $this->refreshFormData(['status', 'actual_start_date']);
                }),
            Action::make('complete_plan')
                ->label('Hoàn thành')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->successNotificationTitle('Đã hoàn thành kế hoạch điều trị')
                ->visible(fn (): bool => TreatmentPlan::canTransitionStatus($this->getRecord()->status, TreatmentPlan::STATUS_COMPLETED))
                ->action(function (): void {
                    app(TreatmentPlanWorkflowService::class)->complete($this->getRecord());
                    $this->refreshFormData(['status', 'actual_end_date', 'progress_percentage']);
                }),
            Action::make('cancel_plan')
                ->label('Hủy kế hoạch')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('reason')
                        ->label('Lý do hủy')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->successNotificationTitle('Đã hủy kế hoạch điều trị')
                ->visible(fn (): bool => TreatmentPlan::canTransitionStatus($this->getRecord()->status, TreatmentPlan::STATUS_CANCELLED))
                ->action(function (array $data): void {
                    app(TreatmentPlanWorkflowService::class)->cancel($this->getRecord(), $data['reason'] ?? null);
                    $this->refreshFormData(['status']);
                }),
            Action::make('open_patient_exam_treatment')
                ->label('Về hồ sơ BN')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->url(fn (): ?string => $this->resolvePatientExamTreatmentUrl())
                ->visible(fn (): bool => filled($this->resolvePatientExamTreatmentUrl())),
            DeleteAction::make()
                ->visible(fn (): bool => app(TreatmentDeletionGuardService::class)->canDeleteTreatmentPlan($this->getRecord()))
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
        $patientId = is_numeric($this->record?->patient_id ?? null)
            ? (int) $this->record->patient_id
            : null;

        if (! $patientId) {
            return null;
        }

        return PatientResource::getUrl('view', [
            'record' => $patientId,
            'tab' => 'exam-treatment',
        ]);
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
