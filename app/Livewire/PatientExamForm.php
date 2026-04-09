<?php

namespace App\Livewire;

use App\Models\ClinicalNote;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientExamClinicalNoteWorkflowService;
use App\Services\PatientExamDoctorReadModelService;
use App\Services\PatientExamIndicationStateService;
use App\Services\PatientExamMediaReadModelService;
use App\Services\PatientExamMediaWorkflowService;
use App\Services\PatientExamReferenceReadModelService;
use App\Services\PatientExamSessionReadModelService;
use App\Services\PatientExamSessionWorkflowService;
use App\Services\PatientExamStatusReadModelService;
use App\Services\PatientOverviewReadModelService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use App\Support\DentitionModeResolver;
use App\Support\ToothChartViewConfig;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class PatientExamForm extends Component
{
    use WithFileUploads;

    protected const ADULT_UPPER_TEETH = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];

    protected const CHILD_UPPER_TEETH = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];

    protected const CHILD_LOWER_TEETH = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

    protected const ADULT_LOWER_TEETH = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];

    public Patient $patient;

    public ?ClinicalNote $clinicalNote = null;

    public ?ExamSession $examSession = null;

    public ?int $activeSessionId = null;

    public ?string $newSessionDate = null;

    public ?int $editingSessionId = null;

    public ?string $editingSessionDate = null;

    public int $clinicalNoteVersion = 1;

    // Form fields
    public ?int $examining_doctor_id = null;

    public ?int $treating_doctor_id = null;

    public ?string $general_exam_notes = '';

    public ?string $treatment_plan_note = '';

    // Indications (checkboxes)
    public array $indications = [];

    // Image uploads per indication type
    public array $indicationImages = [];

    public array $tempUploads = [];

    // Indication types configuration
    public array $indicationTypes = [];

    // For doctor search
    public string $examiningDoctorSearch = '';

    public string $treatingDoctorSearch = '';

    public bool $showExaminingDoctorDropdown = false;

    public bool $showTreatingDoctorDropdown = false;

    public ?string $other_diagnosis = '';

    public array $tooth_diagnosis_data = [];

    public string $dentition_mode = DentitionModeResolver::MODE_AUTO;

    public function mount(Patient $patient): void
    {
        $this->patient = $patient;
        $this->indicationTypes = ClinicRuntimeSettings::examIndicationOptions();
        $this->newSessionDate = now()->toDateString();

        $latestSession = $this->getSessionQuery()->first();

        if ($latestSession) {
            $this->setActiveSession($latestSession->id);

            return;
        }

        $this->resetExamForm();
    }

    public function createSession(): void
    {
        $this->authorizeClinicalWrite();

        $validated = $this->validate([
            'newSessionDate' => ['required', 'date'],
        ]);

        $result = $this->patientExamSessionWorkflowService()->openSession(
            patient: $this->patient,
            sessionDate: (string) $validated['newSessionDate'],
            doctorId: Auth::id() ?: null,
        );

        if (($result['status'] ?? null) === 'unavailable' || ! isset($result['session'])) {
            Notification::make()
                ->title('Không thể khởi tạo phiếu khám cho ngày đã chọn.')
                ->danger()
                ->send();

            return;
        }

        /** @var ExamSession $session */
        $session = $result['session'];

        if (($result['status'] ?? null) === 'existing') {
            $this->setActiveSession($session->id);

            Notification::make()
                ->title('Ngày khám đã tồn tại, đã chuyển về phiếu hiện có')
                ->warning()
                ->send();

            return;
        }

        $this->setActiveSession($session->id);

        Notification::make()
            ->title('Đã mở phiếu khám mới')
            ->body('Phiếu khám chỉ được lưu khi bạn bắt đầu nhập dữ liệu lâm sàng.')
            ->success()
            ->send();
    }

    public function setActiveSession(int $sessionId): void
    {
        $session = $this->patient->examSessions()
            ->with('clinicalNote')
            ->find($sessionId);

        if (! $session) {
            return;
        }

        $note = $session->clinicalNote;

        if (! $note) {
            $note = $this->patientExamClinicalNoteWorkflowService()->draftForSession(
                patient: $this->patient,
                session: $session,
                actor: Auth::user(),
            );
        }

        $this->examSession = $session;
        $this->clinicalNote = $note;
        $this->activeSessionId = $session->id;
        $this->editingSessionId = null;
        $this->editingSessionDate = null;

        $this->hydrateFormFromSession($note);
    }

    public function startEditingSession(int $sessionId): void
    {
        $session = $this->patient->examSessions()->find($sessionId);

        if (! $session) {
            return;
        }

        if ($this->isSessionLockedByProgress($session)) {
            Notification::make()
                ->title('Ngày khám đã có tiến trình điều trị nên không thể chỉnh sửa.')
                ->danger()
                ->send();

            return;
        }

        $this->setActiveSession($session->id);
        $this->editingSessionId = $session->id;
        $this->editingSessionDate = $session->session_date?->format('Y-m-d');
    }

    public function cancelEditingSession(): void
    {
        $this->editingSessionId = null;
        $this->editingSessionDate = null;
    }

    public function saveEditingSession(): void
    {
        if (! $this->editingSessionId) {
            return;
        }

        $this->authorizeClinicalWrite();

        $this->validate([
            'editingSessionDate' => ['required', 'date'],
        ]);

        try {
            $result = $this->patientExamSessionWorkflowService()->rescheduleSession(
                patient: $this->patient,
                sessionId: $this->editingSessionId,
                newDate: (string) $this->editingSessionDate,
                actorId: Auth::id() ?: null,
                doctorId: $this->examining_doctor_id ?: (Auth::id() ?: null),
            );
        } catch (ValidationException $exception) {
            $errorMessage = (string) (collect($exception->errors())->flatten()->first()
                ?? 'Phiếu khám đã thay đổi. Vui lòng tải lại dữ liệu mới nhất.');

            Notification::make()
                ->title($errorMessage)
                ->danger()
                ->send();

            if ($this->editingSessionId) {
                $this->setActiveSession($this->editingSessionId);
            }

            return;
        }

        if (($result['status'] ?? null) === 'missing') {
            $this->cancelEditingSession();

            return;
        }

        if (($result['status'] ?? null) === 'duplicate') {
            Notification::make()
                ->title('Ngày khám đã tồn tại. Vui lòng chọn ngày khác.')
                ->warning()
                ->send();

            return;
        }

        if (($result['clinicalNote'] ?? null) instanceof ClinicalNote) {
            $this->clinicalNote = $result['clinicalNote'];
        }

        /** @var ExamSession $session */
        $session = $result['session'];

        $this->setActiveSession($session->id);

        Notification::make()
            ->title('Đã cập nhật ngày khám')
            ->success()
            ->send();
    }

    public function deleteSession(int $sessionId): void
    {
        $this->authorizeClinicalWrite();
        $deleted = false;

        try {
            $deleted = $this->patientExamSessionWorkflowService()->deleteSession($this->patient, $sessionId);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title((string) (collect($exception->errors())->flatten()->first() ?? 'Không thể xóa phiếu khám.'))
                ->danger()
                ->send();

            return;
        }

        if (! $deleted) {
            return;
        }

        if ($this->activeSessionId === $sessionId) {
            $nextSession = $this->getSessionQuery()->first();

            if ($nextSession) {
                $this->setActiveSession($nextSession->id);
            } else {
                $this->clinicalNote = null;
                $this->examSession = null;
                $this->activeSessionId = null;
                $this->resetExamForm();
            }
        }

        Notification::make()
            ->title('Đã xóa phiếu khám')
            ->success()
            ->send();
    }

    public function clearExaminingDoctor(): void
    {
        $this->examining_doctor_id = null;
        $this->examiningDoctorSearch = '';
        $this->showExaminingDoctorDropdown = false;

        $this->saveData();
    }

    public function clearTreatingDoctor(): void
    {
        $this->treating_doctor_id = null;
        $this->treatingDoctorSearch = '';
        $this->showTreatingDoctorDropdown = false;

        $this->saveData();
    }

    /**
     * @return Collection<int, User>
     */
    public function getDoctors(string $search = ''): Collection
    {
        return $this->patientExamDoctorReadModelService()->options(
            actor: Auth::user(),
            branchId: $this->resolveDoctorAssignmentBranchId(),
            search: $search,
        );
    }

    public function selectExaminingDoctor(int $id): void
    {
        $doctor = $this->findAssignableDoctor($id);

        if (! $doctor) {
            $this->notifyDoctorSelectionOutOfScope();

            return;
        }

        $this->examining_doctor_id = $doctor->id;
        $this->showExaminingDoctorDropdown = false;
        $this->examiningDoctorSearch = $doctor?->name ?? '';

        $this->saveData();
    }

    public function selectTreatingDoctor(int $id): void
    {
        $doctor = $this->findAssignableDoctor($id);

        if (! $doctor) {
            $this->notifyDoctorSelectionOutOfScope();

            return;
        }

        $this->treating_doctor_id = $doctor->id;
        $this->showTreatingDoctorDropdown = false;
        $this->treatingDoctorSearch = $doctor?->name ?? '';

        $this->saveData();
    }

    public function toggleIndication(string $type): void
    {
        $state = $this->patientExamIndicationStateService()->toggle(
            indications: $this->indications,
            indicationImages: $this->indicationImages,
            tempUploads: $this->tempUploads,
            type: $type,
        );

        $this->indications = $state['indications'];
        $this->indicationImages = $state['indicationImages'];
        $this->tempUploads = $state['tempUploads'];

        $this->saveData();
    }

    public function updatedTempUploads($value, string $key): void
    {
        $parts = explode('.', $key);
        $type = $this->patientExamIndicationStateService()->normalizeKey($parts[0]);

        if (! isset($this->tempUploads[$type]) || ! is_array($this->tempUploads[$type])) {
            return;
        }

        $result = $this->patientExamMediaWorkflowService()->storeUploads(
            patient: $this->patient,
            session: $this->examSession,
            clinicalNote: $this->clinicalNote,
            payload: $this->patientExamClinicalNoteWorkflowService()->buildPayload(
                patient: $this->patient,
                clinicalNote: $this->clinicalNote,
                session: $this->examSession,
                data: $this->clinicalNotePayloadData(),
                actorId: Auth::id() ?: null,
            ),
            indicationType: $type,
            uploads: $this->tempUploads[$type],
            actor: Auth::user(),
            actorId: Auth::id() ?: null,
        );

        if (($result['clinicalNote'] ?? null) instanceof ClinicalNote) {
            $this->clinicalNote = $result['clinicalNote'];
        }

        if (! isset($this->indicationImages[$type])) {
            $this->indicationImages[$type] = [];
        }

        $this->indicationImages[$type] = [
            ...$this->indicationImages[$type],
            ...($result['paths'] ?? []),
        ];

        $this->tempUploads[$type] = [];
        $this->saveData();
    }

    public function removeImage(string $type, int $index): void
    {
        $normalizedType = $this->patientExamIndicationStateService()->normalizeKey($type);

        if (! isset($this->indicationImages[$normalizedType][$index])) {
            return;
        }

        $path = $this->indicationImages[$normalizedType][$index];

        $this->patientExamMediaWorkflowService()->removeAsset(
            patient: $this->patient,
            session: $this->examSession,
            storagePath: $path,
        );
        unset($this->indicationImages[$normalizedType][$index]);
        $this->indicationImages[$normalizedType] = array_values($this->indicationImages[$normalizedType]);

        $this->saveData();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['general_exam_notes', 'treatment_plan_note', 'other_diagnosis', 'tooth_diagnosis_data'], true)) {
            $this->saveData();
        }
    }

    public function saveData(): void
    {
        if (! $this->examSession) {
            return;
        }

        $this->authorizeClinicalWrite();

        if (! $this->clinicalNote || ! $this->clinicalNote->exists) {
            $result = $this->patientExamClinicalNoteWorkflowService()->saveForSession(
                patient: $this->patient,
                session: $this->examSession,
                clinicalNote: $this->clinicalNote,
                data: $this->clinicalNotePayloadData(),
                expectedVersion: $this->clinicalNoteVersion,
                actor: Auth::user(),
                actorId: Auth::id() ?: null,
            );
            $this->clinicalNote = $result['clinicalNote'] ?? null;

            if (! $this->clinicalNote) {
                return;
            }

            $this->clinicalNoteVersion = (int) ($this->clinicalNote->lock_version ?: 1);
            $this->dispatch('saved');

            return;
        }

        try {
            $result = $this->patientExamClinicalNoteWorkflowService()->saveForSession(
                patient: $this->patient,
                session: $this->examSession,
                clinicalNote: $this->clinicalNote,
                data: $this->clinicalNotePayloadData(),
                expectedVersion: $this->clinicalNoteVersion,
                actor: Auth::user(),
                actorId: Auth::id(),
            );
            $this->clinicalNote = $result['clinicalNote'] ?? $this->clinicalNote;
        } catch (ValidationException $exception) {
            $errorMessage = (string) (collect($exception->errors())->flatten()->first()
                ?? 'Phiếu khám đã thay đổi. Vui lòng tải lại dữ liệu mới nhất.');

            Notification::make()
                ->title($errorMessage)
                ->danger()
                ->send();

            if ($this->activeSessionId) {
                $this->setActiveSession($this->activeSessionId);
            }

            return;
        }

        $this->clinicalNoteVersion = (int) ($this->clinicalNote->lock_version ?: 1);

        $this->dispatch('saved');
    }

    protected function examiningDoctorName(): string
    {
        return $this->patientExamDoctorReadModelService()->name($this->examining_doctor_id);
    }

    protected function treatingDoctorName(): string
    {
        return $this->patientExamDoctorReadModelService()->name($this->treating_doctor_id);
    }

    public function render(): View
    {
        $sessions = $this->patientExamSessionReadModelService()->sessions($this->patient);
        $toothTreatmentStates = $this->buildToothTreatmentStates();
        $selectedIndications = collect($this->patientExamIndicationStateService()->normalizeSelected($this->indications));
        $medicalRecordAction = app(PatientOverviewReadModelService::class)
            ->medicalRecordAction($this->patient, Auth::user());
        $referencePayload = app(PatientExamReferenceReadModelService::class)->toothConditionsPayload();
        $toothChartViewConfig = app(ToothChartViewConfig::class);
        $mediaReadModel = app(PatientExamMediaReadModelService::class)->build(
            patient: $this->patient,
            activeSessionId: $this->activeSessionId,
            selectedIndications: $selectedIndications->all(),
            indicationImages: $this->indicationImages,
            indicationTypes: $this->indicationTypes,
        );

        return view('livewire.patient-exam-form', $this->formViewState(
            sessions: $sessions,
            medicalRecordAction: $medicalRecordAction,
            referencePayload: $referencePayload,
            toothTreatmentStates: $toothTreatmentStates,
            mediaReadModel: $mediaReadModel,
            toothChartViewConfig: $toothChartViewConfig,
        ));
    }

    /**
     * @param  Collection<int, ExamSession>  $sessions
     * @param  array<string, mixed>  $medicalRecordAction
     * @param  array<string, mixed>  $referencePayload
     * @param  array<string, mixed>  $mediaReadModel
     * @param  array<string, mixed>  $toothTreatmentStates
     * @return array<string, mixed>
     */
    protected function formViewState(
        Collection $sessions,
        array $medicalRecordAction,
        array $referencePayload,
        array $toothTreatmentStates,
        array $mediaReadModel,
        ToothChartViewConfig $toothChartViewConfig,
    ): array {
        return [
            ...$this->sessionPanelData($sessions),
            'examiningDoctors' => $this->getDoctors($this->examiningDoctorSearch),
            'treatingDoctors' => $this->getDoctors($this->treatingDoctorSearch),
            'medicalRecordActionUrl' => $medicalRecordAction['url'] ?? null,
            'medicalRecordActionLabel' => $medicalRecordAction['label'] ?? null,
            ...$this->diagnosisViewData(
                referencePayload: $referencePayload,
                toothTreatmentStates: $toothTreatmentStates,
                toothChartViewConfig: $toothChartViewConfig,
            ),
            ...$this->mediaViewData($mediaReadModel),
        ];
    }

    /**
     * @param  Collection<int, ExamSession>  $sessions
     * @return array{
     *     activeSessionBadgeLabel:?string,
     *     sessionCards: array<int, array<string, mixed>>
     * }
     */
    protected function sessionPanelData(Collection $sessions): array
    {
        return [
            'activeSessionBadgeLabel' => $this->activeSessionBadgeLabel(),
            'sessionCards' => $this->sessionCards($sessions),
        ];
    }

    /**
     * @param  array<string, mixed>  $referencePayload
     * @param  array<string, mixed>  $toothTreatmentStates
     * @return array<string, mixed>
     */
    protected function diagnosisViewData(
        array $referencePayload,
        array $toothTreatmentStates,
        ToothChartViewConfig $toothChartViewConfig,
    ): array {
        return [
            'conditions' => $referencePayload['conditions'],
            'conditionsJson' => $referencePayload['conditionsJson'],
            'conditionOrder' => $referencePayload['conditionOrder'],
            'toothTreatmentStates' => $toothTreatmentStates,
            'defaultDentitionMode' => DentitionModeResolver::resolveFromBirthday($this->patient->birthday),
            'otherDiagnosisOptions' => app(PatientExamReferenceReadModelService::class)->otherDiagnosisOptions(),
            'selectedIndicationUploadTypes' => $this->selectedIndicationUploadTypes(),
            'diagnosisDentitionOptions' => $toothChartViewConfig->dentitionOptions(),
            'diagnosisToothRows' => $toothChartViewConfig->toothRows(
                ['upper' => self::ADULT_UPPER_TEETH, 'lower' => self::ADULT_LOWER_TEETH],
                ['upper' => self::CHILD_UPPER_TEETH, 'lower' => self::CHILD_LOWER_TEETH],
            ),
            'diagnosisTreatmentLegend' => $toothChartViewConfig->treatmentLegend(),
            'diagnosisSelectionHint' => $toothChartViewConfig->selectionHint(),
            'adultUpper' => self::ADULT_UPPER_TEETH,
            'childUpper' => self::CHILD_UPPER_TEETH,
            'childLower' => self::CHILD_LOWER_TEETH,
            'adultLower' => self::ADULT_LOWER_TEETH,
        ];
    }

    /**
     * @param  array<string, mixed>  $mediaReadModel
     * @return array<string, mixed>
     */
    protected function mediaViewData(array $mediaReadModel): array
    {
        return [
            'mediaTimeline' => $mediaReadModel['mediaTimeline'],
            'mediaPhaseSummary' => $mediaReadModel['mediaPhaseSummary'],
            'evidenceChecklist' => $mediaReadModel['evidenceChecklist'],
        ];
    }

    protected function activeSessionBadgeLabel(): ?string
    {
        $sessionDate = $this->examSession?->session_date?->format('d/m/Y');

        if (! filled($sessionDate)) {
            return null;
        }

        return 'Đang mở: '.$sessionDate;
    }

    /**
     * @param  Collection<int, ExamSession>  $sessions
     * @return array<int, array{
     *     id:int,
     *     session: ExamSession,
     *     date_label:string,
     *     is_active:bool,
     *     is_locked:bool,
     *     edit_action_title:string,
     *     edit_action_label:string,
     *     delete_action_title:string,
     *     delete_action_label:string
     * }>
     */
    protected function sessionCards(Collection $sessions): array
    {
        return $sessions->map(
            fn (ExamSession $session): array => [
                'id' => $session->id,
                'session' => $session,
                'date_label' => $session->date?->format('d/m/Y') ?? '-',
                'is_active' => $this->activeSessionId === $session->id,
                'is_locked' => (bool) $session->is_locked,
                'edit_action_title' => $session->is_locked
                    ? 'Ngày khám đã có tiến trình điều trị nên không thể chỉnh sửa.'
                    : 'Sửa ngày khám',
                'edit_action_label' => $session->is_locked
                    ? 'Ngày khám đã khóa, không thể chỉnh sửa'
                    : 'Sửa ngày khám',
                'delete_action_title' => $session->is_locked
                    ? 'Ngày khám đã có tiến trình điều trị nên không thể xóa được.'
                    : 'Xóa phiếu khám',
                'delete_action_label' => $session->is_locked
                    ? 'Ngày khám đã khóa, không thể xóa'
                    : 'Xóa phiếu khám',
            ],
        )->all();
    }

    /**
     * @return list<string>
     */
    protected function selectedIndicationUploadTypes(): array
    {
        return collect($this->indications)
            ->map(fn ($type): string => strtolower(trim((string) $type)))
            ->filter(fn (string $type): bool => $type !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function getSessionQuery()
    {
        return $this->patient->examSessions()
            ->with('clinicalNote')
            ->orderByDesc('session_date')
            ->orderByDesc('id');
    }

    protected function resetExamForm(): void
    {
        $this->clinicalNote = null;
        $this->examSession = null;
        $this->examining_doctor_id = null;
        $this->treating_doctor_id = null;
        $this->general_exam_notes = '';
        $this->treatment_plan_note = '';
        $this->indications = [];
        $this->indicationImages = [];
        $this->tempUploads = [];
        $this->other_diagnosis = '';
        $this->tooth_diagnosis_data = [];
        $this->dentition_mode = DentitionModeResolver::MODE_AUTO;
        $this->examiningDoctorSearch = '';
        $this->treatingDoctorSearch = '';
        $this->clinicalNoteVersion = 1;
    }

    protected function hydrateFormFromSession(ClinicalNote $session): void
    {
        $this->examining_doctor_id = $session->examining_doctor_id;
        $this->treating_doctor_id = $session->treating_doctor_id;
        $this->general_exam_notes = $session->general_exam_notes ?? '';
        $this->treatment_plan_note = $session->treatment_plan_note ?? '';
        $this->indications = $this->patientExamIndicationStateService()->normalizeSelected($session->indications ?? []);
        $this->indicationImages = $this->patientExamIndicationStateService()->normalizeImages(
            $session->indication_images ?? [],
            $this->indications,
        );
        $this->tooth_diagnosis_data = $session->tooth_diagnosis_data ?? [];
        $this->other_diagnosis = $session->other_diagnosis ?? '';
        $this->dentition_mode = DentitionModeResolver::MODE_AUTO;
        $this->clinicalNoteVersion = (int) ($session->lock_version ?: 1);

        $this->tempUploads = [];

        $this->examiningDoctorSearch = $this->examiningDoctorName();
        $this->treatingDoctorSearch = $this->treatingDoctorName();
    }

    protected function isSessionLockedByProgress(ExamSession $session): bool
    {
        return $this->patientExamSessionReadModelService()->isLocked($this->patient, $session);
    }

    protected function getTreatmentProgressDates(): array
    {
        return app(PatientExamStatusReadModelService::class)->treatmentProgressDates($this->patient);
    }

    protected function buildToothTreatmentStates(): array
    {
        return app(PatientExamStatusReadModelService::class)->toothTreatmentStates($this->patient);
    }

    protected function findAssignableDoctor(int $doctorId): ?User
    {
        return $this->patientExamDoctorReadModelService()->find(
            actor: Auth::user(),
            branchId: $this->resolveDoctorAssignmentBranchId(),
            doctorId: $doctorId,
        );
    }

    protected function resolveDoctorAssignmentBranchId(): ?int
    {
        if (is_numeric($this->clinicalNote?->branch_id)) {
            return (int) $this->clinicalNote->branch_id;
        }

        if (is_numeric($this->examSession?->branch_id)) {
            return (int) $this->examSession->branch_id;
        }

        if (is_numeric($this->patient->first_branch_id)) {
            return (int) $this->patient->first_branch_id;
        }

        return null;
    }

    protected function notifyDoctorSelectionOutOfScope(): void
    {
        Notification::make()
            ->title('Bác sĩ được chọn không thuộc phạm vi chi nhánh được phép gán.')
            ->danger()
            ->send();
    }

    /**
     * @return array{
     *     examining_doctor_id: ?int,
     *     treating_doctor_id: ?int,
     *     general_exam_notes: ?string,
     *     treatment_plan_note: ?string,
     *     indications: array<int, string>,
     *     indication_images: array<string, array<int, string>>,
     *     tooth_diagnosis_data: array<mixed>,
     *     other_diagnosis: ?string,
     *     updated_by: ?int
     * }
     */
    protected function clinicalNotePayloadData(): array
    {
        $normalizedIndications = $this->patientExamIndicationStateService()->normalizeSelected($this->indications);

        return [
            'examining_doctor_id' => $this->examining_doctor_id,
            'treating_doctor_id' => $this->treating_doctor_id,
            'general_exam_notes' => $this->general_exam_notes,
            'treatment_plan_note' => $this->treatment_plan_note,
            'indications' => $normalizedIndications,
            'indication_images' => $this->patientExamIndicationStateService()->normalizeImages(
                $this->indicationImages,
                $normalizedIndications,
            ),
            'tooth_diagnosis_data' => $this->tooth_diagnosis_data,
            'other_diagnosis' => $this->other_diagnosis,
            'updated_by' => Auth::id() ?: null,
        ];
    }

    protected function patientExamDoctorReadModelService(): PatientExamDoctorReadModelService
    {
        return app(PatientExamDoctorReadModelService::class);
    }

    protected function patientExamIndicationStateService(): PatientExamIndicationStateService
    {
        return app(PatientExamIndicationStateService::class);
    }

    protected function patientExamSessionReadModelService(): PatientExamSessionReadModelService
    {
        return app(PatientExamSessionReadModelService::class);
    }

    protected function patientExamMediaWorkflowService(): PatientExamMediaWorkflowService
    {
        return app(PatientExamMediaWorkflowService::class);
    }

    protected function patientExamClinicalNoteWorkflowService(): PatientExamClinicalNoteWorkflowService
    {
        return app(PatientExamClinicalNoteWorkflowService::class);
    }

    protected function patientExamSessionWorkflowService(): PatientExamSessionWorkflowService
    {
        return app(PatientExamSessionWorkflowService::class);
    }

    protected function authorizeClinicalWrite(): void
    {
        ActionGate::authorize(
            ActionPermission::EMR_CLINICAL_WRITE,
            'Bạn không có quyền cập nhật dữ liệu lâm sàng EMR.',
        );
    }
}
