# RRB-012 Plan — Report Scope, Export, and Date-Range Boundary Tests

**status**: Completed
**last_updated**: 2026-04-16
**owner**: codex

---

## Objective

Ensure all report read-model services correctly enforce date-range boundary filtering, branch scope isolation, and zero-result handling for out-of-range data.

---

## Scope

| Module | Service | Description |
|---|---|---|
| `FinancialReportReadModelService` | `cashflowSummary()` | Filters `ReceiptExpense` by `voucher_date` range |
| `AppointmentReportReadModelService` | `appointmentSummary()` | Filters appointments by `scheduled_at` range |
| `PatientInsightReportReadModelService` | `patientSummary()` | Filters patients by `created_at` range |
| `InventorySupplyReportReadModelService` | `materialInventorySummary()` | Filters materials by `created_at` + branch |

---

## Test Files

### New (written this session)

| File | Tests | Assertions | Coverage |
|---|---|---|---|
| `ReportDateRangeBoundaryContractTest.php` | 9 | 19 | cashflow in/out-of-range; multi-day range; empty branch filter; expense vs receipt aggregate; appointment boundary; patient boundary; material branch isolation; null branchIds admin view |

### Pre-existing (all pass, 69 tests)

| File | Notes |
|---|---|
| `HotReportAggregateReadModelServiceTest.php` | Revenue + care aggregates, live data |
| `FinancialReportReadModelServiceTest.php` | Cashflow/invoice balance by branch |
| `FinancialDashboardReadModelServiceTest.php` | Dashboard widgets + overdue invoices |
| `FinanceOperationalReadModelServiceTest.php` | Finance operational signals |
| `PatientInsightReportReadModelServiceTest.php` | Patient + risk insights |
| `InventorySupplyReportReadModelServiceTest.php` | Inventory + supplier reports |
| `AppointmentReportReadModelServiceTest.php` | Appointment metrics |
| `ReportSnapshotComparisonServiceTest.php` | Drift-aware snapshot comparison |
| `ReportSnapshotSlaServiceTest.php` | SLA classification + persistence |

---

## Key Findings

- `patientSummary()` returns key `total_patients` (not `total`)
- `materialInventorySummary()` queries `Material` model by `created_at` (not `TreatmentMaterial`)
- All finance services accept `null $branchIds` for unrestricted admin view
- `AppointmentFactory` creates a `Patient` internally — use existing Patient via `patient_id` override
- `cashflowSummary()` filters on `voucher_date` (date column, not timestamp)

---

## Results

**Total (new + existing)**: 78 tests | 766 assertions | 0 failures
