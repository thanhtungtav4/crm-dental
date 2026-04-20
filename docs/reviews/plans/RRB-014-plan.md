# RRB-014 Plan — Cross-module TRT/INV/FIN/SUP Chain Smoke Tests

**Status:** Completed
**Date:** 2026-04-16
**Branch:** codex/wip-popup-risk-followups

---

## Objective

Validate end-to-end data integrity across the Treatment → Inventory → Finance pipeline:

- Usage creation decreases stock
- Usage reversal restores stock and is idempotent
- InventoryTransaction immutability (delete blocked)
- Double-reversal idempotency via `reversalAlreadyRecorded()`
- Audit log records entity + action correctly
- Invoice → Payment → Wallet DEBIT flow
- Payment reversal → Wallet CREDIT
- Branch attribution consistency across all entities

---

## Test File

`tests/Feature/CrossModuleTrtInvFinChainSmokeTest.php`

---

## Test Cases (10 tests, 28 assertions)

| # | Scenario |
|---|---|
| 1 | Usage creation decreases batch quantity |
| 2 | Usage reversal restores batch quantity |
| 3 | InventoryTransaction cannot be deleted (immutable) |
| 4 | Double reversal is idempotent |
| 5 | Audit log records reversal with correct entity |
| 6 | Invoice subtotal stored correctly |
| 7 | Payment creates wallet DEBIT entry |
| 8 | PaymentReversalService creates wallet CREDIT entry |
| 9 | Branch attribution consistent across invoice/payment/wallet |
| 10 | Patient attribution consistent across treatment/invoice |

---

## Key Fixes During Development

1. Invoice: column `subtotal` not `total`
2. InventoryTransaction: query by `treatment_session_id + type` (no `treatment_material_id` FK)
3. Double-reversal: pass `$usage` (soft-deleted), service handles `reversalAlreadyRecorded()` guard
4. Audit: `ENTITY_TREATMENT_SESSION + session->id` not `treatment_material`
5. `PaymentReversalService::reverse()`: 2nd arg is `float $amount`, not `User`
6. `payment->amount`: string decimal → use `(float)` cast + `toEqual()` not `toBe()`
