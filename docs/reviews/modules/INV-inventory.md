# Metadata

- Module code: `INV`
- Module name: `Inventory / Batches / Stock`
- Current status: `In Fix`
- Current verdict: `D`
- Review file: `docs/reviews/modules/INV-inventory.md`
- Issue file: `docs/issues/INV-issues.md`
- Plan file: `docs/planning/INV-plan.md`
- Issue ID prefix: `INV-`
- Task ID prefix: `TASK-INV-`
- Dependencies: `GOV, TRT, FIN, SUP, KPI`
- Last updated: `2026-03-06`

# Scope

- Review module `INV` theo 4 lop: architecture, database, domain logic, UI/UX.
- Trong pham vi review:
  - `materials`, `material_batches`, `inventory_transactions`, `material_issue_notes`, `material_issue_items`
  - Filament resources, forms, tables, relation managers cho vat tu, lo vat tu va phieu xuat vat tu
  - mutation paths dang tac dong den ton tong, ton theo lo va ledger inventory
- Flow chinh duoc xem xet:
  - tao vat tu / tao lo
  - cap nhat ton kho va gia von
  - xuat vat tu theo phieu
  - ghi nhan su dung vat tu o dieu tri de doi chieu voi inventory

# Context

- `INV` la boundary giu vai tro source of truth cho batch traceability, ton kho thuc te, canh bao het hang, het han va lien ket voi `TRT` / `FIN` / `KPI`.
- Day la module co rui ro cao trong CRM phong nha khoa vi vat tu co lien quan truc tiep den dieu tri, truy vet lo hang, thu hoi vat tu va gia von dich vu.
- Thong tin con thieu lam giam do chinh xac review:
  - chua co SOP chinh thuc cho inventory adjustment / stock count / batch recall
  - chua co rule van hanh ve viec co cho phep chinh tay `stock_qty` / `batch quantity` hay khong
  - chua co quy tac FIFO/FEFO chinh thuc cho xuat vat tu thu cong
- Ghi nhan van hanh quan trong:
  - codebase da co migration `2026_03_06_162936_add_material_batch_context_to_inventory_transactions_table.php`, nhung DB app hien tai van chua co cot `inventory_transactions.material_batch_id`; day la rollout drift can duoc xu ly khi trien khai fix.

# Executive Summary

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`, chua dat baseline cho inventory do ton tong va ton theo lo co the drift ngay trong flow chuan.
- Cac canh bao nghiem trong:
  - inventory mutation boundary van chua duoc centralize giua issue-note flow, treatment usage va cac future adjust/receive flows.
  - rollout drift van ton tai: app DB hien tai chua co cot `inventory_transactions.material_batch_id` du migration da ton tai trong repo.
  - module dang trong giai doan `In Fix`; `INV-001` den `INV-004` da duoc khoa bang code + test, nhung chua re-audit module.

# Architecture Findings

## Security

- Danh gia: `Trung binh`
- Evidence:
  - `app/Policies/MaterialPolicy.php`
  - `app/Policies/MaterialBatchPolicy.php`
  - `app/Filament/Resources/Materials/Schemas/MaterialForm.php`
  - `app/Filament/Resources/MaterialBatches/Schemas/MaterialBatchForm.php`
  - `app/Filament/Resources/MaterialIssueNotes/RelationManagers/ItemsRelationManager.php`
- Findings:
  - Diem tot: `MaterialResource` va `MaterialBatchResource` da scope query theo branch accessible; policy cung branch-aware.
  - Lo hong con lai nam o UI layer:
    - `MaterialForm::branch_id` va `supplier_id` khong dung `BranchAccess` de scope options.
    - `MaterialBatchForm::material_id` khong gioi han theo branch accessible.
    - `ItemsRelationManager` cho `material_id` tren phieu xuat khong scope theo branch cua phieu, khong loc theo ton kho, va khong yeu cau chon lo.
- Suggested direction:
  - dua inventory selectors ve authorizer/service chung de scope theo branch va stock state.
  - bo write surface khong can thiet va chi cho phep mutation qua flow co transaction.

## Data Integrity & Database

- Danh gia: `Kem`
- Evidence:
  - schema `materials`, `material_batches`, `material_issue_notes`, `material_issue_items`, `inventory_transactions`
  - `app/Models/Material.php`
  - `app/Models/MaterialBatch.php`
  - `app/Models/MaterialIssueNote.php`
  - `app/Models/InventoryTransaction.php`
- Findings:
  - `materials.sku` khong co unique/composite unique o DB, trong khi form lai de helper text `Ma vat tu duy nhat`.
  - `material_issue_items` khong co `material_batch_id`, nen posted issue note khong the truy vet lo da xuat.
  - `MaterialIssueNote::post()` chi tru `materials.stock_qty`, khong tru `material_batches.quantity`, khong cap nhat `InventoryTransaction.material_batch_id`.
  - `MaterialForm` cho edit truc tiep `stock_qty`; `MaterialBatchForm` cho edit truc tiep `quantity` va `status` ngoai moi ledger/service boundary.
- Suggested direction:
  - bat buoc issue item gan voi 1 lo cu the.
  - khoa mutation ton kho tong va ton theo lo khoi generic edit form.
  - bo sung DB invariant cho SKU va batch-traceable issue items.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - `app/Models/MaterialIssueNote.php`
  - `app/Models/MaterialBatch.php`
  - `app/Services/TreatmentMaterialUsageService.php`
- Findings:
  - `MaterialIssueNote::post()` co transaction + `lockForUpdate()` tren note/items/materials, nhung khong lock `material_batches` vi issue item khong mang context lo; ket qua la transaction boundary hien tai van chua khoa duoc drift batch.
  - `MaterialBatch::decreaseQuantity()` / `increaseQuantity()` chi `save()` truc tiep, khong lock row, khong co guard branch / expiry / active status.
  - Treatment usage da batch-safe va lock-safe trong `TreatmentMaterialUsageService`, nhung issue note chua dung boundary cung muc do chat.
- Suggested direction:
  - dua moi stock mutation ve service/model methods transaction-safe tren `Material` + `MaterialBatch`.
  - dong bo phieu xuat vat tu voi chuan batch-safe da dung trong `TRT`.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Models/Material.php`
  - `app/Filament/Resources/Materials/Tables/MaterialsTable.php`
  - `app/Filament/Resources/MaterialBatches/Tables/MaterialBatchesTable.php`
  - `app/Services/TreatmentMaterialUsageService.php`
- Findings:
  - Diem tot: `material_batches` da co unique `(material_id, batch_number)` va index `(material_id, status)`; `material_issue_notes` da co unique `note_no` va index `(branch_id, status)`.
  - Diem yeu: SKU khong unique, issue note khong co batch FK nen khong the query nhanh theo lo/traceability, direct edit `stock_qty` de batch aggregation phai tinh tay neu can reconcile.
  - Materials table dang `counts('batches')`, nhung quyet dinh van hanh lai dua tren `stock_qty` editable tay, de lam KPI/bao cao kho mat tin cay.
- Suggested direction:
  - query/report phai dua tren structured batch context va immutable inventory ledger.
  - tranh cho phep value tong hop bi sua tay.

## Maintainability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Models/Material.php`
  - `app/Models/MaterialBatch.php`
  - `app/Models/MaterialIssueNote.php`
  - `app/Filament/Resources/Materials/*`
  - `app/Filament/Resources/MaterialBatches/*`
- Findings:
  - Logic stock mutation hien dang tach lam 3 duong:
    - treatment usage service batch-safe
    - issue note post aggregate-only
    - form edit cho sua tay ton tong / ton lo
  - Kien truc nay de drift va rat kho audit vi khong co canonical inventory mutation boundary.
- Suggested direction:
  - tao 1 inventory mutation boundary ro rang cho receive / issue / adjust / reverse.
  - UI chi duoc goi qua boundary do, khong de edit raw stock fields.

## Better Architecture Proposal

- Tach inventory thanh 4 boundary nghiep vu ro rang:
  - `MaterialCatalogBoundary` cho master data vat tu, SKU, supplier, branch ownership
  - `MaterialBatchLifecycleService` cho tao lo, dieu chinh lo, doi trang thai active/expired/recalled/depleted
  - `MaterialIssuePostingService` cho phieu xuat vat tu batch-safe, lock-safe, ledger-safe
  - `InventoryAdjustmentService` cho inventory count / reconcile co ly do va audit bat buoc
- Muc tieu kien truc:
  - moi mutation ton kho deu co `transaction + lockForUpdate + audit/ledger`
  - ton tong duoc dong bo tu ton theo lo hoac inventory ledger, khong sua tay tren CRUD thong thuong
  - moi su kien xuat kho deu truy vet duoc den lo cu the

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Kem`
- Workflow hien tai:
  - tao material
  - tao batch cho material
  - tao issue note draft -> them item -> post
  - treatment usage dung batch rieng thong qua `TreatmentMaterialUsageService`
- Nhan xet:
  - workflow treatment usage da di dung huong batch-safe.
  - workflow issue note lai bo qua batch boundary, nen inventory co hai chuan domain khac nhau ngay trong cung module.

## State transitions

- Danh gia: `Trung binh`
- Hien co:
  - `MaterialIssueNote`: `draft -> posted/cancelled`
  - `MaterialBatch.status`: `active`, `expired`, `recalled`, `depleted`
- Van de:
  - `MaterialIssueNote` khoa duoc `posted`, nhung posted note chua dam bao batch depletion.
  - `MaterialBatch.status` co the bi sua tay tren edit form, khong buoc di qua inventory mutation boundary.

## Missing business rules

- chua co rule `phieu xuat thu cong bat buoc chon lo vat tu`.
- chua co rule `khong duoc sua tay ton tong sau khi da co batch/ledger history`.
- chua co rule `khong duoc xoa/sua material batch neu da co treatment usage / issue transaction lien quan`.
- chua co rule `SKU phai duy nhat trong pham vi duoc chot`.

## Invalid states / forbidden transitions

- material co the co `stock_qty = 10` trong khi tong batch = 0 hoac nguoc lai.
- phieu xuat da `posted` nhung khong truy vet duoc lo nao da bi rut ra.
- batch co the bi set `recalled/expired/depleted` bang tay ma khong co inventory reason hoac audit context.
- user co the xoa/force delete material/batch da co downstream history neu duoc cap permission.

## Service / action / state machine / transaction boundary de xuat

- Tao `MaterialIssuePostingService` hoac nang cap `MaterialIssueNote::post()` thanh batch-safe boundary:
  - bat buoc `material_issue_items.material_batch_id`
  - lock note + items + materials + batches
  - check branch/material/batch/expiry/status/quantity
  - ghi `InventoryTransaction.material_batch_id`
- Tao inventory mutation helpers co lock:
  - `consume()` / `restore()` cho batch
  - `syncAggregateStock()` cho material neu tiep tuc giu `stock_qty`
- Khoa manual edit tren `stock_qty`, `batch.quantity`, `batch.status`, thay bang action co ly do va audit.

# QA/UX Findings

## User flow

- Danh gia: `Kem`
- Flow hien tai de dung nham:
  - le tan / thu kho tao phieu xuat ma khong can chon lo
  - nguoi van hanh co the sua tay `stock_qty` tren material de "can bang so"
  - batch co the bi edit truc tiep du quantity da duoc su dung o dieu tri
- Ket qua:
  - UX co ve nhanh, nhung du lieu kho rat de bi sai ma nguoi dung khong nhan ra.

## Filament UX

- Danh gia: `Trung binh`
- Evidence:
  - `app/Filament/Resources/Materials/Schemas/MaterialForm.php`
  - `app/Filament/Resources/MaterialBatches/Schemas/MaterialBatchForm.php`
  - `app/Filament/Resources/MaterialIssueNotes/RelationManagers/ItemsRelationManager.php`
- Findings:
  - `MaterialForm` mo `stock_qty` nhu mot truong CRUD thong thuong, khong co canh bao day la du lieu tong hop nhay cam.
  - `MaterialBatchForm` cho sua `quantity/status` nhu master data thuong.
  - relation manager cua issue note khong yeu cau chon lo, khong show batch availability / expiry.
  - materials / batches table van mo delete/force delete/restore bulk.

## Edge cases quan trong

- xuat vat tu cho benh nhan bang phieu thu cong trong khi batch sap het han va ton tong van con.
- cung mot material co nhieu batch, nguoi dung xuat sai batch het han nhung he thong khong bat chon batch.
- can bang so ton bang cach sua tay `stock_qty`, lam KPI ton kho va reorder sai.
- xoa material batch da tung duoc dung o treatment usage / issue note.
- tao material moi trung SKU voi material khac trong cung branch.
- nguoi dung branch A chon material/batch cua branch B neu selector khong scope chat.
- app DB chay thuc te chua apply migration batch context cho `inventory_transactions`, trong khi code da phu thuoc vao cot nay.

## Diem de thao tac sai

- `stock_qty` va `batch quantity` dat ngay tren form edit, de nguoi dung sua tay thay vi di qua action nghiep vu.
- relation manager item cua issue note chi cho chon `material`, khong cho chon `batch`.
- bulk delete/force delete o materials/batches khong canh bao hau qua ledger/downstream.

## De xuat cai thien UX

- issue note item phai co `material_batch_id`, chi hien cac batch con hang, dung branch, chua het han, dang active.
- `stock_qty` tren material nen la read-only, hien ro tong batch / so batch active / canh bao drift.
- `MaterialBatchForm` bo editable `quantity/status` thong thuong; thay bang action `Nhap kho`, `Dieu chinh`, `Thu hoi`, `Danh dau het han` co ly do.
- bo delete/force delete surface tren materials/batches co downstream history; neu can thi chi cho archive/read-only.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| INV-001 | Critical | Data Integrity | Posted issue note khong tru ton theo lo va khong truy vet batch | Resolved | Issue items da co `material_batch_id`; posted issue note tru ton tong va ton lo dong bo, ghi batch context vao ledger |
| INV-002 | High | Data Integrity | SKU uniqueness va aggregate stock invariant con yeu | Resolved | SKU da duoc khoa unique o DB; `stock_qty` da bi cat khoi CRUD form/page thong thuong |
| INV-003 | High | UX / Data Integrity | Destructive va manual mutation surfaces cua material/batch van mo | Resolved | delete/restore/force-delete surfaces da bi cat; batch quantity/status chi con editable khi tao moi |
| INV-004 | High | Security / Domain Logic | Forms va relation managers chua branch-scoped, stock-aware day du | Resolved | material selectors da branch-aware; forged payload va supplier inactive deu bi chan server-side |
| INV-005 | Medium | Concurrency | Batch mutation logic chua duoc centralize thanh transaction-safe boundary | Open | `MaterialBatch::decreaseQuantity()` / `increaseQuantity()` khong lock-safe; issue note khong dung chung boundary voi treatment |
| INV-006 | Medium | Maintainability | Regression coverage chua khoa inventory drift va rollout drift | Open | thieu test cho batch-safe issue note, SKU invariant, destructive surfaces va migration alignment |

# Dependencies

- `GOV`: branch scoping va RBAC baseline da khoa xong, la nen cho form/resource scope.
- `TRT`: da co `TreatmentMaterialUsageService` batch-safe, nen duoc dung lam chuan tham chieu cho inventory issue-note flow.
- `FIN`: gia von va doanh thu phu thuoc vao ton kho va batch cost accuracy.
- `SUP`: supplier/global procurement la dependency ben ngoai inventory core; chi nen dong vao khi can supplier scope/cross-module batch receive.
- `KPI`: bao cao ton kho, reorder, batch aging va cost usage phu thuoc truc tiep vao do tin cay cua `INV`.

# Open Questions

- Nen chot invariant SKU theo global hay theo `(branch_id, sku)`?
- `stock_qty` co nen tro thanh derived field tu batch sum/ledger, hay van giu denormalized field va them reconciliation job?
- Inventory adjustment co can quy trinh 2-buoc phe duyet hay chi can action permission + audit reason?
- Khi batch het han/thu hoi, co cho phep issue note manual xuat nua hay phai cam tuyet doi?
- Rollout migration `inventory_transactions.material_batch_id` tren DB hien huu se duoc xu ly theo SOP nao?

# Recommended Next Steps

- Chot `INV-005` de dua batch mutation ve canonical transaction-safe boundary dung chung cho inventory va treatment.
- Sau do dong `INV-006` de khoa regression suite va rollout verification cho schema drift.
- Bo sung regression suite cho batch-safe issue posting truoc khi sang `SUP` hoac `KPI`.

# Current Status

- In Fix
