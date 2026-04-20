# RRB-015 Plan — KPI Snapshot SLA, ZNS Dead-letter Threshold & OPS Release Gate Contract

**Status:** Completed
**Date:** 2026-04-16
**Branch:** codex/wip-popup-risk-followups

---

## Objective

Validate three observability/operations contracts that must remain stable across releases:

1. `ReportSnapshotSlaService::resolveSlaStatus()` classification logic
2. `ZnsOperationalReadModelService::automationDeadCount()` dead-letter counting
3. `OpsReleaseGateCatalog::steps()` / `requiredProductionCommands()` catalog completeness

---

## Test File

`tests/Feature/KpiSnapshotSlaAndOpsReleaseGateContractTest.php`

---

## Test Cases (12 tests, 43 assertions)

### SLA Classification Contract (6 tests)
| # | Scenario | Expected |
|---|---|---|
| 1 | `generated_at` within SLA window, not today | `on_time` |
| 2 | `generated_at > sla_due_at` | `late` |
| 3 | Today's snapshot, `generated_at` older than stale cutoff (24 h) | `stale` |
| 4 | `generated_at = null` | `missing` |
| 5 | `checkScope()` — no snapshot in DB | `missing=1`, placeholder persisted |
| 6 | `checkScope()` dry-run | `missing=1`, **no** DB write |

### ZNS Dead-letter Threshold (3 tests)
| # | Scenario | Expected |
|---|---|---|
| 7 | Seed 1 `STATUS_DEAD` event | count increases by 1 |
| 8 | Seed 2 events of different `event_type` | scoped counts ≥ 1 each, total ≥ sum |
| 9 | Query unused `event_type` | returns 0 |

### OPS Release Gate Catalog (3 tests)
| # | Scenario | Expected |
|---|---|---|
| 10 | `steps('ci', false)` | non-empty array, each step has `name/command/arguments` |
| 11 | `requiredProductionCommands(false)` | includes base gates: schema drift, FK, action permission |
| 12 | `requiredProductionCommands(false)` | includes `security:check-automation-actor`, `ops:check-backup-health` |

---

## Key Service APIs Used

```php
// ReportSnapshotSlaService
resolveSlaStatus(ReportSnapshot $snapshot, Carbon $staleCutoff): string
checkScope(string $snapshotKey, Carbon $snapshotDate, ?int $branchId, bool $dryRun, ?int $actorId): array

// ZnsOperationalReadModelService
automationDeadCount(?string $eventType = null, ?array $branchIds = null): int

// OpsReleaseGateCatalog
static steps(string $profile, bool $withFinance, ?string $from = null, ?string $to = null): array
static requiredProductionCommands(bool $withFinance): array
```

---

## Regression

No pre-existing tests broken. 8 pre-existing failures in `RoleHotPathPageSmokeTest` confirmed as baseline failures (unrelated to this RRB).
