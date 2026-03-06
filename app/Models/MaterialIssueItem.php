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
        'material_batch_id',
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
            'material_batch_id' => 'integer',
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $issueNote = MaterialIssueNote::query()
                ->whereKey((int) $item->material_issue_note_id)
                ->lockForUpdate()
                ->first();

            if ($issueNote && $issueNote->status !== MaterialIssueNote::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'material_issue_note_id' => 'Phiếu đã xuất kho không thể cập nhật vật tư.',
                ]);
            }

            $quantity = (int) ($item->quantity ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng phải lớn hơn 0.',
                ]);
            }

            $materialId = is_numeric($item->material_id) ? (int) $item->material_id : null;
            $materialBatchId = is_numeric($item->material_batch_id) ? (int) $item->material_batch_id : null;

            if ($materialId === null) {
                throw ValidationException::withMessages([
                    'material_id' => 'Vui lòng chọn vật tư.',
                ]);
            }

            if ($materialBatchId === null) {
                throw ValidationException::withMessages([
                    'material_batch_id' => 'Vui lòng chọn lô vật tư để truy vết tồn kho.',
                ]);
            }

            $material = Material::query()
                ->select(['id', 'branch_id', 'cost_price', 'sale_price'])
                ->find($materialId);

            if (! $material) {
                throw ValidationException::withMessages([
                    'material_id' => 'Không tìm thấy vật tư đã chọn.',
                ]);
            }

            if ($item->unit_cost === null || (float) $item->unit_cost <= 0) {

                $item->unit_cost = (float) ($material?->cost_price ?? $material?->sale_price ?? 0);
            }

            $materialBatch = MaterialBatch::query()->find($materialBatchId);

            if (! $materialBatch) {
                throw ValidationException::withMessages([
                    'material_batch_id' => 'Không tìm thấy lô vật tư đã chọn.',
                ]);
            }

            if ((int) $materialBatch->material_id !== $materialId) {
                throw ValidationException::withMessages([
                    'material_batch_id' => 'Lô vật tư không thuộc vật tư đã chọn.',
                ]);
            }

            $issueNoteBranchId = is_numeric($issueNote?->branch_id) ? (int) $issueNote->branch_id : null;
            $materialBranchId = is_numeric($material->branch_id) ? (int) $material->branch_id : null;

            if ($issueNoteBranchId !== null && $materialBranchId !== null && $issueNoteBranchId !== $materialBranchId) {
                throw ValidationException::withMessages([
                    'material_batch_id' => 'Lô vật tư không thuộc chi nhánh của phiếu xuất.',
                ]);
            }

            if ($materialBatch->status !== 'active') {
                throw ValidationException::withMessages([
                    'material_batch_id' => 'Chỉ được chọn lô vật tư đang hoạt động.',
                ]);
            }

            if ($materialBatch->expiry_date !== null && $materialBatch->expiry_date->lt(today())) {
                throw ValidationException::withMessages([
                    'material_batch_id' => 'Không được chọn lô vật tư đã hết hạn.',
                ]);
            }

            if ((int) $materialBatch->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng xuất vượt quá tồn kho của lô vật tư đã chọn.',
                ]);
            }

            $item->total_cost = round($quantity * max((float) $item->unit_cost, 0), 2);
        });

        static::deleting(function (self $item): void {
            $issueNote = MaterialIssueNote::query()
                ->whereKey((int) $item->material_issue_note_id)
                ->lockForUpdate()
                ->first();

            if ($issueNote && $issueNote->status !== MaterialIssueNote::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'material_issue_note_id' => 'Phiếu đã xuất kho không thể xóa vật tư.',
                ]);
            }
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

    public function materialBatch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class, 'material_batch_id');
    }
}
