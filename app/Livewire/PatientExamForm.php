<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Patient;
use App\Models\ClinicalNote;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PatientExamForm extends Component
{
    use WithFileUploads;

    public Patient $patient;
    public ?ClinicalNote $clinicalNote = null;

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
    public array $indicationTypes = [
        'cephalometric' => 'Cephalometric',
        'panorama' => 'Panorama',
        '3d_5x5' => '3D 5x5',
        '3d' => '3D',
        'anh_ext' => 'Ảnh (ext)',
        'anh_int' => 'Ảnh (int)',
        'can_chop' => 'Cận chóp',
        'khac' => 'Khác',
        'xet_nghiem_huyet_hoc' => 'Xét nghiệm huyết học',
        'xet_nghiem_sinh_hoa' => 'Xét nghiệm sinh hóa',
    ];

    // For doctor search
    public string $examiningDoctorSearch = '';
    public string $treatingDoctorSearch = '';
    public bool $showExaminingDoctorDropdown = false;
    public bool $showTreatingDoctorDropdown = false;

    public function mount(Patient $patient)
    {
        $this->patient = $patient;

        // Load existing clinical note or get latest
        $this->clinicalNote = $patient->clinicalNotes()->latest()->first();

        if ($this->clinicalNote) {
            $this->examining_doctor_id = $this->clinicalNote->examining_doctor_id;
            $this->treating_doctor_id = $this->clinicalNote->treating_doctor_id;
            $this->general_exam_notes = $this->clinicalNote->general_exam_notes ?? '';
            $this->treatment_plan_note = $this->clinicalNote->treatment_plan_note ?? '';
            $this->indications = $this->clinicalNote->indications ?? [];
            $this->indicationImages = $this->clinicalNote->indication_images ?? [];
        }
    }

    public function getDoctors($search = '')
    {
        return User::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);
    }

    public function selectExaminingDoctor($id)
    {
        $this->examining_doctor_id = $id;
        $this->showExaminingDoctorDropdown = false;
        $this->examiningDoctorSearch = '';
        $this->saveData();
    }

    public function selectTreatingDoctor($id)
    {
        $this->treating_doctor_id = $id;
        $this->showTreatingDoctorDropdown = false;
        $this->treatingDoctorSearch = '';
        $this->saveData();
    }

    public function toggleIndication($type)
    {
        if (in_array($type, $this->indications)) {
            $this->indications = array_values(array_diff($this->indications, [$type]));
            // Remove images when unchecking
            unset($this->indicationImages[$type]);
            unset($this->tempUploads[$type]);
        } else {
            $this->indications[] = $type;
        }
        $this->saveData();
    }

    public function updatedTempUploads($value, $key)
    {
        // $key format: "type.index" or just "type"
        $parts = explode('.', $key);
        $type = $parts[0];

        if (isset($this->tempUploads[$type]) && is_array($this->tempUploads[$type])) {
            foreach ($this->tempUploads[$type] as $file) {
                if ($file && method_exists($file, 'store')) {
                    $path = $file->store("patients/{$this->patient->id}/indications/{$type}", 'public');

                    if (!isset($this->indicationImages[$type])) {
                        $this->indicationImages[$type] = [];
                    }
                    $this->indicationImages[$type][] = $path;
                }
            }
            $this->tempUploads[$type] = [];
            $this->saveData();
        }
    }

    public function removeImage($type, $index)
    {
        if (isset($this->indicationImages[$type][$index])) {
            $path = $this->indicationImages[$type][$index];
            Storage::disk('public')->delete($path);
            unset($this->indicationImages[$type][$index]);
            $this->indicationImages[$type] = array_values($this->indicationImages[$type]);
            $this->saveData();
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['general_exam_notes', 'treatment_plan_note'])) {
            $this->saveData();
        }
    }

    public function saveData()
    {
        $data = [
            'patient_id' => $this->patient->id,
            'examining_doctor_id' => $this->examining_doctor_id,
            'treating_doctor_id' => $this->treating_doctor_id,
            'general_exam_notes' => $this->general_exam_notes,
            'treatment_plan_note' => $this->treatment_plan_note,
            'indications' => $this->indications,
            'indication_images' => $this->indicationImages,
            'branch_id' => $this->patient->first_branch_id,
            'date' => now(),
            'updated_by' => Auth::id(),
        ];

        if ($this->clinicalNote) {
            $this->clinicalNote->update($data);
        } else {
            $data['created_by'] = Auth::id();
            $data['doctor_id'] = Auth::id();
            $this->clinicalNote = ClinicalNote::create($data);
        }

        $this->dispatch('saved');
    }

    public function getExaminingDoctorNameProperty()
    {
        if ($this->examining_doctor_id) {
            return User::find($this->examining_doctor_id)?->name ?? '';
        }
        return '';
    }

    public function getTreatingDoctorNameProperty()
    {
        if ($this->treating_doctor_id) {
            return User::find($this->treating_doctor_id)?->name ?? '';
        }
        return '';
    }

    public function render()
    {
        return view('livewire.patient-exam-form', [
            'examiningDoctors' => $this->getDoctors($this->examiningDoctorSearch),
            'treatingDoctors' => $this->getDoctors($this->treatingDoctorSearch),
        ]);
    }
}
