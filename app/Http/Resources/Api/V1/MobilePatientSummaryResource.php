<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobilePatientSummaryResource extends JsonResource
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
            'patient_code' => $this->patient_code,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'birthday' => $this->birthday?->toDateString(),
            'address' => $this->address,
            'first_visit_reason' => $this->first_visit_reason,
            'branch' => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ],
            'primary_doctor' => [
                'id' => $this->primaryDoctor?->id,
                'name' => $this->primaryDoctor?->name,
            ],
            'wallet' => [
                'balance' => (float) ($this->wallet?->balance ?? 0),
                'total_deposit' => (float) ($this->wallet?->total_deposit ?? 0),
                'total_spent' => (float) ($this->wallet?->total_spent ?? 0),
            ],
            'risk_profile' => [
                'risk_level' => $this->latestRiskProfile?->risk_level,
                'no_show_score' => $this->latestRiskProfile?->no_show_score,
                'churn_score' => $this->latestRiskProfile?->churn_score,
                'as_of_date' => $this->latestRiskProfile?->as_of_date?->toDateString(),
            ],
        ];
    }
}
