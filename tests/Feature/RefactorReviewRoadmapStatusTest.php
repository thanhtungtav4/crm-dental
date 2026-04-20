<?php

it('keeps the refactor review roadmap closed when every RRB item is completed', function (): void {
    $masterBacklog = file_get_contents(base_path('docs/roadmap/refactor-review-master-backlog.md'));
    $executionPlan = file_get_contents(base_path('docs/roadmap/refactor-review-execution-plan.md'));
    $programSummary = file_get_contents(base_path('docs/reviews/program-audit-summary.md'));

    expect($masterBacklog)->not->toContain('Status: `In progress`')
        ->and($executionPlan)->not->toContain('Status: `In progress`')
        ->and($programSummary)->toContain('Backlog RRB-001..RRB-016: `Completed`')
        ->and($programSummary)->not->toContain('Dang trien khai')
        ->and($programSummary)->not->toContain('Tiep theo uu tien RRB-012 va RRB-013');

    preg_match_all('/## \\[RRB-\\d{3}\\][\\s\\S]*?- Status: `([^`]+)`/', $masterBacklog, $matches);

    expect($matches[1])
        ->toHaveCount(16)
        ->each->toBe('Completed');

    foreach (['Phase 4', 'Phase 5', 'Phase 6'] as $phaseName) {
        expect($executionPlan)->toMatch("/## {$phaseName}[^\\n]*\\n\\n- Status: `Completed`/");
    }
});
