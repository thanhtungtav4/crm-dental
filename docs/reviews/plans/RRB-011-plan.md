# RRB-011 Plan — Immutable Adjustment and Reversal Ledger Strategy

**status**: Completed
**last_updated**: 2026-04-16
**owner**: codex

---

## Objective

Ensure wallet ledger entries and inventory transactions are immutable after creation, reversal semantics are correct (opposite-direction ledger entries), and the end-to-end reconciliation path (payment → wallet → audit) is covered by tests.

---

## Scope

| Module | Service / Model | Description |
|---|---|---|
| `PatientWalletService` | `postPayment()` | deposit/spend/refund/reversal ledger entries |
| `PatientWalletService` | `adjustBalance()` | manual adjustment with audit log |
| `PaymentReversalService` | `reverse()` | idempotent reversal with audit log |
| `WalletLedgerEntry` | Observer/immutability | blocks update + delete |
| `InventoryTransaction` | Observer/immutability | blocks update + delete |
| `PaymentObserver` | `created()` | auto-posts payment to wallet, records audit |

---

## Test Files

### New (written this session)

| File | Tests | Assertions | Coverage |
|---|---|---|---|
| `WalletLedgerReconciliationTest.php` | 8 | ~30 | `postPayment` deposit/spend/refund/refund; idempotency; adjust audit; negative-balance guard |
| `PaymentReversalConcurrencyTest.php` | 5 | ~20 | concurrent reversal → single record; idempotency; audit log; wallet CREDIT on reversal; amount validation |
| `ImmutableLedgerBoundaryContractTest.php` | 6 | ~15 | `WalletLedgerEntry` update+delete blocked; `firstOrCreate` idempotency; `InventoryTransaction` update+delete blocked; reversal creates CREDIT opposite to original DEBIT |

### Pre-existing (all pass)

| File | Tests | Notes |
|---|---|---|
| `PaymentLedgerImmutabilityTest.php` | 3 | Payment blocks edit/delete; `markReversed()` metadata |
| `PatientWalletAdjustmentAuditTest.php` | 3 | Permission guard; audit+ledger; WalletLedgerEntry immutability |
| `TreatmentMaterialUsageServiceTest.php` | 6 | Usage creation+ledger+audit; service boundary; actor enforcement; InventoryTransaction immutability; reversal audit; idempotency |
| `PaymentReversalAuditLogTest.php` | 1 | Audit on reversal |
| `PaymentReversalServiceTest.php` | 6 | Canonical reversal; idempotency; concurrent submissions; refund surface routing |

---

## Key Patterns Documented

### 1. Immutability via Observer
`WalletLedgerEntry` and `InventoryTransaction` have model observers that throw `ValidationException` on `update()` or `delete()`. This is tested at the model level.

### 2. Idempotency via `firstOrCreate`
`PatientWalletService::postPayment()` uses `firstOrCreate(['payment_id', 'entry_type'])` so repeated calls (including concurrent) never produce duplicate ledger entries.

### 3. Reversal = Opposite Direction
`resolveEntryFromPayment()` looks up the original payment's ledger entry direction and flips it: DEBIT → CREDIT, CREDIT → DEBIT.

### 4. Dual Audit Pattern
`PaymentObserver::created()` records a lightweight audit on every Payment create. `PaymentReversalService::reverse()` records a richer audit with `status_from/status_to/reversal_of_id`. Both entries coexist — tests filter by `isset(metadata['status_from'])` to find the Service-level audit.

### 5. Balance Chain
Each `WalletLedgerEntry` captures `balance_before` and `balance_after`. The `balance_after` of entry N should equal the `balance_before` of entry N+1 (sequential posting).

---

## Test Results Summary

```
38 tests, 148 assertions — all PASS (full RRB-011 suite)
```
