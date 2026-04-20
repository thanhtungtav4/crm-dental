<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Value object bọc kết quả của một workflow transition.
 *
 * Mục đích:
 * - Cung cấp return contract nhất quán cho mọi workflow service.
 * - Tách structured audit metadata ra khỏi model, giúp caller trace kết quả.
 * - Immutable — không được mutate sau khi tạo.
 *
 * @template TRecord of Model
 */
final class TransitionResult
{
    /**
     * @param  TRecord  $record  Model sau khi transition (đã refresh).
     * @param  string  $fromStatus  Trạng thái trước transition.
     * @param  string  $toStatus  Trạng thái sau transition.
     * @param  string  $transition  Tên transition đã thực hiện (ví dụ: 'cancel', 'complete').
     * @param  array<string, mixed>  $metadata  Structured audit metadata đã ghi vào AuditLog.
     */
    public function __construct(
        public readonly Model $record,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly string $transition,
        public readonly array $metadata = [],
    ) {}

    /**
     * Trạng thái có thay đổi hay không.
     */
    public function statusChanged(): bool
    {
        return $this->fromStatus !== $this->toStatus;
    }

    /**
     * Lấy giá trị từ metadata theo key, trả về default nếu không có.
     */
    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Convert sang array thuần (dùng cho tests / serialization).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'record_id' => $this->record->getKey(),
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'transition' => $this->transition,
            'status_changed' => $this->statusChanged(),
            'metadata' => $this->metadata,
        ];
    }
}
