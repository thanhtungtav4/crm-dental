<?php

namespace App\Livewire;

use App\Models\ClinicalNote;
use App\Models\Disease;
use App\Models\Patient;
use App\Models\ToothCondition;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use App\Support\DentitionModeResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PatientExamForm extends Component
{
    use WithFileUploads;

    public Patient $patient;

    public ?ClinicalNote $clinicalNote = null;

    public ?int $activeSessionId = null;

    public ?string $newSessionDate = null;

    public ?int $editingSessionId = null;

    public ?string $editingSessionDate = null;

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
        $validated = $this->validate([
            'newSessionDate' => ['required', 'date'],
        ]);

        $existingSession = $this->patient->clinicalNotes()
            ->whereDate('date', $validated['newSessionDate'])
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

        $session = $this->patient->clinicalNotes()->create([
            'patient_id' => $this->patient->id,
            'doctor_id' => Auth::id(),
            'branch_id' => $this->patient->first_branch_id,
            'date' => $validated['newSessionDate'],
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
        $session = $this->patient->clinicalNotes()->find($sessionId);

        if (! $session) {
            return;
        }

        $this->clinicalNote = $session;
        $this->activeSessionId = $session->id;
        $this->editingSessionId = null;
        $this->editingSessionDate = null;

        $this->hydrateFormFromSession($session);
    }

    public function startEditingSession(int $sessionId): void
    {
        $session = $this->patient->clinicalNotes()->find($sessionId);

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
        $this->editingSessionDate = $session->date?->format('Y-m-d');
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

        $this->validate([
            'editingSessionDate' => ['required', 'date'],
        ]);

        $session = $this->patient->clinicalNotes()->find($this->editingSessionId);

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
        $existingSession = $this->patient->clinicalNotes()
            ->whereDate('date', $newDate)
            ->where('id', '!=', $session->id)
            ->first();

        if ($existingSession) {
            Notification::make()
                ->title('Ngày khám đã tồn tại. Vui lòng chọn ngày khác.')
                ->warning()
                ->send();

            return;
        }

        $session->update([
            'date' => $newDate,
            'updated_by' => Auth::id(),
        ]);

        $this->setActiveSession($session->id);

        Notification::make()
            ->title('Đã cập nhật ngày khám')
            ->success()
            ->send();
    }

    public function deleteSession(int $sessionId): void
    {
        $session = $this->patient->clinicalNotes()->find($sessionId);

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

        $session->delete();

        if ($this->activeSessionId === $sessionId) {
            $nextSession = $this->getSessionQuery()->first();

            if ($nextSession) {
                $this->setActiveSession($nextSession->id);
            } else {
                $this->clinicalNote = null;
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

        $this->clinicalNote->update($data);
        $this->clinicalNote->refresh();

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
            $sessionDate = $session->date?->toDateString();
            $session->setAttribute('is_locked', $sessionDate !== null && isset($lockedDates[$sessionDate]));
        }

        $medicalRecordId = $this->patient->medicalRecord()
            ->value('id');

        $medicalRecordActionUrl = $medicalRecordId
            ? route('filament.admin.resources.patient-medical-records.edit', ['record' => $medicalRecordId])
            : route('filament.admin.resources.patient-medical-records.create', ['patient_id' => $this->patient->id]);

        $medicalRecordActionLabel = $medicalRecordId
            ? 'Mở bệnh án điện tử'
            : 'Tạo bệnh án điện tử';

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
        return $this->patient->clinicalNotes()
            ->with(['examiningDoctor:id,name', 'treatingDoctor:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    protected function resetExamForm(): void
    {
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

        $this->tempUploads = [];

        $this->examiningDoctorSearch = $this->getExaminingDoctorNameProperty();
        $this->treatingDoctorSearch = $this->getTreatingDoctorNameProperty();
    }

    protected function isSessionLockedByProgress(ClinicalNote $session): bool
    {
        $sessionDate = $session->date?->toDateString();

        if (! $sessionDate) {
            return false;
        }

        return in_array($sessionDate, $this->getTreatmentProgressDates(), true);
    }

    protected function getTreatmentProgressDates(): array
    {
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
}
