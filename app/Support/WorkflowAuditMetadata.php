<?php

namespace App\Support;

class WorkflowAuditMetadata
{
    /**
     * Build structured audit metadata cho một workflow transition.
     *
     * @param  array<string, mixed>  $metadata  Extra context fields (trigger, actor_id, v.v.).
     * @return array<string, mixed>
     */
    public static function transition(
        string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        array $metadata = [],
    ): array {
        $resolvedReason = static::normalizeReason($reason);

        return array_merge($metadata, array_filter([
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'reason' => $resolvedReason,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Build audit metadata kèm actor context (actor_id, actor_role, ip).
     *
     * Dùng khi cần trace đầy đủ hơn ở các workflow surface mới.
     * Caller chịu trách nhiệm resolve actor_id và ip trước khi gọi method này
     * (tức là gọi auth()->id() / request()->ip() ở service layer, không trong VO).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function withActor(
        string $fromStatus,
        string $toStatus,
        ?int $actorId = null,
        ?string $actorRole = null,
        ?string $reason = null,
        ?string $trigger = null,
        ?string $ip = null,
        array $extra = [],
    ): array {
        return static::transition(
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            reason: $reason,
            metadata: array_filter(array_merge($extra, [
                'trigger' => $trigger,
                'actor_id' => $actorId,
                'actor_role' => $actorRole,
                'ip' => $ip,
            ]), static fn (mixed $value): bool => $value !== null),
        );
    }

    public static function normalizeReason(?string $reason): ?string
    {
        $resolvedReason = trim((string) $reason);

        return $resolvedReason !== '' ? $resolvedReason : null;
    }
}
