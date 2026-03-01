<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class MaterialIssueItem extends Model
{
    protected $fillable = [
        'material_issue_note_id',
        'material_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'material_issue_note_id' => 'integer',
            'material_id' => 'integer',
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $quantity = (int) ($item->quantity ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng phải lớn hơn 0.',
                ]);
            }

            if ($item->unit_cost === null || (float) $item->unit_cost <= 0) {
                $material = Material::query()
                    ->select(['id', 'cost_price', 'sale_price'])
                    ->find((int) $item->material_id);

                $item->unit_cost = (float) ($material?->cost_price ?? $material?->sale_price ?? 0);
            }

            $item->total_cost = round($quantity * max((float) $item->unit_cost, 0), 2);
        });
    }

    public function issueNote(): BelongsTo
    {
        return $this->belongsTo(MaterialIssueNote::class, 'material_issue_note_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
