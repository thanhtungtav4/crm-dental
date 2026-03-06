# Metadata

- Module code: `SUP`
- Module name: `Suppliers / Factory Orders`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Review file: `docs/reviews/modules/SUP-suppliers-factory.md`
- Issue file: `docs/issues/SUP-issues.md`
- Plan file: `docs/planning/SUP-plan.md`
- Issue ID prefix: `SUP-`
- Task ID prefix: `TASK-SUP-`
- Dependencies: `INV, FIN, GOV`
- Last updated: `2026-03-06`

# Scope

- Review module `SUP` theo 4 lop: architecture, database, domain logic, UI/UX.
- Trong pham vi review:
  - `suppliers`, `factory_orders`, `factory_order_items`
  - Filament resources/forms/tables/relation managers cho supplier va lenh labo
  - report `FactoryStatistical`
  - boundary lien ket voi `INV`, `PAT`, `FIN`
- Flow chinh duoc xem xet:
  - tao/sua supplier
  - tao lenh labo cho benh nhan
  - them/sua/xoa item labo
  - chuyen trang thai draft -> ordered -> in_progress -> delivered/cancelled

# Context

- `SUP` la boundary procurement va outsource labo, lien quan truc tiep den chi phi, theo doi nha cung cap, tien do labo, va lien ket voi patient/treatment.
- Module nay phai giu duoc 3 chuan:
  - authorization theo branch/role
  - workflow lenh labo khong tao state sai
  - canonical supplier master phai map duoc sang order/report/finance
- Thong tin con thieu lam giam do chinh xac review:
  - chua co SOP phe duyet dat labo va tiep nhan hang labo
  - chua co quy tac ro rang supplier la global master hay branch-owned master
  - chua co workflow doi chieu cong no supplier voi finance

# Executive Summary

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`, chua dat baseline cho procurement/factory workflow.
- Cac canh bao nghiem trong:
  - `FactoryOrderResource` dang thieu authorization boundary; runtime check cho thay `doctor` cung `canViewAny()` va `canCreate()`.
  - `FactoryOrder` cho phep patient/branch mismatch va doctor selector khong branch-scoped.
  - supplier master hien bi tach roi khoi factory order do order chi luu `vendor_name` free text.
  - `generateOrderNo()` dung mau query-last-then-increment, co race-condition voi unique `order_no`.

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - `app/Filament/Resources/FactoryOrders/FactoryOrderResource.php`
  - `app/Models/FactoryOrder.php`
  - runtime tinker: `FactoryOrderResource::canViewAny()` va `canCreate()` tra `true` cho `doctor`
  - `app/Policies/SupplierPolicy.php`
  - runtime tinker: `SupplierResource::canDeleteAny()` tra `true` cho `doctor`
- Findings:
  - `FactoryOrderResource` khong co policy rieng, chi co query scope theo branch. Query scope khong thay the duoc authorization.
  - Runtime check cho thay `doctor` hien co the xem va tao lenh labo. Neu day khong phai role duoc phep procurement, day la lo hong quyen rat ro.
  - `SupplierResource` dang co delete/force-delete/restore surfaces; runtime permission surface cua `deleteAny` dang khong an toan.
- Suggested direction:
  - tao `FactoryOrderPolicy` va `FactoryOrderItemPolicy` ro rang theo role + branch.
  - khoa delete/force-delete/restore supplier o policy + UI + model layer tru khi co workflow archive ro rang.

## Data Integrity & Database

- Danh gia: `Kem`
- Evidence:
  - schema `suppliers`, `factory_orders`, `factory_order_items`
  - `app/Models/FactoryOrder.php`
  - `app/Models/FactoryOrderItem.php`
  - `app/Filament/Resources/FactoryOrders/Schemas/FactoryOrderForm.php`
- Findings:
  - `factory_orders` khong co `supplier_id`; order chi luu `vendor_name` text, lam supplier master tro thanh danh muc tach roi.
  - `FactoryOrder` khong enforce `patient.first_branch_id == factory_orders.branch_id`; chi assert actor duoc thao tac branch do.
  - `doctor_id` selector khong scope theo branch cua order.
  - `factory_order_items` cho phep sua/xoa bat ke order da delivered/cancelled hay chua.
- Suggested direction:
  - them `supplier_id` vao `factory_orders`, backfill tu `vendor_name` neu du lieu cho phep.
  - enforce patient/branch/doctor consistency o service/model layer.
  - khoa item mutation khi order khong con o phase editable.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - `app/Models/FactoryOrder.php::generateOrderNo()`
- Findings:
  - `generateOrderNo()` doc `lastOrderNo` theo ngay roi tang sequence trong PHP. Hai request dong thoi co the sinh cung `LAB-YYYYMMDD-XXXX` va va nhau vao unique index.
  - Cac action chuyen status tren table goi `save()` truc tiep, khong co service boundary hay optimistic/pessimistic lock neu 2 nguoi thao tac cung lenh labo.
- Suggested direction:
  - dua create/status transition ve service transaction-safe co retry khi unique key conflict.
  - can nhac dung counter table/sequence strategy hoac lock row khi sinh `order_no`.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Filament/Resources/FactoryOrders/FactoryOrderResource.php`
  - `app/Filament/Resources/FactoryOrders/Tables/FactoryOrdersTable.php`
  - `app/Filament/Pages/Reports/FactoryStatistical.php`
- Findings:
  - `FactoryOrderResource` khong eager load `patient`, `branch`, `doctor`, `items_count`; khi danh sach lon se de phat sinh N+1 va count query lap lai.
  - report `FactoryStatistical` query `TreatmentSession`, khong query `FactoryOrder`, nen KPI/report la sai nghiep vu hon la cham.
- Suggested direction:
  - eager load hot-path relations trong resource query.
  - sua report ve dung datasource `FactoryOrder`/`FactoryOrderItem`.

## Maintainability

- Danh gia: `Kem`
- Evidence:
  - `app/Models/FactoryOrder.php`
  - `app/Filament/Resources/FactoryOrders/Tables/FactoryOrdersTable.php`
  - `app/Filament/Resources/FactoryOrders/RelationManagers/ItemsRelationManager.php`
- Findings:
  - workflow hien dang bi tach giua model event, table actions va relation manager raw CRUD; khong co canonical service cho create/order/deliver/cancel.
  - supplier master va factory order khong dung chung 1 identity boundary.
- Suggested direction:
  - tao `FactoryOrderWorkflowService` va `SupplierCatalogGuard`.
  - UI chi goi workflow/service, khong mutate status/item raw.

## Better Architecture Proposal

- Tach `SUP` thanh 3 boundary:
  - `SupplierCatalogBoundary`: master data supplier, archive policy, active/inactive, contact/payment terms
  - `FactoryOrderWorkflowService`: create, order, progress, deliver, cancel, branch validation, audit
  - `FactoryOrderReportingBoundary`: dashboard/thong ke query dung nguon `factory_orders`
- Muc tieu kien truc:
  - moi transition lenh labo di qua service transaction-safe
  - supplier master la canonical source cho procurement/factory flow
  - order/item mutation bi khoa sau khi qua editable phases

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Kem`
- Workflow hien tai:
  - tao supplier
  - tao lenh labo cho benh nhan
  - them item labo trong relation manager
  - table actions chuyen trang thai order
- Nhan xet:
  - workflow co the chay, nhung domain boundary chua chat: khong co supplier canonical link, khong co validation patient/branch/doctor consistency, khong co service workflow.

## State transitions

- Danh gia: `Trung binh`
- Hien co:
  - `FactoryOrder`: `draft -> ordered -> in_progress -> delivered/cancelled`
  - `FactoryOrderItem.status`: `ordered/in_progress/delivered/cancelled`
- Van de:
  - order va item la hai state machine tach roi nhung khong co rule dong bo.
  - relation manager cho sua item status bat ke order status.
  - table actions chuyen status truc tiep bang `save()`, khong audit ly do ngoai case cancel.

## Missing business rules

- chua co rule `patient branch phai trung voi branch cua lenh labo`.
- chua co rule `doctor phai thuoc branch cua lenh labo`.
- chua co rule `supplier tren lenh labo phai map ve supplier master`.
- chua co rule `khong cho sua/xoa item khi lenh labo da ordered/in_progress/delivered/cancelled`.
- chua co rule `delivered_at`/`ordered_at` bat buoc nhat quan voi transition service.

## Invalid states / forbidden transitions

- lenh labo co the thuoc branch B trong khi patient goc o branch A.
- doctor branch A co the duoc gan cho lenh labo branch B neu role la doctor.
- order delivered van co item status ordered hoac item bi xoa sau khi da deliver.
- supplier bi xoa/force delete sau khi da lien ket voi materials/batches, lam mat provenance procurement.

## Service / action / state machine / transaction boundary de xuat

- Tao `FactoryOrderWorkflowService`:
  - `createDraft()`
  - `markOrdered()`
  - `markInProgress()`
  - `markDelivered()`
  - `cancel()`
  - moi action deu validate branch/patient/doctor/supplier va ghi audit
- Tao `FactoryOrderAuthorizer` hoac policy branch-aware:
  - scope records
  - scope selectors
  - sanitize payload server-side
- Dua item CRUD ve guard theo `FactoryOrder::isEditable()`.

# QA/UX Findings

## User flow

- Danh gia: `Kem`
- Flow hien tai de dung nham:
  - nguoi dung tao lenh labo voi branch tuy chon, patient tuy chon, doctor tuy chon, nhung he thong khong khoa chat consistency.
  - supplier master co giao dien rieng nhung order lai nhap `vendor_name` text, gay nham lap supplier moi va mat lien ket.
  - item labo co the bi sua/xoa khi order da o phase van hanh.

## Filament UX

- Danh gia: `Trung binh`
- Evidence:
  - `app/Filament/Resources/Suppliers/*`
  - `app/Filament/Resources/FactoryOrders/*`
- Findings:
  - `FactoryOrderForm` dung `vendor_name` text thay vi supplier select.
  - relation manager item khong hien rule khoa form theo order status.
  - `EditSupplier` va table suppliers mo delete/force-delete/restore surfaces qua rong.
  - report `FactoryStatistical` dat ten factory/labo nhung data hien thi lai la treatment session.

## Edge cases quan trong

- hai nguoi tao lenh labo cung luc gay duplicate `order_no`.
- patient o branch A nhung order luu branch B.
- doctor khong thuoc branch order van duoc gan vao lenh labo.
- order da delivered nhung van cho sua/xoa item.
- supplier bi xoa sau khi materials/batches da tung tham chieu toi supplier do.
- manager/doctor thao tac order ngoai pham vi nghiep vu neu policy boundary khong khoa.
- report labo dung sai datasource lam quan ly ra quyet dinh sai.

## Diem de thao tac sai

- text input `vendor_name` de nguoi dung go tu do, de tao duplicate supplier naming.
- table actions chuyen status rat nhanh nhung thieu helper text/validation theo nghiep vu.
- relation manager item khong canh bao order da khong con editable.

## De xuat cai thien UX

- doi `vendor_name` thanh supplier selector canonical; chi cho override text co kiem soat neu supplier ngoai he thong.
- disable item create/edit/delete khi order da ordered tro len, hoac toi thieu khoa sau delivered/cancelled.
- them badge `branch scope` va helper text cho doctor selector.
- report labo phai query dung lenh labo va item, co filter `status`, `supplier`, `branch`, `due_at`.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| SUP-001 | Critical | Security | FactoryOrderResource thieu policy va mo quyen qua rong | Resolved | policy + branch-scoped record binding da khoa create/update/delete surface cua doctor tren resource |
| SUP-002 | High | Domain Logic | Factory order khong enforce consistency giua patient, branch va doctor | Resolved | payload server-side da duoc sanitize; patient branch va doctor scope deu bi khoa o form/page/model |
| SUP-003 | High | Data Integrity | Supplier master khong la canonical identity cho factory orders | Resolved | `factory_orders` da co `supplier_id`, migration backfill canonical supplier, va form/resource da doi sang selector chuan |
| SUP-004 | High | Concurrency | Sinh `order_no` dang race-prone voi unique index | Resolved | da doi sang sequence-per-day co `lockForUpdate()` va create boundary transaction-safe |
| SUP-005 | High | Domain Logic / UX | Order va item mutation surfaces mo sau khi qua phase editable | Resolved | status transition da di qua workflow service; edit/item CRUD bi khoa sau draft va model layer hard-deny bypass |
| SUP-006 | High | Data Integrity / Security | Supplier destructive surfaces mo va co the cat dut procurement provenance | Resolved | delete/force-delete/restore surfaces da bi go; policy + model layer deu hard-deny |
| SUP-007 | Medium | Maintainability / Reporting | Bao cao labo dang query sai datasource | Resolved | `FactoryStatistical` da query `FactoryOrder`, co stats branch-aware va test datasource/report value |
| SUP-008 | Medium | Maintainability | Test coverage cua SUP qua mong | Resolved | module da co suite rieng cho auth, branch consistency, supplier canonical, numbering, workflow va report |

# Dependencies

- `INV`: supplier dang lien ket voi material/material batch; destructive surface cua supplier co the cat dut provenance inventory.
- `FIN`: payment terms supplier va factory outsourcing cost se can map ve finance neu procurement duoc mo rong.
- `GOV`: policy, RBAC va branch access la nen de khoa resource quyen cao va scope records.

# Open Questions

- supplier nen la global master data hay branch-owned master data?
- factory order co can giai doan `received/verified` tach rieng khoi `delivered` khong?
- co can lien ket factory order item voi service catalog + treatment plan item bat buoc khong?
- co quy tac nao cho phep doctor tu tao lenh labo, hay chi manager/le tan/lab staff?

# Recommended Next Steps

- Khong con open issue baseline cho `SUP`.
- Rollout tiep theo: chay `php artisan migrate` tren moi truong that de ap dung `supplier_id` backfill va `factory_order_sequences`, sau do smoke test report labo.
- Chi chuyen sang report/workflow re-audit sau khi branch invariants va create boundary da on dinh.

# Current Status

- In Fix
