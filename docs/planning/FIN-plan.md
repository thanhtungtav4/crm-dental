# Metadata

- Module code: `FIN`
- Module name: `Finance / Payments / Wallet / Installments`
- Current status: `In Fix`
- Current verdict: `D`
- Task ID prefix: `TASK-FIN-`
- Source review: `docs/reviews/modules/FIN-finance.md`
- Source issues: `docs/issues/FIN-issues.md`
- Dependencies: `GOV, PAT, APPT, TRT, INV, KPI`
- Last updated: `2026-03-06`

# Objective

- Dua module `FIN` tu `D` len it nhat `B` baseline bang cach khoa 4 boundary nguy hiem nhat:
  - wallet authorization va wallet adjustment
  - invoice lifecycle/cancel/void boundary
  - refund/reversal concurrency va idempotency
  - destructive delete surface va branch-scoped finance actor attribution
- Bao dam `FIN` khong mo lai regression cho `TRT`, `KPI` va `PAT`.

# Foundation fixes

## [TASK-FIN-001] Khoa wallet resource bang policy, action permission va audit boundary
- Based on issue(s): `FIN-001`
- Priority: Foundation
- Objective:
  - Chi role finance hop le moi xem/adjust duoc ví bệnh nhân.
- Scope:
  - `PatientWalletResource`
  - wallet policy
  - wallet adjustment service/action
  - audit log
- Why now:
  - Day la lo hong phan quyen tai chinh nghiem trong nhat hien tai.
- Suggested implementation:
  - tao `PatientWalletPolicy`
  - them `ActionPermission::WALLET_ADJUST`
  - doi `adjustBalance()` sang boundary co authorize + audit
  - khoa wallet surface ve read-only cho role khong hop le
- Affected files or layers:
  - `app/Filament/Resources/PatientWallets/*`
  - `app/Policies/*`
  - `app/Services/PatientWalletService.php`
  - seed/baseline permission neu can
- Tests required:
  - wallet auth feature tests
  - adjustment audit tests
- Estimated effort: `M`
- Dependencies:
  - `GOV`
- Exit criteria:
  - role ngoai finance khong the view/edit/adjust wallet
  - moi adjustment deu co permission + audit log day du

# Critical fixes

## [TASK-FIN-002] Chuyen invoice state transitions ve workflow service canonical
- Based on issue(s): `FIN-002`
- Priority: Critical
- Objective:
  - Khong cho create/edit invoice di thang vao `cancelled` va khong cho cancel sai workflow.
- Scope:
  - invoice model/form/page/table
  - workflow service moi
- Why now:
  - Invoice state la source of truth cho aging, collection, KPI.
- Suggested implementation:
  - form create/edit chi cho state hop le
  - tao `InvoiceWorkflowService` cho `issue/cancel/void`
  - chan direct update `cancelled`
  - downstream check payment/installment/reminder truoc khi cancel
- Affected files or layers:
  - `app/Models/Invoice.php`
  - `app/Filament/Resources/Invoices/*`
  - service moi trong `app/Services`
- Tests required:
  - feature test cancel hole
  - workflow metadata tests
- Estimated effort: `M`
- Dependencies:
  - `TASK-FIN-001`
- Exit criteria:
  - invoice khong con bypass workflow qua form/table/page

## [TASK-FIN-003] Tao reversal service idempotent va lock-safe
- Based on issue(s): `FIN-003`
- Priority: Critical
- Objective:
  - Moi payment goc chi co toi da 1 reversal canonical cho moi lan xu ly hop le.
- Scope:
  - payment model
  - refund actions trong UI
  - wallet posting / invoice balance / audit
- Why now:
  - Refund double-post se lam sai doanh thu, cong no va so du vi ngay lap tuc.
- Suggested implementation:
  - tao `PaymentReversalService`
  - `lockForUpdate()` original payment + invoice
  - unique/index guard cho reversal
  - retry tra ve reversal hien co
- Affected files or layers:
  - `app/Models/Payment.php`
  - `app/Filament/Resources/Payments/*`
  - `app/Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php`
  - migration neu can them unique/index
- Tests required:
  - concurrency test concurrent refund
  - idempotent retry test
- Estimated effort: `L`
- Dependencies:
  - `TASK-FIN-002`
- Exit criteria:
  - khong the tao nhieu reversal cho cung payment goc do race/retry

# High priority fixes

## [TASK-FIN-004] Loai destructive finance surfaces va chot immutable history boundary
- Based on issue(s): `FIN-004`
- Priority: High
- Objective:
  - Chan delete/force delete/bulk delete tren invoice va finance core neu da co downstream records.
- Scope:
  - invoice resource/page/table/policy
  - related FK strategy follow-up neu can
- Why now:
  - Sau khi workflow va reversal an toan hon, destructive surface la duong tat gay drift con lai lon nhat.
- Suggested implementation:
  - bo `DeleteAction` / `DeleteBulkAction`
  - siết policy delete/restore/forceDelete
  - neu can thi them delete guard service
- Affected files or layers:
  - `app/Filament/Resources/Invoices/*`
  - `app/Policies/InvoicePolicy.php`
  - migrations follow-up cho FK/restrict strategy
- Tests required:
  - feature test destructive surfaces bi go bo
  - feature test invoice co downstream records khong delete duoc
- Estimated effort: `M`
- Dependencies:
  - `TASK-FIN-002`
- Exit criteria:
  - finance history khong con bi xoa tuy tien tren UI/policy

## [TASK-FIN-005] Branch-scope `received_by` va sanitize server-side
- Based on issue(s): `FIN-005`
- Priority: High
- Objective:
  - Chi gan duoc nguoi thu/hoan tien trong pham vi chi nhanh hop le.
- Scope:
  - payment forms/pages/relation managers
  - finance actor authorizer moi
- Why now:
  - Sai attribution nguoi thu la loi van hanh xuat hien rat som o phong kham nhieu chi nhanh.
- Suggested implementation:
  - tao `FinanceActorAuthorizer`
  - scope relationship query theo branch
  - sanitize payload server-side o create/refund flow
- Affected files or layers:
  - `app/Filament/Resources/Payments/*`
  - `app/Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php`
  - service authorizer moi
- Tests required:
  - branch-scoped receiver options tests
  - forged payload rejection tests
- Estimated effort: `M`
- Dependencies:
  - `TASK-FIN-001`
- Exit criteria:
  - khong con gan duoc `received_by` sai chi nhanh

# Medium priority fixes

## [TASK-FIN-006] Gom finance write logic ve service canonical de giam drift
- Based on issue(s): `FIN-006`
- Priority: Medium
- Objective:
  - Moi surface UI cua payment/refund dung chung service boundary.
- Scope:
  - create payment page
  - invoice table action
  - payments table action
  - invoice payments relation manager
- Why now:
  - Sau khi da harden workflow va reversal, can thu gon de tranh mo lai bug do drift.
- Suggested implementation:
  - tao `PaymentRecordingService`
  - service chung cho notification/result object neu can
- Affected files or layers:
  - finance Filament surfaces
  - service layer
- Tests required:
  - regression tests xac nhan surface deu dung cung service
- Estimated effort: `M`
- Dependencies:
  - `TASK-FIN-003`
  - `TASK-FIN-005`
- Exit criteria:
  - khong con duplicate domain write logic o UI layer

# Low priority fixes

- Chua co low-priority task rieng. Toan bo backlog FIN hien tai deu tac dong truc tiep den an toan tai chinh va traceability.

# Testing & regression protection

## [TASK-FIN-007] Bo sung regression suite cho wallet auth, invoice workflow va refund concurrency
- Based on issue(s): `FIN-007`, `FIN-001`, `FIN-002`, `FIN-003`, `FIN-005`
- Priority: Medium
- Objective:
  - Chan regression cho cac boundary tai chinh quan trong nhat.
- Scope:
  - tests feature/concurrency cho wallet, invoice, payment/refund
- Why now:
  - FIN la module tien; khong co regression suite thi moi refactor deu qua nguy hiem.
- Suggested implementation:
  - them test wallet resource auth
  - them test cancel invoice co payment
  - them test concurrent refund / duplicate reversal
  - them test receiver scope
- Affected files or layers:
  - `tests/Feature/*`
- Tests required:
  - chinh task nay la backlog test
- Estimated effort: `M`
- Dependencies:
  - `TASK-FIN-001`
  - `TASK-FIN-002`
  - `TASK-FIN-003`
  - `TASK-FIN-005`
- Exit criteria:
  - co regression suite ro rang cho 4 lo hong nghiem trong nhat cua FIN

# Re-audit checklist

- Xac nhan wallet resource da co policy va chi role duoc phep moi adjust duoc.
- Xac nhan invoice khong con cancel truc tiep qua form edit.
- Xac nhan refund/reversal chi tao mot lan duoi concurrent retry.
- Xac nhan delete surfaces sai nghiep vu da bi go bo.
- Xac nhan `received_by` da branch-scoped va forged payload bi chan.
- Xac nhan regression suite moi deu pass.
- Danh gia lai verdict va clean baseline status.

# Execution order

1. `TASK-FIN-001`
2. `TASK-FIN-002`
3. `TASK-FIN-003`
4. `TASK-FIN-004`
5. `TASK-FIN-005`
6. `TASK-FIN-006`
7. `TASK-FIN-007`

# What can be done in parallel

- `TASK-FIN-005` co the di song song mot phan sau khi `TASK-FIN-001` chot authorizer/policy boundary.
- `TASK-FIN-006` co the bat dau cuoi `TASK-FIN-003` khi service canonical da ro.
- `TASK-FIN-007` co the viet dan theo tung task, nhung chi chot sau khi logic boundary da on dinh.

# What must be done first

- `TASK-FIN-001` phai di truoc de khoa wallet authorization.
- `TASK-FIN-002` va `TASK-FIN-003` phai di tiep theo vi day la hai domain hole nguy hiem nhat cua module tien.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-FIN-001`
  - `TASK-FIN-002`
- Milestone 2:
  - `TASK-FIN-003`
  - `TASK-FIN-005`
- Milestone 3:
  - `TASK-FIN-004`
  - `TASK-FIN-006`
  - `TASK-FIN-007`
