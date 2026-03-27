# Metadata

- Module code: `INV`
- Module name: `Inventory / Batches / Stock`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-INV-`
- Source review: `docs/reviews/modules/INV-inventory.md`
- Source issues: `docs/reviews/issues/INV-issues.md`
- Dependencies: `GOV, TRT, FIN, SUP, KPI`
- Last updated: `2026-03-06`

# Objective

- Dua module `INV` tu `D` len it nhat `B` baseline bang cach khoa 4 boundary nguy hiem nhat:
  - issue note posting batch-safe, traceable va lock-safe
  - material identity/stock invariants o DB va UI
  - destructive/manual mutation surfaces tren material va batch
  - branch-scoped, stock-aware selectors va regression suite
- Bao dam inventory khong mo lai drift cho `TRT`, `FIN` va `KPI`.

# Foundation fixes

## [TASK-INV-001] Chuyen phieu xuat vat tu sang batch-safe posting boundary
- Based on issue(s): `INV-001`
- Priority: Foundation
- Objective:
  - Moi dong issue item phai truy vet duoc den lo cu the, va posted issue note phai tru ton tong + ton lo dong bo.
- Scope:
  - `material_issue_items`
  - `MaterialIssueNote::post()` hoac service moi tuong duong
  - `ItemsRelationManager`
  - `inventory_transactions`
- Why now:
  - Day la lo hong nghiem trong nhat cua inventory; neu khong khoa ngay thi ton tong va ton theo lo tiep tuc drift trong flow chuan.
- Suggested implementation:
  - them `material_batch_id` vao `material_issue_items`
  - issue item form bat buoc chon batch active, dung branch, chua het han, du ton
  - post issue note lock note + items + materials + batches, tru quantity batch, cap nhat `stock_qty`, ghi `InventoryTransaction.material_batch_id`
  - thong bao ro khi batch khong hop le hoac khong du ton
- Affected files or layers:
  - `app/Models/MaterialIssueNote.php`
  - `app/Models/MaterialIssueItem.php`
  - `app/Filament/Resources/MaterialIssueNotes/RelationManagers/ItemsRelationManager.php`
  - migrations cho `material_issue_items` va co the rollout alignment `inventory_transactions`
- Tests required:
  - feature test batch-safe issue posting
  - feature test invalid batch / expired batch / cross-branch batch
  - retry/idempotency regression test
- Estimated effort: `L`
- Dependencies:
  - `TRT`
- Exit criteria:
  - issue note posted luon tao trace batch day du va khong con drift giua stock tong va stock lo trong happy path

# Critical fixes

## [TASK-INV-002] Chot SKU invariant va khoa edit truc tiep aggregate stock
- Based on issue(s): `INV-002`
- Priority: Critical
- Objective:
  - Dam bao identity cua material on dinh va ton tong khong bi sua tay tren CRUD thong thuong.
- Scope:
  - materials migration/model/form/table
  - rollout/reconcile du lieu ton tai
- Why now:
  - Sau khi da batch-safe duong xuat kho, can cat ngay duong drift khac tu master data.
- Suggested implementation:
  - chot unique strategy cho SKU (`global` hoac `(branch_id, sku)`)
  - them unique index o DB sau khi clean dupe data
  - bien `stock_qty` thanh read-only tren form thong thuong va cap nhat qua service/action co ly do
- Affected files or layers:
  - `materials` migration
  - `app/Models/Material.php`
  - `app/Filament/Resources/Materials/*`
- Tests required:
  - duplicate SKU tests
  - UI guard tests cho `stock_qty`
- Estimated effort: `M`
- Dependencies:
  - `TASK-INV-001`
- Exit criteria:
  - duplicate SKU bi chan o DB
  - `stock_qty` khong con editable tay tren flow CRUD chinh

# High priority fixes

## [TASK-INV-003] Go destructive va manual mutation surfaces tren material/batch
- Based on issue(s): `INV-003`
- Priority: High
- Objective:
  - Nguoi dung khong con sua/xoa du lieu kho nhay cam bang CRUD thong thuong sau khi da co downstream history.
- Scope:
  - materials tables/resources
  - material batches tables/resources
  - policies neu can
- Why now:
  - Day la duong drift thu hai lon nhat sau issue-note posting.
- Suggested implementation:
  - bo `DeleteBulkAction`, `ForceDeleteBulkAction`, `RestoreBulkAction` neu khong con phu hop
  - khoa edit `quantity/status` tren batch form thong thuong
  - neu can tao action rieng `adjust/reclassify/archive` co ly do + audit
- Affected files or layers:
  - `app/Filament/Resources/Materials/*`
  - `app/Filament/Resources/MaterialBatches/*`
  - related policies/models
- Tests required:
  - destructive surface guard tests
  - downstream history guard tests
- Estimated effort: `M`
- Dependencies:
  - `TASK-INV-001`
  - `TASK-INV-002`
- Exit criteria:
  - material/batch khong con bi mutate tuy tien qua CRUD thong thuong

## [TASK-INV-004] Scope tat ca inventory selectors theo branch va stock state
- Based on issue(s): `INV-004`
- Priority: High
- Objective:
  - Moi selector material/batch/supplier tren inventory flow chi hien option hop le theo branch va stock state.
- Scope:
  - `MaterialForm`
  - `MaterialBatchForm`
  - `ItemsRelationManager`
- Why now:
  - Sau khi co batch-safe issue items, neu selector van khong scope chat thi nguoi dung van de mis-post.
- Suggested implementation:
  - dung `BranchAccess` / authorizer helper cho moi relationship query
  - issue item chi hien batches con ton, active, chua het han, dung material/branch
  - sanitize payload server-side cho batch/material mismatch
- Affected files or layers:
  - inventory Filament forms/relation managers
  - helper/service authorizer neu can
- Tests required:
  - branch isolation tests cho selectors
  - forged payload rejection tests
- Estimated effort: `M`
- Dependencies:
  - `TASK-INV-001`
  - `GOV`
- Exit criteria:
  - khong con chon duoc option sai branch, sai batch, sai ton kho

## [TASK-INV-005] Centralize inventory mutation methods thanh transaction-safe boundary
- Based on issue(s): `INV-001`, `INV-005`
- Priority: High
- Objective:
  - Co 1 boundary chung cho consume/restore/adjust inventory de tranh drift khi module mo rong.
- Scope:
  - material/material batch service or model boundary
  - integration voi issue note va treatment usage
- Why now:
  - Neu khong chot boundary chung, fix `INV-001` chi giai quyet 1 path ma van de lap lai drift o path khac.
- Suggested implementation:
  - tao inventory mutation helper/service transaction-safe
  - `consumeBatch()`, `restoreBatch()`, `syncAggregateStock()` co lock va invariant ro rang
  - neu phu hop, refactor `TreatmentMaterialUsageService` va issue note cung dung boundary chung
- Affected files or layers:
  - `app/Models/MaterialBatch.php`
  - `app/Services/*`
  - issue note / treatment usage integration
- Tests required:
  - service boundary tests
  - restore/reverse mutation tests
- Estimated effort: `L`
- Dependencies:
  - `TASK-INV-001`
  - `TRT`
- Exit criteria:
  - inventory mutation co canonical path va duoc dung o moi flow write chinh

# Medium priority fixes

## [TASK-INV-006] Bo sung regression suite va rollout verification cho inventory baseline
- Based on issue(s): `INV-006`
- Priority: Medium
- Objective:
  - Chan regression cho inventory drift va kiem tra schema alignment truoc rollout.
- Scope:
  - tests feature cho inventory
  - rollout verification checklist/command neu can
- Why now:
  - Inventory drift thuong xuat hien sau refactor; can khoa test truoc khi sang `SUP` / `KPI`.
- Suggested implementation:
  - them test cho batch-safe issue note, duplicate SKU, destructive surfaces, branch-scoped selectors
  - bo sung verify buoc rollout cho migration `inventory_transactions.material_batch_id`
- Affected files or layers:
  - `tests/Feature/*`
  - rollout docs/commands neu can
- Tests required:
  - chinh task nay la backlog test
- Estimated effort: `M`
- Dependencies:
  - `TASK-INV-001`
  - `TASK-INV-002`
  - `TASK-INV-003`
  - `TASK-INV-004`
- Exit criteria:
  - co regression suite ro rang cho 4 lo hong lon nhat va rollout khong bi schema drift bat ngo

# Low priority fixes

- Chua co low-priority task rieng. Toan bo backlog `INV` hien tai deu lien quan truc tiep den an toan ton kho, truy vet lo va traceability.

# Testing & regression protection

- Bat buoc co tests cho:
  - issue note batch-safe posting
  - invalid batch / expired batch / cross-branch batch rejection
  - duplicate SKU invariant
  - destructive surface guards tren materials/batches
  - branch-scoped selectors va forged payload rejection
- Sau moi batch code change, chay it nhat test file lien quan va `vendor/bin/pint --dirty`.
- Truoc khi ket luan baseline, chay full suite de kiem tra drift voi `TRT` va `FIN`.

# Re-audit checklist

- Xac nhan issue note da yeu cau batch va ton lo giam dong bo voi ton tong.
- Xac nhan `InventoryTransaction` co batch context cho issue-note flow.
- Xac nhan SKU invariant da duoc khoa o DB.
- Xac nhan `stock_qty`, `batch quantity`, `batch status` khong con mutate tuy tien qua CRUD thong thuong.
- Xac nhan selectors inventory da branch-scoped va stock-aware.
- Xac nhan regression suite moi pass va full suite khong mo lai drift lien module.

# Execution order

1. `TASK-INV-001`
2. `TASK-INV-002`
3. `TASK-INV-003`
4. `TASK-INV-004`
5. `TASK-INV-005`
6. `TASK-INV-006`

# What can be done in parallel

- `TASK-INV-003` va `TASK-INV-004` co the lam song song mot phan sau khi `TASK-INV-001` chot field `material_batch_id` va posting boundary.
- `TASK-INV-006` co the viet dan test theo tung task, nhung chi chot sau khi boundary chinh on dinh.

# What must be done first

- `TASK-INV-001` phai lam truoc vi no khoa lo hong nghiem trong nhat va dinh nghia batch boundary cho ca module.
- `TASK-INV-002` nen di ngay sau do de cat duong drift tu master data.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-INV-001`
- Milestone 2:
  - `TASK-INV-002`
  - `TASK-INV-003`
- Milestone 3:
  - `TASK-INV-004`
  - `TASK-INV-005`
  - `TASK-INV-006`
