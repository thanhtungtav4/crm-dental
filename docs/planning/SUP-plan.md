# Metadata

- Module code: `SUP`
- Module name: `Suppliers / Factory Orders`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-SUP-`
- Source review: `docs/reviews/modules/SUP-suppliers-factory.md`
- Source issues: `docs/issues/SUP-issues.md`
- Dependencies: `INV, FIN, GOV`
- Last updated: `2026-03-06`

# Objective

- Dua module `SUP` tu `D` len it nhat `B` baseline bang cach khoa 4 boundary lon nhat:
  - authorization + branch scope cho supplier/factory order
  - consistency giua patient/branch/doctor/supplier
  - workflow/state machine va mutation surfaces cua lenh labo
  - report/query va regression protection

# Foundation fixes

## [TASK-SUP-001] Khoa authorization boundary cho FactoryOrderResource va SupplierResource
- Based on issue(s): `SUP-001`, `SUP-006`
- Priority: Foundation
- Objective:
  - Xac dinh ro ai duoc view/create/update/delete supplier va lenh labo; loai bo fallback authorization nguy hiem.
- Scope:
  - policies
  - resources/pages
  - route binding scope
- Why now:
  - Neu quyen sai thi cac fix domain sau do khong co y nghia.
- Suggested implementation:
  - tao `FactoryOrderPolicy`
  - harden `SupplierPolicy` cho destructive surfaces
  - them resource query/record scope theo branch neu can
- Affected files or layers:
  - `app/Policies/*`
  - `app/Filament/Resources/Suppliers/*`
  - `app/Filament/Resources/FactoryOrders/*`
- Tests required:
  - auth matrix tests cho admin/manager/doctor
- Estimated effort: `M`
- Dependencies:
  - `GOV`
- Exit criteria:
  - role khong duoc phep bi chan o list/create/edit/delete surfaces cua SUP

# Critical fixes

## [TASK-SUP-002] Enforce patient-branch-doctor consistency cho factory orders
- Based on issue(s): `SUP-002`
- Priority: Critical
- Objective:
  - Khong con tao/sua duoc lenh labo sai branch hoac sai doctor scope.
- Scope:
  - model/service/form/page guards
- Why now:
  - Day la invariant nghiep vu cot loi cua order.
- Suggested implementation:
  - tao service/authorizer sanitize payload server-side
  - doctor selector branch-aware
  - validate `patient.first_branch_id == branch_id`
- Affected files or layers:
  - `FactoryOrder`
  - `FactoryOrderForm`
  - create/edit pages
- Tests required:
  - cross-branch patient/order rejection
  - outside-branch doctor rejection
- Estimated effort: `M`
- Dependencies:
  - `TASK-SUP-001`
- Exit criteria:
  - branch mismatch va doctor mismatch deu bi chan o UI va server-side

# High priority fixes

## [TASK-SUP-003] Bien supplier thanh canonical identity cua factory orders
- Based on issue(s): `SUP-003`
- Priority: High
- Objective:
  - Supplier master duoc map truc tiep vao procurement/factory workflow.
- Scope:
  - migrations
  - model/form/table/report
- Why now:
  - Khong co `supplier_id` thi procurement provenance va report khong the on dinh.
- Suggested implementation:
  - them `supplier_id` vao `factory_orders`
  - backfill/normalize `vendor_name`
  - doi form sang supplier selector canonical
- Affected files or layers:
  - `factory_orders` migration/model/form/table
  - supplier module
- Tests required:
  - supplier link tests
  - migration/backfill tests neu co
- Estimated effort: `L`
- Dependencies:
  - `TASK-SUP-001`
  - `INV`
- Exit criteria:
  - order moi deu co canonical supplier identity hoac explicit external-vendor path duoc kiem soat

## [TASK-SUP-004] Hardening order number generation va create boundary
- Based on issue(s): `SUP-004`
- Priority: High
- Objective:
  - Khong con collision `order_no` khi tao dong thoi.
- Scope:
  - create workflow
  - model/service sequence generation
- Why now:
  - Production multi-user rat de dap vao unique race nay.
- Suggested implementation:
  - dua create lenh labo ve transaction-safe service co retry
  - dung sequence/lock strategy theo ngay
- Affected files or layers:
  - `FactoryOrder`
  - service moi neu can
  - create page
- Tests required:
  - concurrency/idempotency create tests
- Estimated effort: `M`
- Dependencies:
  - `TASK-SUP-001`
- Exit criteria:
  - create dong thoi van giu duoc unique `order_no`

## [TASK-SUP-005] Khoa mutation surfaces va chot workflow service cho order/item
- Based on issue(s): `SUP-005`
- Priority: High
- Objective:
  - Moi state transition va item mutation deu qua workflow boundary dung nghiep vu.
- Scope:
  - table actions
  - relation manager items
  - model/service layer
- Why now:
  - Neu van de raw CRUD con mo thi order van co the roi vao invalid states du auth da khoa.
- Suggested implementation:
  - tao `FactoryOrderWorkflowService`
  - them `isEditable()` / `canMutateItems()`
  - bo edit/delete item sau khi order qua editable phases
- Affected files or layers:
  - `FactoryOrdersTable`
  - `ItemsRelationManager`
  - `FactoryOrder`, `FactoryOrderItem`
- Tests required:
  - invalid mutation tests
  - workflow transition tests
- Estimated effort: `L`
- Dependencies:
  - `TASK-SUP-001`
  - `TASK-SUP-002`
- Exit criteria:
  - order va item khong con mutate sai phase, status transitions deu di qua workflow service

## [TASK-SUP-006] Hardening destructive surfaces cua Supplier
- Based on issue(s): `SUP-006`
- Priority: High
- Objective:
  - Supplier khong con bi xoa/khoi phuc/force-delete tuy tien sau khi da co inventory history.
- Scope:
  - supplier model/policy/resource
- Why now:
  - Supplier provenance anh huong truc tiep den `INV`.
- Suggested implementation:
  - remove destructive UI surfaces
  - block delete/restore/force-delete o policy + model layer
  - su dung `active`/archive thay cho xoa neu can
- Affected files or layers:
  - supplier resource/policy/model
- Tests required:
  - destructive guard tests
- Estimated effort: `M`
- Dependencies:
  - `TASK-SUP-001`
  - `INV`
- Exit criteria:
  - supplier chi con inactive/archive path, khong co delete destructive path trong baseline

# Medium priority fixes

## [TASK-SUP-007] Sua report labo ve dung datasource va filter nghiep vu
- Based on issue(s): `SUP-007`
- Priority: Medium
- Objective:
  - Bao cao labo phan anh dung factory workflow thay vi treatment sessions.
- Scope:
  - report page/query/export
- Why now:
  - Bao cao sai nghiep vu se lam sai quyet dinh van hanh, nhung nen di sau khi boundary chinh da khoa.
- Suggested implementation:
  - doi query sang `FactoryOrder`/`FactoryOrderItem`
  - them filter branch/status/supplier/due_at
- Affected files or layers:
  - `FactoryStatistical`
- Tests required:
  - report datasource tests
- Estimated effort: `M`
- Dependencies:
  - `TASK-SUP-003`
- Exit criteria:
  - report va export dung datasource factory order

## [TASK-SUP-008] Bo sung regression suite cho SUP
- Based on issue(s): `SUP-008`
- Priority: Medium
- Objective:
  - Chan regression auth, branch mismatch, workflow, report va destructive surfaces.
- Scope:
  - `tests/Feature/*`
- Why now:
  - SUP dang co gap coverage rat ro; khong khoa test thi fix sau de quay lai loi cu.
- Suggested implementation:
  - viet feature tests rieng cho auth matrix, branch guards, workflow transition, supplier destructive guard, report correctness, order number race
- Affected files or layers:
  - `tests/Feature/*`
- Tests required:
  - chinh task nay la backlog test
- Estimated effort: `M`
- Dependencies:
  - `TASK-SUP-001`
  - `TASK-SUP-002`
  - `TASK-SUP-004`
  - `TASK-SUP-005`
  - `TASK-SUP-006`
  - `TASK-SUP-007`
- Exit criteria:
  - SUP co regression suite rieng du de re-audit

# Low priority fixes

- Chua co low-priority task rieng. Backlog SUP hien tai toan bo deu lien quan truc tiep den quyen, branch consistency va traceability.

# Testing & regression protection

- Bat buoc co tests cho:
  - auth matrix supplier/factory order
  - patient/branch/doctor consistency
  - order number uniqueness/concurrency
  - order/item mutation after final states
  - supplier destructive guards
  - report datasource correctness
- Sau moi batch code change, chay it nhat test file lien quan va `vendor/bin/pint --dirty`.
- Truoc khi ket luan baseline, chay full suite de kiem tra drift voi `INV`, `FIN`, `PAT`.

# Re-audit checklist

- Xac nhan `FactoryOrderResource` da co policy va role matrix ro rang.
- Xac nhan order khong con luu branch/doctor sai voi patient scope.
- Xac nhan supplier duoc link canonical vao order.
- Xac nhan order/item khong con mutate sai phase.
- Xac nhan report labo query dung datasource.
- Xac nhan regression suite SUP pass va full suite khong gay drift lien module.

# Execution order

1. `TASK-SUP-001`
2. `TASK-SUP-002`
3. `TASK-SUP-003`
4. `TASK-SUP-004`
5. `TASK-SUP-005`
6. `TASK-SUP-006`
7. `TASK-SUP-007`
8. `TASK-SUP-008`

# What can be done in parallel

- `TASK-SUP-004` va `TASK-SUP-006` co the lam song song mot phan sau khi `TASK-SUP-001` khoa auth boundary.
- `TASK-SUP-008` co the viet dan theo tung batch fix, nhung chi chot sau khi workflow boundary on dinh.

# What must be done first

- `TASK-SUP-001` phai lam truoc de dong quyen resource.
- `TASK-SUP-002` nen di ngay sau do de khoa invariant branch.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-SUP-001`
  - `TASK-SUP-002`
- Milestone 2:
  - `TASK-SUP-003`
  - `TASK-SUP-004`
- Milestone 3:
  - `TASK-SUP-005`
  - `TASK-SUP-006`
- Milestone 4:
  - `TASK-SUP-007`
  - `TASK-SUP-008`
