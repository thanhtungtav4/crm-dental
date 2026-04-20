<?php

use App\Support\WorkflowAuditMetadata;

describe('WorkflowAuditMetadata::transition()', function (): void {
    it('returns structured status_from, status_to, and reason fields', function (): void {
        $result = WorkflowAuditMetadata::transition(
            fromStatus: 'pending',
            toStatus: 'completed',
            reason: 'operator confirmed',
        );

        expect($result)->toMatchArray([
            'status_from' => 'pending',
            'status_to' => 'completed',
            'reason' => 'operator confirmed',
        ]);
    });

    it('omits reason key when reason is null', function (): void {
        $result = WorkflowAuditMetadata::transition(
            fromStatus: 'pending',
            toStatus: 'cancelled',
            reason: null,
        );

        expect($result)->toHaveKey('status_from')
            ->and($result)->toHaveKey('status_to')
            ->and($result)->not->toHaveKey('reason');
    });

    it('omits reason key when reason is empty string', function (): void {
        $result = WorkflowAuditMetadata::transition(
            fromStatus: 'pending',
            toStatus: 'cancelled',
            reason: '   ',
        );

        expect($result)->not->toHaveKey('reason');
    });

    it('merges extra metadata fields into result', function (): void {
        $result = WorkflowAuditMetadata::transition(
            fromStatus: 'pending',
            toStatus: 'in_progress',
            reason: 'started',
            metadata: ['trigger' => 'manual_start', 'actor_id' => 42],
        );

        expect($result)->toMatchArray([
            'status_from' => 'pending',
            'status_to' => 'in_progress',
            'reason' => 'started',
            'trigger' => 'manual_start',
            'actor_id' => 42,
        ]);
    });

    it('allows extra metadata to override order without dropping status fields', function (): void {
        $result = WorkflowAuditMetadata::transition(
            fromStatus: 'a',
            toStatus: 'b',
            metadata: ['custom_key' => 'custom_value'],
        );

        expect($result)->toHaveKey('status_from', 'a')
            ->and($result)->toHaveKey('status_to', 'b')
            ->and($result)->toHaveKey('custom_key', 'custom_value');
    });
});

describe('WorkflowAuditMetadata::withActor()', function (): void {
    it('includes actor_id and actor_role when provided', function (): void {
        $result = WorkflowAuditMetadata::withActor(
            fromStatus: 'pending',
            toStatus: 'approved',
            actorId: 7,
            actorRole: 'manager',
            reason: 'approved by manager',
            trigger: 'manual_approve',
        );

        expect($result)->toMatchArray([
            'status_from' => 'pending',
            'status_to' => 'approved',
            'reason' => 'approved by manager',
            'trigger' => 'manual_approve',
            'actor_id' => 7,
            'actor_role' => 'manager',
        ]);
    });

    it('omits actor_id key when not provided and no authenticated user', function (): void {
        $result = WorkflowAuditMetadata::withActor(
            fromStatus: 'pending',
            toStatus: 'cancelled',
            actorId: null,
            reason: null,
        );

        // actor_id should not be present when there is no actor
        expect($result)->not->toHaveKey('actor_id');
    });

    it('omits null actor_role from result', function (): void {
        $result = WorkflowAuditMetadata::withActor(
            fromStatus: 'pending',
            toStatus: 'completed',
            actorId: 1,
            actorRole: null,
        );

        expect($result)->not->toHaveKey('actor_role');
    });

    it('merges extra fields passed via $extra parameter', function (): void {
        $result = WorkflowAuditMetadata::withActor(
            fromStatus: 'draft',
            toStatus: 'published',
            actorId: 3,
            extra: ['branch_id' => 5, 'note_id' => 99],
        );

        expect($result)->toHaveKey('branch_id', 5)
            ->and($result)->toHaveKey('note_id', 99);
    });
});

describe('WorkflowAuditMetadata::normalizeReason()', function (): void {
    it('returns null for null input', function (): void {
        expect(WorkflowAuditMetadata::normalizeReason(null))->toBeNull();
    });

    it('returns null for blank string', function (): void {
        expect(WorkflowAuditMetadata::normalizeReason('   '))->toBeNull();
    });

    it('trims and returns non-empty string', function (): void {
        expect(WorkflowAuditMetadata::normalizeReason('  hello  '))->toBe('hello');
    });
});
