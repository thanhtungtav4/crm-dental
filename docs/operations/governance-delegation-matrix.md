# Governance Delegation Matrix

Cap nhat: 2026-03-28

## Muc tieu

- Chot baseline delegation matrix cho role, page, va sensitive action sau baseline hardening.
- Giam drift giua:
  - `database/seeders/RolesAndPermissionsSeeder.php`
  - `app/Support/SensitiveActionRegistry.php`
  - `security:assert-governance-resource-baseline`
  - `security:assert-action-permission-baseline`

## Source of truth

- Resource/page baseline:
  - `database/seeders/RolesAndPermissionsSeeder.php`
- Sensitive action matrix:
  - `app/Support/SensitiveActionRegistry.php`
  - `app/Support/ActionPermission.php`
- Runtime drift gates:
  - `php artisan security:assert-governance-resource-baseline --no-interaction`
  - `php artisan security:assert-action-permission-baseline --no-interaction`

## Seeded baseline roles

### `Admin`

- Scope:
  - toan bo resource permissions
  - toan bo page permissions
  - toan bo `Action:*`
- Van hanh:
  - co the chay release gates, production readiness, sync baseline, backfill, restore drill
  - la role escalation cho governance, integration runtime, va OPS control-plane

### `Manager`

- Scope:
  - CRUD tren resource van hanh ngoai governance resources
  - duoc vao:
    - `FrontdeskControlCenter`
    - `DeliveryOpsCenter`
    - `FinancialDashboard`
    - `DentalChainReport`
    - `DentalApp`
- Sensitive actions:
  - `Action:PaymentReversal`
  - `Action:WalletAdjust`
  - `Action:AppointmentOverride`
  - `Action:PlanApproval`
  - `Action:AutomationRun`
  - `Action:MasterDataSync`
  - `Action:InsuranceClaimDecision`
  - `Action:MpiDedupeReview`
  - `Action:PatientBranchTransfer`
  - `Action:EmrClinicalWrite`
  - `Action:EmrEvidenceOverride`
  - `Action:EmrRecordExport`
  - `Action:EmrSyncPush`

### `Doctor`

- Scope:
  - `Patient`: `ViewAny`, `View`, `Update`
  - `TreatmentPlan`: `ViewAny`, `View`, `Update`
  - `TreatmentSession`: `ViewAny`, `View`, `Create`, `Update`
  - `Appointment`: `ViewAny`, `View`, `Create`, `Update`
  - `DeliveryOpsCenter`
- Sensitive actions:
  - `Action:AppointmentOverride`
  - `Action:PlanApproval`
  - `Action:EmrClinicalWrite`
  - `Action:EmrRecordExport`

### `CSKH`

- Scope:
  - `Customer`: `ViewAny`, `View`, `Create`, `Update`
  - `Patient`: `ViewAny`, `View`, `Create`
  - `Appointment`: `ViewAny`, `View`, `Create`, `Update`
  - `Note`: `ViewAny`, `View`, `Create`
  - `FrontdeskControlCenter`
- Sensitive actions:
  - `Action:PatientBranchTransfer`
  - `Action:AutomationRun`
- Luu y:
  - `Action:AutomationRun` chi la baseline permission.
  - Moi command/service van phai tu guard tiep actor scope va branch scope o runtime.

### `AutomationService`

- Scope:
  - service account cho scheduler / OPS control-plane
  - khong dung lam role thao tac Filament hang ngay
- Sensitive actions:
  - `Action:AutomationRun`
  - `Action:EmrSyncPush`

## Non-baseline roles

### `Assistant`

- Chua la seeded role rieng trong baseline hien tai.
- Neu can tach role nay, phai di qua:
  - update matrix tai seeder + registry
  - regression tests
  - review rollout blast radius

### `Finance`

- Chua la seeded role rieng trong baseline hien tai.
- Cac surface tai chinh baseline dang duoc quan ly boi `Admin` / `Manager`.
- Tach role `Finance` phai duoc xem nhu batch auth/scope rieng, khong add ad-hoc.

## Sensitive action matrix

| Permission | Allowed roles |
| --- | --- |
| `Action:PaymentReversal` | `Admin`, `Manager` |
| `Action:WalletAdjust` | `Admin`, `Manager` |
| `Action:AppointmentOverride` | `Admin`, `Manager`, `Doctor` |
| `Action:PlanApproval` | `Admin`, `Manager`, `Doctor` |
| `Action:AutomationRun` | `Admin`, `Manager`, `CSKH`, `AutomationService` |
| `Action:MasterDataSync` | `Admin`, `Manager` |
| `Action:InsuranceClaimDecision` | `Admin`, `Manager` |
| `Action:MpiDedupeReview` | `Admin`, `Manager` |
| `Action:PatientBranchTransfer` | `Admin`, `Manager`, `CSKH` |
| `Action:EmrClinicalWrite` | `Admin`, `Manager`, `Doctor` |
| `Action:EmrEvidenceOverride` | `Admin`, `Manager` |
| `Action:EmrRecordExport` | `Admin`, `Manager`, `Doctor` |
| `Action:EmrSyncPush` | `Admin`, `Manager`, `AutomationService` |

## Operational checklist

### Sau moi deploy co thay doi permission

```bash
php artisan security:assert-governance-resource-baseline --no-interaction
php artisan security:assert-action-permission-baseline --no-interaction
```

### Neu production DB bi drift

```bash
php artisan security:assert-governance-resource-baseline --sync --no-interaction
php artisan security:assert-action-permission-baseline --sync --no-interaction
```

## Review rules

- Khong them role moi ma khong cap nhat matrix nay.
- Khong them `Action:*` moi ma khong cap nhat:
  - `ActionPermission`
  - `SensitiveActionRegistry`
  - regression tests
  - tai lieu nay
- Khong xem page visibility la du thay cho mutation guard; service/model boundary van la lane authorize cuoi cung.
