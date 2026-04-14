<?php

namespace App\Filament\Resources\PlanItems\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\PlanItems\PlanItemResource;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Services\PlanItemWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditPlanItem extends EditRecord
{
    protected static string $resource = PlanItemResource::class;

    public ?string $returnUrl = null;

    public ?int $patientIdContext = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(PlanItemWorkflowService::class)->prepareEditablePayload($this->getRecord(), $data);
    }

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
            Action::make('start_treatment')
                ->label('Bắt đầu')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn (): bool => $this->record->canStartTreatment()
                    && $this->record->status === PlanItem::STATUS_PENDING)
                ->form([
                    Textarea::make('reason')
                        ->label('Ghi chú vận hành')
                        ->rows(3),
                ])
                ->successNotificationTitle('Đã bắt đầu hạng mục điều trị')
                ->action(function (array $data): void {
                    app(PlanItemWorkflowService::class)->startTreatment(
                        planItem: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'started_at', 'progress_percentage', 'completed_visits']);
                }),
            Action::make('complete_visit')
                ->label('Hoàn thành 1 lần')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->canStartTreatment()
                    && $this->record->completed_visits < $this->record->required_visits
                    && ! in_array($this->record->status, [PlanItem::STATUS_COMPLETED, PlanItem::STATUS_CANCELLED], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Ghi chú tiến độ')
                        ->rows(3),
                ])
                ->successNotificationTitle('Đã hoàn thành một lần điều trị')
                ->action(function (array $data): void {
                    app(PlanItemWorkflowService::class)->completeVisit(
                        planItem: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'started_at', 'completed_at', 'progress_percentage', 'completed_visits']);
                }),
            Action::make('complete_treatment')
                ->label('Hoàn thành')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->record->canStartTreatment()
                    && ! in_array($this->record->status, [PlanItem::STATUS_COMPLETED, PlanItem::STATUS_CANCELLED], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Ghi chú hoàn thành')
                        ->rows(3),
                ])
                ->successNotificationTitle('Đã hoàn thành hạng mục điều trị')
                ->action(function (array $data): void {
                    app(PlanItemWorkflowService::class)->completeTreatment(
                        planItem: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'started_at', 'completed_at', 'progress_percentage', 'completed_visits']);
                }),
            Action::make('cancel_treatment')
                ->label('Hủy')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [PlanItem::STATUS_PENDING, PlanItem::STATUS_IN_PROGRESS], true))
                ->form([
                    Textarea::make('reason')
                        ->label('Lý do hủy')
                        ->rows(3)
                        ->required(),
                ])
                ->successNotificationTitle('Đã hủy hạng mục điều trị')
                ->action(function (array $data): void {
                    app(PlanItemWorkflowService::class)->cancel(
                        planItem: $this->getRecord(),
                        reason: $data['reason'] ?? null,
                    );

                    $this->refreshFormData(['status', 'completed_at']);
                }),
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
