<?php

namespace App\Support;

class WorkflowAuditMetadata
{
    /**
     * @param  array<string, mixed>  $metadata
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

    public static function normalizeReason(?string $reason): ?string
    {
        $resolvedReason = trim((string) $reason);

        return $resolvedReason !== '' ? $resolvedReason : null;
    }
}
