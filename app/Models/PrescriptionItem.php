<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'medication_name',
        'dosage',
        'quantity',
        'unit',
        'instructions',
        'duration',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'notes' => NullableEncrypted::class,
    ];

    // Relationships
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    // Common units for medications
    public static function getUnits(): array
    {
        return [
            'viên' => 'Viên',
            'gói' => 'Gói',
            'chai' => 'Chai',
            'ống' => 'Ống',
            'tuýp' => 'Tuýp',
            'hộp' => 'Hộp',
            'vỉ' => 'Vỉ',
            'lọ' => 'Lọ',
            'ml' => 'ml',
            'mg' => 'mg',
        ];
    }

    // Common instructions
    public static function getCommonInstructions(): array
    {
        return [
            'Ngày uống 1 lần, sau ăn',
            'Ngày uống 2 lần, sáng - tối',
            'Ngày uống 3 lần, sáng - trưa - tối',
            'Ngày uống 2 lần, trước ăn 30 phút',
            'Ngày uống 2 lần, sau ăn',
            'Uống khi đau',
            'Bôi ngoài da, ngày 2-3 lần',
            'Súc miệng ngày 2-3 lần',
        ];
    }

    // Format for display
    public function getFormattedInstructionAttribute(): string
    {
        $parts = [];

        if ($this->dosage) {
            $parts[] = $this->dosage;
        }

        if ($this->quantity && $this->unit) {
            $parts[] = $this->quantity.' '.$this->unit;
        }

        if ($this->instructions) {
            $parts[] = $this->instructions;
        }

        if ($this->duration) {
            $parts[] = '('.$this->duration.')';
        }

        return implode(' - ', $parts);
    }
}
