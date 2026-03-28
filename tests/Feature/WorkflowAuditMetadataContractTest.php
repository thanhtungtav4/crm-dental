<?php

use App\Support\WorkflowAuditMetadata;

it('normalizes workflow reasons and transition metadata consistently', function (): void {
    $metadata = WorkflowAuditMetadata::transition(
        fromStatus: 'draft',
        toStatus: 'cancelled',
        reason: '  Need to stop this workflow  ',
        metadata: [
            'channel' => 'contract-test',
        ],
    );

    expect($metadata)->toBe([
        'channel' => 'contract-test',
        'status_from' => 'draft',
        'status_to' => 'cancelled',
        'reason' => 'Need to stop this workflow',
    ])
        ->and(WorkflowAuditMetadata::transition(
            fromStatus: 'draft',
            toStatus: 'scheduled',
            reason: '   ',
        ))->toBe([
            'status_from' => 'draft',
            'status_to' => 'scheduled',
        ]);
});
