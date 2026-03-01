<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toIso8601String(),
            'duration_minutes' => (int) ($this->duration_minutes ?? 0),
            'time_range_label' => $this->time_range_label,
            'status' => $this->status,
            'status_label' => \App\Models\Appointment::statusLabel($this->status),
            'appointment_type' => $this->appointment_type,
            'chief_complaint' => $this->chief_complaint,
            'patient' => [
                'id' => $this->patient?->id,
                'patient_code' => $this->patient?->patient_code,
                'full_name' => $this->patient?->full_name,
                'phone' => $this->patient?->phone,
            ],
            'doctor' => [
                'id' => $this->doctor?->id,
                'name' => $this->doctor?->name,
            ],
            'branch' => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ],
        ];
    }
}
