<?php

namespace App\Services;

use App\Models\ClinicalNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalNoteVersioningService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateWithOptimisticLock(
        ClinicalNote $clinicalNote,
        array $attributes,
        int $expectedVersion,
        ?int $actorId = null,
        string $operation = 'update',
        ?string $reason = null,
    ): ClinicalNote {
        return DB::transaction(function () use (
            $clinicalNote,
            $attributes,
            $expectedVersion,
            $actorId,
            $operation,
            $reason
        ): ClinicalNote {
            $note = ClinicalNote::query()
                ->lockForUpdate()
                ->find($clinicalNote->id);

            if (! $note) {
                throw ValidationException::withMessages([
                    'clinical_note' => 'Không tìm thấy phiếu khám cần cập nhật.',
                ]);
            }

            if ((int) $note->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'clinical_note' => 'Dữ liệu phiếu khám đã thay đổi bởi người dùng khác. Vui lòng tải lại phiên bản mới nhất.',
                ]);
            }

            $note->revisionPreviousPayload = $note->revisionPayload();
            $note->revisionOperation = $operation;
            $note->revisionReason = $reason;
            $note->fill($attributes);

            if ($actorId !== null) {
                $note->updated_by = $actorId;
            }

            if (! $note->isDirty()) {
                return $note;
            }

            $note->save();
            $note->refresh();

            return $note;
        }, 3);
    }
}
