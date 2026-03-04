<?php

namespace App\Livewire;

use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\ClinicalNote;
use App\Models\Disease;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;
use App\Models\ToothCondition;
use App\Models\User;
use App\Services\ClinicalMediaAccessService;
use App\Services\ClinicalNoteVersioningService;
use App\Services\EncounterService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use App\Support\DentitionModeResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class PatientExamForm extends Component
{
    use WithFileUploads;

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

        $sessionDate = (string) $validated['newSessionDate'];

        $existingSession = $this->patient->examSessions()
            ->whereDate('session_date', $sessionDate)
            ->latest('id')
            ->first();

        if ($existingSession) {
            $this->setActiveSession($existingSession->id);

            Notification::make()
                ->title('Ngày khám đã tồn tại, đã chuyển về phiếu hiện có')
                ->warning()
                ->send();

            return;
        }

        $visitEpisodeId = $this->resolveEncounterIdForDate($sessionDate);

        $session = $this->patient->examSessions()->create([
            'patient_id' => $this->patient->id,
            'visit_episode_id' => $visitEpisodeId,
            'doctor_id' => Auth::id() ?: null,
            'branch_id' => $this->patient->first_branch_id,
            'session_date' => $sessionDate,
            'status' => ExamSession::STATUS_DRAFT,
            'created_by' => Auth::id() ?: null,
            'updated_by' => Auth::id() ?: null,
        ]);

        $this->patient->clinicalNotes()->create([
            'exam_session_id' => $session->id,
            'patient_id' => $this->patient->id,
            'visit_episode_id' => $visitEpisodeId,
            'doctor_id' => Auth::id(),
            'branch_id' => $this->patient->first_branch_id,
            'date' => $sessionDate,
            'indications' => [],
            'indication_images' => [],
            'tooth_diagnosis_data' => [],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->setActiveSession($session->id);

        Notification::make()
            ->title('Đã tạo phiếu khám mới')
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
            $note = $this->patient->clinicalNotes()->create([
                'exam_session_id' => $session->id,
                'patient_id' => $this->patient->id,
                'visit_episode_id' => $session->visit_episode_id,
                'doctor_id' => $session->doctor_id,
                'branch_id' => $session->branch_id,
                'date' => $session->session_date?->toDateString() ?? now()->toDateString(),
                'indications' => [],
                'indication_images' => [],
                'tooth_diagnosis_data' => [],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $session->refresh();
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

        $session = $this->patient->examSessions()
            ->with('clinicalNote')
            ->find($this->editingSessionId);

        if (! $session) {
            $this->cancelEditingSession();

            return;
        }

        if ($this->isSessionLockedByProgress($session)) {
            Notification::make()
                ->title('Ngày khám đã có tiến trình điều trị nên không thể chỉnh sửa.')
                ->danger()
                ->send();
            $this->cancelEditingSession();

            return;
        }

        $newDate = (string) $this->editingSessionDate;
        $existingSession = $this->patient->examSessions()
            ->whereDate('session_date', $newDate)
            ->where('id', '!=', $session->id)
            ->first();

        if ($existingSession) {
            Notification::make()
                ->title('Ngày khám đã tồn tại. Vui lòng chọn ngày khác.')
                ->warning()
                ->send();

            return;
        }

        $visitEpisodeId = $session->visit_episode_id
            ?: $this->resolveEncounterIdForDate($newDate);

        $session->fill([
            'session_date' => $newDate,
            'visit_episode_id' => $visitEpisodeId,
            'updated_by' => Auth::id(),
        ]);
        $session->save();

        $note = $session->clinicalNote;

        if ($note) {
            $payload = [
                'date' => $newDate,
                'visit_episode_id' => $visitEpisodeId,
                'updated_by' => Auth::id(),
            ];

            try {
                $this->clinicalNote = $this->clinicalNoteVersioningService()->updateWithOptimisticLock(
                    clinicalNote: $note,
                    attributes: $payload,
                    expectedVersion: (int) ($note->lock_version ?: 1),
                    actorId: Auth::id(),
                    operation: 'amend',
                    reason: 'session_date_update',
                );
            } catch (ValidationException $exception) {
                $errorMessage = (string) (collect($exception->errors())->flatten()->first()
                    ?? 'Phiếu khám đã thay đổi. Vui lòng tải lại dữ liệu mới nhất.');

                Notification::make()
                    ->title($errorMessage)
                    ->danger()
                    ->send();

                $this->setActiveSession($session->id);

                return;
            }
        }

        if ($visitEpisodeId) {
            $this->encounterService()->syncStandaloneEncounterDate((int) $visitEpisodeId, $newDate);
        }

        $this->setActiveSession($session->id);

        Notification::make()
            ->title('Đã cập nhật ngày khám')
            ->success()
            ->send();
    }

    public function deleteSession(int $sessionId): void
    {
        $this->authorizeClinicalWrite();

        $session = $this->patient->examSessions()
            ->with(['clinicalOrders:id,exam_session_id', 'prescriptions:id,exam_session_id'])
            ->find($sessionId);

        if (! $session) {
            return;
        }

        if ($this->isSessionLockedByProgress($session)) {
            Notification::make()
                ->title('Ngày khám đã có tiến trình điều trị nên không thể xóa được.')
                ->danger()
                ->send();

            return;
        }

        if ($session->clinicalOrders->isNotEmpty() || $session->prescriptions->isNotEmpty()) {
            Notification::make()
                ->title('Phiếu khám đã phát sinh chỉ định/đơn thuốc nên không thể xóa.')
                ->danger()
                ->send();

            return;
        }

        ClinicalNote::query()
            ->where('exam_session_id', $session->id)
            ->delete();

        $session->delete();

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

    public function getDoctors(string $search = '')
    {
        return User::query()
            ->when($search, function ($query, $searchValue) {
                $query->where('name', 'like', "%{$searchValue}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);
    }

    public function selectExaminingDoctor(int $id): void
    {
        $this->examining_doctor_id = $id;
        $this->showExaminingDoctorDropdown = false;

        $doctor = User::find($id);
        $this->examiningDoctorSearch = $doctor?->name ?? '';

        $this->saveData();
    }

    public function selectTreatingDoctor(int $id): void
    {
        $this->treating_doctor_id = $id;
        $this->showTreatingDoctorDropdown = false;

        $doctor = User::find($id);
        $this->treatingDoctorSearch = $doctor?->name ?? '';

        $this->saveData();
    }

    public function toggleIndication(string $type): void
    {
        $normalizedType = $this->normalizeIndicationKey($type);

        if (in_array($normalizedType, $this->indications, true)) {
            $this->indications = array_values(array_diff($this->indications, [$normalizedType]));
            unset($this->indicationImages[$normalizedType], $this->tempUploads[$normalizedType]);
        } else {
            $this->indications[] = $normalizedType;
            $this->indications = array_values(array_unique($this->indications));
        }

        $this->saveData();
    }

    public function updatedTempUploads($value, string $key): void
    {
        $parts = explode('.', $key);
        $type = $this->normalizeIndicationKey($parts[0]);

        if (! isset($this->tempUploads[$type]) || ! is_array($this->tempUploads[$type])) {
            return;
        }

        foreach ($this->tempUploads[$type] as $file) {
            if (! $file || ! method_exists($file, 'store')) {
                continue;
            }

            $path = $file->store("patients/{$this->patient->id}/indications/{$type}", 'public');

            if (! isset($this->indicationImages[$type])) {
                $this->indicationImages[$type] = [];
            }

            $this->indicationImages[$type][] = $path;
            $this->persistClinicalMediaAsset($type, $path);
        }

        $this->tempUploads[$type] = [];
        $this->saveData();
    }

    public function removeImage(string $type, int $index): void
    {
        $normalizedType = $this->normalizeIndicationKey($type);

        if (! isset($this->indicationImages[$normalizedType][$index])) {
            return;
        }

        $path = $this->indicationImages[$normalizedType][$index];

        Storage::disk('public')->delete($path);
        ClinicalMediaAsset::query()
            ->where('patient_id', $this->patient->id)
            ->where('exam_session_id', $this->examSession?->id)
            ->where('storage_disk', 'public')
            ->where('storage_path', $path)
            ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
            ->get()
            ->each
            ->delete();
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
        if (! $this->clinicalNote) {
            return;
        }

        $this->authorizeClinicalWrite();

        $normalizedIndications = $this->normalizeIndications($this->indications);

        $data = [
            'examining_doctor_id' => $this->examining_doctor_id,
            'treating_doctor_id' => $this->treating_doctor_id,
            'general_exam_notes' => $this->general_exam_notes,
            'treatment_plan_note' => $this->treatment_plan_note,
            'indications' => $normalizedIndications,
            'indication_images' => $this->normalizeIndicationImages($this->indicationImages, $normalizedIndications),
            'tooth_diagnosis_data' => $this->tooth_diagnosis_data,
            'other_diagnosis' => $this->other_diagnosis,
            'updated_by' => Auth::id(),
        ];

        if (! $this->clinicalNote->visit_episode_id) {
            $data['visit_episode_id'] = $this->resolveEncounterIdForDate(
                $this->clinicalNote->date?->toDateString() ?? now()->toDateString(),
            );
        }

        try {
            $this->clinicalNote = $this->clinicalNoteVersioningService()->updateWithOptimisticLock(
                clinicalNote: $this->clinicalNote,
                attributes: $data,
                expectedVersion: $this->clinicalNoteVersion,
                actorId: Auth::id(),
                operation: 'update',
            );
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

    public function getExaminingDoctorNameProperty(): string
    {
        if (! $this->examining_doctor_id) {
            return '';
        }

        return User::find($this->examining_doctor_id)?->name ?? '';
    }

    public function getTreatingDoctorNameProperty(): string
    {
        if (! $this->treating_doctor_id) {
            return '';
        }

        return User::find($this->treating_doctor_id)?->name ?? '';
    }

    public function render()
    {
        $sessions = $this->getSessionQuery()->get();

        $lockedDates = array_flip($this->getTreatmentProgressDates());
        $toothTreatmentStates = $this->buildToothTreatmentStates();
        $conditions = ToothCondition::query()
            ->ordered()
            ->get()
            ->values();

        if (! $conditions->contains(fn (ToothCondition $condition) => strtoupper((string) $condition->code) === 'KHAC')) {
            $conditions->push(new ToothCondition([
                'code' => 'KHAC',
                'name' => '(*) Khác',
                'category' => 'Khác',
                'color' => '#9ca3af',
            ]));
        }

        $conditions = $conditions->values();
        $conditionsArray = $conditions->map(fn (ToothCondition $condition) => [
            'code' => $condition->code,
            'name' => $condition->name,
            'category' => $condition->category,
            'color' => $condition->color,
            'display_code' => $this->getConditionDisplayCode($condition),
        ])->values()->all();

        $conditionOrder = $conditions
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();

        foreach ($sessions as $session) {
            $sessionDate = $session->session_date?->toDateString();
            $session->setAttribute(
                'is_locked',
                $session->status === ExamSession::STATUS_LOCKED
                    || ($sessionDate !== null && isset($lockedDates[$sessionDate]))
            );
        }

        $authUser = Auth::user();
        $medicalRecord = $this->patient->medicalRecord()
            ->first(['id', 'patient_id']);

        $medicalRecordActionUrl = null;
        $medicalRecordActionLabel = null;

        if ($authUser instanceof User) {
            if ($medicalRecord instanceof PatientMedicalRecord) {
                if ($authUser->can('update', $medicalRecord) || $authUser->can('view', $medicalRecord)) {
                    $medicalRecordActionUrl = route('filament.admin.resources.patient-medical-records.edit', ['record' => $medicalRecord->id]);
                    $medicalRecordActionLabel = 'Mở bệnh án điện tử';
                }
            } elseif ($authUser->can('create', PatientMedicalRecord::class)) {
                $medicalRecordActionUrl = route('filament.admin.resources.patient-medical-records.create', ['patient_id' => $this->patient->id]);
                $medicalRecordActionLabel = 'Tạo bệnh án điện tử';
            }
        }

        $mediaAccessService = $this->clinicalMediaAccessService();
        $mediaAssets = $this->patient->clinicalMediaAssets()
            ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
            ->latest('captured_at')
            ->limit(120)
            ->get();
        $mediaPhaseSummary = $mediaAssets
            ->groupBy(fn (ClinicalMediaAsset $asset): string => (string) ($asset->phase ?: 'unspecified'))
            ->map(fn ($group): int => $group->count())
            ->toArray();
        $mediaTimeline = $mediaAssets
            ->take(20)
            ->map(function (ClinicalMediaAsset $asset) use ($mediaAccessService): array {
                return [
                    'id' => (int) $asset->id,
                    'captured_at' => $asset->captured_at?->toDateTimeString(),
                    'phase' => (string) ($asset->phase ?: 'unspecified'),
                    'modality' => (string) ($asset->modality ?: ClinicalMediaAsset::MODALITY_PHOTO),
                    'anatomy_scope' => (string) ($asset->anatomy_scope ?: 'general'),
                    'exam_session_id' => $asset->exam_session_id ? (int) $asset->exam_session_id : null,
                    'view_url' => $mediaAccessService->signedViewUrl($asset),
                    'download_url' => $mediaAccessService->signedDownloadUrl($asset),
                ];
            })
            ->values()
            ->all();

        $activeSessionMedia = $mediaAssets
            ->filter(fn (ClinicalMediaAsset $asset): bool => (int) ($asset->exam_session_id ?? 0) === (int) ($this->activeSessionId ?? 0))
            ->values();
        $selectedIndications = collect($this->normalizeIndications($this->indications));
        $missingIndications = $selectedIndications
            ->filter(function (string $type) use ($activeSessionMedia): bool {
                $uploadedCount = count((array) ($this->indicationImages[$type] ?? []));
                $assetCount = $activeSessionMedia
                    ->filter(fn (ClinicalMediaAsset $asset): bool => strtolower((string) data_get($asset->meta, 'indication_type', '')) === $type)
                    ->count();

                return ($uploadedCount + $assetCount) <= 0;
            })
            ->map(fn (string $type): string => (string) ($this->indicationTypes[$type] ?? strtoupper($type)))
            ->values()
            ->all();
        $requiredEvidenceCount = $selectedIndications->count();
        $fulfilledEvidenceCount = max(0, $requiredEvidenceCount - count($missingIndications));
        $evidenceCompletionPercent = $requiredEvidenceCount > 0
            ? (int) floor(($fulfilledEvidenceCount / $requiredEvidenceCount) * 100)
            : 100;

        $qualityWarnings = [];
        if ($activeSessionMedia->whereNull('checksum_sha256')->count() > 0) {
            $qualityWarnings[] = 'Có ảnh chưa có checksum, cần kiểm tra integrity dữ liệu.';
        }
        if ($activeSessionMedia->whereNull('captured_at')->count() > 0) {
            $qualityWarnings[] = 'Có ảnh thiếu thời gian chụp, nên chuẩn hóa metadata.';
        }
        if ($requiredEvidenceCount > 0 && $fulfilledEvidenceCount <= 0) {
            $qualityWarnings[] = 'Phiếu khám đã chọn chỉ định nhưng chưa có bằng chứng ảnh lâm sàng.';
        }

        return view('livewire.patient-exam-form', [
            'sessions' => $sessions,
            'examiningDoctors' => $this->getDoctors($this->examiningDoctorSearch),
            'treatingDoctors' => $this->getDoctors($this->treatingDoctorSearch),
            'medicalRecordActionUrl' => $medicalRecordActionUrl,
            'medicalRecordActionLabel' => $medicalRecordActionLabel,
            'conditions' => $conditions,
            'conditionsJson' => $conditionsArray,
            'conditionOrder' => $conditionOrder,
            'toothTreatmentStates' => $toothTreatmentStates,
            'defaultDentitionMode' => DentitionModeResolver::resolveFromBirthday($this->patient->birthday),
            'otherDiagnosisOptions' => Disease::query()
                ->active()
                ->with(['diseaseGroup:id,name,sort_order'])
                ->get()
                ->sortBy([
                    fn (Disease $disease) => $disease->diseaseGroup?->sort_order ?? 0,
                    fn (Disease $disease) => $disease->code,
                ])
                ->values()
                ->map(fn (Disease $disease) => [
                    'code' => $disease->code,
                    'label' => $disease->full_name,
                    'group' => $disease->diseaseGroup?->name ?? 'Khác',
                ])
                ->values()
                ->all(),
            'mediaTimeline' => $mediaTimeline,
            'mediaPhaseSummary' => $mediaPhaseSummary,
            'evidenceChecklist' => [
                'required' => $requiredEvidenceCount,
                'fulfilled' => $fulfilledEvidenceCount,
                'completion_percent' => max(0, min(100, $evidenceCompletionPercent)),
                'missing_labels' => $missingIndications,
                'quality_warnings' => $qualityWarnings,
            ],
        ]);
    }

    protected function getConditionDisplayCode(ToothCondition $condition): string
    {
        $name = (string) ($condition->name ?? '');

        if (preg_match('/^\(([^)]+)\)/', $name, $matches)) {
            return strtoupper(str_replace(' ', '', $matches[1]));
        }

        return strtoupper((string) $condition->code);
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
        $this->indications = $this->normalizeIndications($session->indications ?? []);
        $this->indicationImages = $this->normalizeIndicationImages($session->indication_images ?? [], $this->indications);
        $this->tooth_diagnosis_data = $session->tooth_diagnosis_data ?? [];
        $this->other_diagnosis = $session->other_diagnosis ?? '';
        $this->dentition_mode = DentitionModeResolver::MODE_AUTO;
        $this->clinicalNoteVersion = (int) ($session->lock_version ?: 1);

        $this->tempUploads = [];

        $this->examiningDoctorSearch = $this->getExaminingDoctorNameProperty();
        $this->treatingDoctorSearch = $this->getTreatingDoctorNameProperty();
    }

    protected function isSessionLockedByProgress(ExamSession $session): bool
    {
        if ($session->status === ExamSession::STATUS_LOCKED) {
            return true;
        }

        $sessionDate = $session->session_date?->toDateString();

        if (! $sessionDate) {
            return false;
        }

        return in_array($sessionDate, $this->getTreatmentProgressDates(), true);
    }

    protected function getTreatmentProgressDates(): array
    {
        $progressDates = $this->patient->treatmentProgressDays()
            ->get(['progress_date'])
            ->map(fn ($day) => $day->progress_date?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($progressDates !== []) {
            return $progressDates;
        }

        return $this->patient->treatmentSessions()
            ->get(['performed_at', 'start_at', 'end_at'])
            ->flatMap(function ($session) {
                return collect([
                    $session->performed_at,
                    $session->start_at,
                    $session->end_at,
                ])
                    ->filter()
                    ->map(fn ($dateTime) => Carbon::parse($dateTime)->toDateString());
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function buildToothTreatmentStates(): array
    {
        $statePriority = [
            'normal' => 0,
            'current' => 1,
            'completed' => 2,
            'in_treatment' => 3,
        ];

        $toothStates = [];

        $planItems = \App\Models\PlanItem::query()
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $this->patient->id))
            ->get(['tooth_number', 'status']);

        foreach ($planItems as $planItem) {
            $targetState = $this->mapPlanItemStatusToToothState((string) $planItem->status);

            foreach ($planItem->getToothNumbers() as $toothNumber) {
                $toothKey = (string) $toothNumber;
                $currentState = $toothStates[$toothKey] ?? 'normal';

                if (($statePriority[$targetState] ?? 0) >= ($statePriority[$currentState] ?? 0)) {
                    $toothStates[$toothKey] = $targetState;
                }
            }
        }

        $sessionStates = $this->patient->treatmentSessions()
            ->get([
                'treatment_sessions.plan_item_id',
                'treatment_sessions.status',
            ]);

        $planItemsById = \App\Models\PlanItem::withTrashed()
            ->whereIn('id', $sessionStates->pluck('plan_item_id')->filter()->unique()->values())
            ->get(['id', 'tooth_number'])
            ->keyBy('id');

        foreach ($sessionStates as $sessionState) {
            $planItem = $planItemsById->get($sessionState->plan_item_id);

            if (! $planItem) {
                continue;
            }

            $targetState = $this->mapTreatmentSessionStatusToToothState((string) $sessionState->status);

            foreach ($planItem->getToothNumbers() as $toothNumber) {
                $toothKey = (string) $toothNumber;
                $currentState = $toothStates[$toothKey] ?? 'normal';

                if (($statePriority[$targetState] ?? 0) >= ($statePriority[$currentState] ?? 0)) {
                    $toothStates[$toothKey] = $targetState;
                }
            }
        }

        return $toothStates;
    }

    protected function mapPlanItemStatusToToothState(string $status): string
    {
        return match ($status) {
            'in_progress' => 'in_treatment',
            'completed' => 'completed',
            default => 'current',
        };
    }

    protected function mapTreatmentSessionStatusToToothState(string $status): string
    {
        return match ($status) {
            'done' => 'completed',
            'scheduled', 'follow_up' => 'in_treatment',
            default => 'current',
        };
    }

    protected function persistClinicalMediaAsset(string $indicationType, string $storagePath): void
    {
        if (! $this->clinicalNote) {
            return;
        }

        $normalizedType = $this->normalizeIndicationKey($indicationType);
        $existing = ClinicalMediaAsset::query()
            ->where('patient_id', $this->patient->id)
            ->where('exam_session_id', $this->examSession?->id)
            ->where('storage_disk', 'public')
            ->where('storage_path', $storagePath)
            ->first();

        if ($existing) {
            return;
        }

        $modality = in_array($normalizedType, ['xray', 'panorama', 'cephalometric', '3d', '3d5x5'], true)
            ? ClinicalMediaAsset::MODALITY_XRAY
            : ClinicalMediaAsset::MODALITY_PHOTO;
        $anatomyScope = match ($normalizedType) {
            'int' => 'intraoral',
            'ext' => 'extraoral',
            default => $normalizedType !== '' ? $normalizedType : 'general',
        };

        $asset = ClinicalMediaAsset::query()->create([
            'patient_id' => (int) $this->patient->id,
            'visit_episode_id' => $this->clinicalNote->visit_episode_id ? (int) $this->clinicalNote->visit_episode_id : null,
            'exam_session_id' => $this->examSession?->id ? (int) $this->examSession->id : null,
            'branch_id' => $this->clinicalNote->branch_id
                ? (int) $this->clinicalNote->branch_id
                : ($this->patient->first_branch_id ? (int) $this->patient->first_branch_id : null),
            'captured_by' => Auth::id() ?: null,
            'captured_at' => now(),
            'modality' => $modality,
            'anatomy_scope' => $anatomyScope,
            'phase' => 'pre',
            'mime_type' => $this->guessMimeTypeFromPath($storagePath),
            'file_size_bytes' => $this->resolveStoredFileSize($storagePath),
            'checksum_sha256' => $this->resolveStoredFileChecksum($storagePath),
            'storage_disk' => 'public',
            'storage_path' => $storagePath,
            'status' => ClinicalMediaAsset::STATUS_ACTIVE,
            'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
            'legal_hold' => false,
            'meta' => [
                'source_table' => 'clinical_notes.indication_images',
                'source_id' => (int) $this->clinicalNote->id,
                'indication_type' => $normalizedType,
            ],
        ]);

        ClinicalMediaVersion::query()->create([
            'clinical_media_asset_id' => (int) $asset->id,
            'version_number' => 1,
            'is_original' => true,
            'mime_type' => $asset->mime_type,
            'checksum_sha256' => $asset->checksum_sha256,
            'storage_disk' => $asset->storage_disk,
            'storage_path' => $asset->storage_path,
            'created_by' => Auth::id() ?: null,
        ]);
    }

    protected function guessMimeTypeFromPath(string $path): ?string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => null,
        };
    }

    protected function resolveStoredFileChecksum(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);

            if (is_resource($stream)) {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                fclose($stream);

                return hash_final($hash);
            }
        }

        return hash('sha256', 'public|'.$path);
    }

    protected function resolveStoredFileSize(string $path): ?int
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $size = Storage::disk('public')->size($path);

        return is_int($size) ? $size : null;
    }

    protected function normalizeIndications(array $indications): array
    {
        return collect($indications)
            ->filter(fn ($item) => filled($item))
            ->map(fn ($item) => $this->normalizeIndicationKey((string) $item))
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeIndicationImages(array $indicationImages, ?array $selectedIndications = null): array
    {
        $selected = $selectedIndications ?? $this->indications;
        $selected = $this->normalizeIndications($selected);

        $normalized = [];

        foreach ($indicationImages as $rawType => $paths) {
            $type = $this->normalizeIndicationKey((string) $rawType);

            if (! in_array($type, $selected, true)) {
                continue;
            }

            $normalized[$type] = collect(is_array($paths) ? $paths : [$paths])
                ->filter(fn ($path) => filled($path))
                ->values()
                ->all();
        }

        foreach ($selected as $type) {
            if (! array_key_exists($type, $normalized)) {
                $normalized[$type] = [];
            }
        }

        return $normalized;
    }

    protected function normalizeIndicationKey(string $key): string
    {
        return ClinicRuntimeSettings::normalizeExamIndicationKey($key);
    }

    protected function resolveEncounterIdForDate(string $date): ?int
    {
        $encounter = $this->encounterService()->resolveForPatientOnDate(
            patientId: (int) $this->patient->id,
            branchId: $this->patient->first_branch_id ? (int) $this->patient->first_branch_id : null,
            date: $date,
            doctorId: $this->examining_doctor_id ?: (Auth::id() ?: null),
            createIfMissing: true,
        );

        return $encounter?->id ? (int) $encounter->id : null;
    }

    protected function encounterService(): EncounterService
    {
        return app(EncounterService::class);
    }

    protected function clinicalMediaAccessService(): ClinicalMediaAccessService
    {
        return app(ClinicalMediaAccessService::class);
    }

    protected function clinicalNoteVersioningService(): ClinicalNoteVersioningService
    {
        return app(ClinicalNoteVersioningService::class);
    }

    protected function authorizeClinicalWrite(): void
    {
        ActionGate::authorize(
            ActionPermission::EMR_CLINICAL_WRITE,
            'Bạn không có quyền cập nhật dữ liệu lâm sàng EMR.',
        );
    }
}
