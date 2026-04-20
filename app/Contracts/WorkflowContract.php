<?php

namespace App\Contracts;

use App\Support\TransitionResult;

/**
 * Contract chung cho các workflow service có state machine.
 *
 * Mỗi module có thể implement interface này để đảm bảo:
 * - Transition đi qua service canonical (không raw status write).
 * - Audit metadata (reason, trigger, actor, timestamp) được ghi nhất quán.
 * - Caller nhận TransitionResult có structured return contract.
 *
 * @template TRecord of \Illuminate\Database\Eloquent\Model
 */
interface WorkflowContract
{
    /**
     * Thực hiện một workflow transition.
     *
     * @param  TRecord  $record  Model cần transition.
     * @param  string  $transition  Tên transition (ví dụ: 'cancel', 'complete', 'approve').
     * @param  string|null  $reason  Lý do do operator cung cấp.
     * @param  int|null  $actorId  ID người thực hiện (default: auth()->id()).
     * @param  array<string, mixed>  $context  Dữ liệu bổ sung (không bắt buộc).
     * @return TransitionResult<TRecord>
     */
    public function apply(
        mixed $record,
        string $transition,
        ?string $reason = null,
        ?int $actorId = null,
        array $context = [],
    ): TransitionResult;
}
