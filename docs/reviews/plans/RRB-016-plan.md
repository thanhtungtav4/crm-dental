# RRB-016 Plan — Production-like Dataset Smoke Tests & MPI Merge Reparenting Chain

**Status:** Completed
**Date:** 2026-04-16
**Branch:** codex/wip-popup-risk-followups

---

## Objective

Validate report aggregation correctness under realistic multi-branch dataset volume and verify that the MPI merge/rollback pipeline correctly reparents `TreatmentPlan` and `Invoice` records.

---

## Test File

`tests/Feature/ProductionDatasetSmokeAndMpiChainTest.php`

---

## Test Cases (7 tests, 23 assertions)

### Financial Report Aggregation (5 tests)
| # | Scenario | Expected |
|---|---|---|
| 1 | Single branch, 5 invoices | `total_amount` and `paid_amount` match seeded values |
| 2 | Two branches, `branchIds=null` (global) | sum ≥ both branches combined |
| 3 | Branch A scope | does NOT include branch B invoices |
| 4 | `branchIds=[]` | returns all zeroes |
| 5 | Date range filter | excludes last-month invoice, includes today's invoice |

### MPI Merge / Rollback Reparenting (2 tests)
| # | Scenario | Expected |
|---|---|---|
| 6 | `mpi:merge` | TreatmentPlan + Invoice reassigned to canonical; merged patient → inactive |
| 7 | `mpi:merge-rollback` | TreatmentPlan + Invoice restored to merged patient; merged → active |

---

## Key Notes

- `FinancialReportReadModelService::invoiceBalanceSummary()` scoped by `branch_id` on `invoices` table
- MPI merge uses `mpi:merge` artisan command (bypasses `ActionGate` in test context)
- MPI rollback command is `mpi:merge-rollback` (not `mpi:rollback`)
- `TreatmentPlan::factory()` requires `patient_id` + `branch_id`
