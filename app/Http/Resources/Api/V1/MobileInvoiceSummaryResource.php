<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileInvoiceSummaryResource extends JsonResource
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
            'invoice_no' => $this->invoice_no,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),
            'total_amount' => (float) ($this->total_amount ?? 0),
            'discount_amount' => (float) ($this->discount_amount ?? 0),
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'remaining_amount' => (float) max(((float) ($this->total_amount ?? 0) - (float) ($this->paid_amount ?? 0)), 0),
            'patient' => [
                'id' => $this->patient?->id,
                'patient_code' => $this->patient?->patient_code,
                'full_name' => $this->patient?->full_name,
                'phone' => $this->patient?->phone,
            ],
            'branch' => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ],
        ];
    }
}
