# Metadata

- Module code: `TRT`
- Module name: `Treatment Plans / Sessions / Materials usage`
- Current status: `In Fix`
- Current verdict: `D`
- Task ID prefix: `TASK-TRT-`
- Source review: `docs/reviews/modules/TRT-treatment.md`
- Source issues: `docs/issues/TRT-issues.md`
- Dependencies: `PAT, APPT, CLIN, INV, FIN`
- Last updated: `2026-03-06`

# Objective

- Dua module `TRT` tu `D` len it nhat `B` baseline bang cach khoa 4 boundary nguy hiem nhat:
  - batch-safe material usage
  - treatment plan workflow/approval boundary
  - treatment progress sync idempotency
  - destructive delete surface va branch-scoped staff assignment
- Bao dam `TRT` khong mo lai regression cho `CLIN`, dong thoi khong gay drift sang `INV` va `FIN`.

# Foundation fixes

## [TASK-TRT-001] Batch-safe material usage service voi transaction va inventory traceability
- Based on issue(s): `TRT-001`
- Priority: Foundation
- Objective:
  - Bien ghi nhan vat tu trong treatment session thanh flow inventory-safe, traceable theo batch.
- Scope:
  - `TreatmentMaterial` model/resource/form
  - `Material`, `MaterialBatch`, `InventoryTransaction`
  - service moi cho consume/revert usage
- Why now:
  - Day la blocker inventory/data-integrity lon nhat cua TRT va la giao diem truc tiep voi `INV`.
- Suggested implementation:
  - Tao `TreatmentMaterialUsageService` transaction-safe.
  - Bat buoc chon batch hop le trong form.
  - `lockForUpdate()` material + batch + usage boundary.
  - Tru `material_batches.quantity` va cap nhat aggregate `materials.stock_qty` trong cung transaction.
  - Ghi inventory transaction voi context day du.
- Affected files or layers:
  - `app/Models/TreatmentMaterial.php`
  - `app/Filament/Resources/TreatmentMaterials/*`
  - `app/Models/Material.php`
  - `app/Models/MaterialBatch.php`
  - migration neu can bo sung relation/index/context
- Tests required:
  - Concurrency test oversell stock
  - Feature test batch quantity + aggregate stock sync
  - Feature test khong duoc chon batch khong hop le
- Estimated effort: `L`
- Dependencies:
  - `INV`
- Exit criteria:
  - Moi treatment material usage deu gan batch hop le va tru ton transaction-safe
  - Ledger doi chieu duoc voi batch va aggregate stock

# Critical fixes

## [TASK-TRT-002] Khoa treatment plan workflow boundary va approval transition
- Based on issue(s): `TRT-002`
- Priority: Critical
- Objective:
  - Khong cho tao hoac mutate ke hoach dieu tri sang state cao neu chua qua workflow approval.
- Scope:
  - treatment plan model/resource/form/table
  - workflow service moi cho plan transition
- Why now:
  - Day la domain hole nghiem trong nhat cua TRT; neu khong khoa, moi guard khac deu co the bi bypass.
- Suggested implementation:
  - Form create chi cho `draft`.
  - Tao `TreatmentPlanWorkflowService` cho `approve/start/complete/cancel`.
  - Gate permission ro rang, luu `approved_by`, `approved_at`, audit metadata.
  - Chan direct update `status` ngoai workflow service.
- Affected files or layers:
  - `app/Models/TreatmentPlan.php`
  - `app/Filament/Resources/TreatmentPlans/*`
  - service moi trong `app/Services`
- Tests required:
  - Feature test create plan khong duoc vao `approved/in_progress`
  - Feature test chi actor hop le moi transition duoc
  - Feature test metadata approval duoc set day du
- Estimated effort: `M`
- Dependencies:
  - `GOV`, `FIN`
- Exit criteria:
  - Khong con bypass state machine treatment plan tu form/table action
  - Transition cap plan di qua service canonical duy nhat

## [TASK-TRT-003] Refactor treatment progress sync thanh idempotent va reuse provisioning service
- Based on issue(s): `TRT-003`
- Priority: Critical
- Objective:
  - Khoa race-condition khi session sync sang progress day/item va exam session.
- Scope:
  - `TreatmentProgressSyncService`
  - `TreatmentSessionObserver`
  - exam session linkage
- Why now:
  - `CLIN` da co provisioning service transactional; TRT dang bypass va mo lai risk cu.
- Suggested implementation:
  - Dung `ExamSessionProvisioningService` thay cho direct `ExamSession::create()`.
  - Bao `progress_day` + `progress_item` trong transaction.
  - Xu ly unique collision thanh re-entrant behavior on dinh.
- Affected files or layers:
  - `app/Services/TreatmentProgressSyncService.php`
  - `app/Observers/TreatmentSessionObserver.php`
  - migration/index neu can bo sung unique/composite guard
- Tests required:
  - Concurrency/idempotency tests cho same-session sync
  - Feature test khong tao duplicate exam session/progress day
- Estimated effort: `M`
- Dependencies:
  - `CLIN`, `APPT`
- Exit criteria:
  - Treatment session sync idempotent duoi concurrent updates
  - Khong bypass provisioning boundary da harden o CLIN

# High priority fixes

## [TASK-TRT-004] Branch-scope staff selectors va sanitize server-side
- Based on issue(s): `TRT-004`
- Priority: High
- Objective:
  - Chan cross-branch assignment sai scope trong treatment flow.
- Scope:
  - treatment plan/session/material forms
  - legacy relation managers lien quan
- Why now:
  - Sau GOV/PAT/APPT/CLIN baseline, day la lo hong governance con lai ro nhat trong TRT.
- Suggested implementation:
  - Loc `doctor_id`, `assistant_id`, `used_by` theo branch accessible/doctor assignment.
  - Re-validate payload o page/resource mutate hooks.
  - Helper text UX ro rang khi option dang chon khong thuoc scope.
- Affected files or layers:
  - `app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanForm.php`
  - `app/Filament/Resources/TreatmentSessions/Schemas/TreatmentSessionForm.php`
  - `app/Filament/Resources/TreatmentMaterials/Schemas/TreatmentMaterialForm.php`
  - `app/Filament/Resources/TreatmentPlans/Relations/*`
- Tests required:
  - Feature test branch-scoped options
  - Feature test forged payload bi reject
- Estimated effort: `M`
- Dependencies:
  - `GOV`, `PAT`
- Exit criteria:
  - Khong con gan duoc staff ngoai branch scope trong TRT hot path

## [TASK-TRT-005] Loai destructive surfaces va them delete guard cho treatment core
- Based on issue(s): `TRT-005`, `TRT-007`
- Priority: High
- Objective:
  - Chan xoa/force delete khong an toan tren plan/item/session/material usage.
- Scope:
  - treatment resources/pages/tables/policies
  - delete guard service
  - schema/FK review neu can migration follow-up
- Why now:
  - Neu chua dong boundary nay, user van co the pha traceability sau khi module da duoc harden o phan state/inventory.
- Suggested implementation:
  - Go bo `DeleteAction`, `ForceDeleteAction`, `Restore*` o surface khong hop le.
  - Them `canDelete()`/policy rule dua tren downstream linkage.
  - Bo edit page `TreatmentMaterial`; chuyen sang `void + recreate` neu can.
  - Xem lai FK strategy de khong de orphan data quan trong.
- Affected files or layers:
  - `app/Filament/Resources/TreatmentPlans/*`
  - `app/Filament/Resources/PlanItems/*`
  - `app/Filament/Resources/TreatmentSessions/*`
  - `app/Filament/Resources/TreatmentMaterials/*`
  - related policies/migrations
- Tests required:
  - Feature/source tests khong con destructive surfaces sai nghiep vu
  - Feature test delete bi chan khi da co downstream records
- Estimated effort: `L`
- Dependencies:
  - `FIN`, `INV`, `CLIN`
- Exit criteria:
  - Treatment core khong con bi xoa/force delete tuy tien sau khi da phat sinh side effects

# Medium priority fixes

## [TASK-TRT-006] Loai bo legacy relation managers va chot surface canonical
- Based on issue(s): `TRT-006`
- Priority: Medium
- Objective:
  - Giam drift code va chot 1 bo relation manager canonical cho treatment plan.
- Scope:
  - `TreatmentPlans/RelationManagers/*`
  - `TreatmentPlans/Relations/*`
  - docs/tests source inspection lien quan
- Why now:
  - Sau khi chot guards, can thu gon surface de tranh fix nham file legacy.
- Suggested implementation:
  - Xoa bo `Relations/*` neu khong con reference.
  - Neu can giu, doi ten/chu thich ro rang va align behavior voi bo canonical.
- Affected files or layers:
  - Filament relation manager layer
  - related tests/docs
- Tests required:
  - Regression test resource chi tham chieu bo canonical
- Estimated effort: `S`
- Dependencies:
  - `TASK-TRT-004`, `TASK-TRT-005`
- Exit criteria:
  - Khong con duplicate surface relation manager gay drift behavior

# Low priority fixes

- Chua co low-priority task rieng. Toan bo backlog TRT hien tai deu anh huong truc tiep den treatment correctness va inventory/finance traceability.

# Testing & regression protection

## [TASK-TRT-007] Khoa regression cho batch usage, state bypass, delete guard va selector scope
- Based on issue(s): `TRT-008`, `TRT-001`, `TRT-002`, `TRT-003`, `TRT-004`, `TRT-005`, `TRT-007`
- Priority: Medium
- Objective:
  - Dam bao cac fix TRT khong bi quay lai khi refactor treatment/inventory/finance boundary.
- Scope:
  - feature tests, concurrency tests, va browser tests neu UI thay doi lon
- Why now:
  - TRT la module giao diem giua CLIN/INV/FIN; regression o day co the khong lo ngay nhung se rat dat gia khi len production.
- Suggested implementation:
  - Them test cho batch-safe usage, oversell concurrency, state create bypass, branch-scoped selectors, delete guard, khong con edit surface cho treatment material.
  - Neu co thay doi UX lon o workspace, bo sung browser test cho treatment workflow chinh.
- Affected files or layers:
  - `tests/Feature/*`
  - `tests/Browser/*` neu can
- Tests required:
  - Chinh task nay la backlog regression
- Estimated effort: `M`
- Dependencies:
  - `TASK-TRT-001`
  - `TASK-TRT-002`
  - `TASK-TRT-003`
  - `TASK-TRT-004`
  - `TASK-TRT-005`
  - `TASK-TRT-006`
- Exit criteria:
  - TRT co regression suite moi bao phu cac bug quan trong nhat va pass on dinh

# Re-audit checklist

- Xac nhan moi treatment material usage deu traceable theo batch va transaction-safe.
- Xac nhan treatment plan khong con bypass state machine luc create/update/action.
- Xac nhan progress sync khong tao duplicate exam session/progress day/item.
- Xac nhan staff selectors trong TRT deu branch-scoped va forged payload bi chan.
- Xac nhan khong con destructive surfaces sai nghiep vu cho treatment core.
- Xac nhan legacy relation managers da duoc loai bo hoac align.
- Xac nhan regression suite TRT moi deu pass.
- Danh gia lai verdict va clean baseline status.

# Execution order

1. `TASK-TRT-001`
2. `TASK-TRT-002`
3. `TASK-TRT-003`
4. `TASK-TRT-004`
5. `TASK-TRT-005`
6. `TASK-TRT-006`
7. `TASK-TRT-007`

# What can be done in parallel

- `TASK-TRT-004` co the song song sau khi `TASK-TRT-002` bat dau on dinh form/service boundary.
- `TASK-TRT-006` co the lam song song cuoi `TASK-TRT-005` neu destructive surfaces da ro.
- `TASK-TRT-007` co the viet dan theo tung task, nhung chi chot sau khi code boundary da on.

# What must be done first

- `TASK-TRT-001` va `TASK-TRT-002` phai di truoc vi day la 2 blocker nang nhat cho inventory traceability va treatment state machine.
- `TASK-TRT-003` phai di truoc khi deep-fix `FIN` vi progress/session drift se anh huong invoice linkage.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-TRT-001`
  - `TASK-TRT-002`
- Milestone 2:
  - `TASK-TRT-003`
  - `TASK-TRT-004`
- Milestone 3:
  - `TASK-TRT-005`
  - `TASK-TRT-006`
  - `TASK-TRT-007`
