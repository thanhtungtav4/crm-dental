# Metadata

- Module code: `TRT`
- Module name: `Treatment Plans / Sessions / Materials usage`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Review file: `docs/reviews/modules/TRT-treatment.md`
- Issue file: `docs/issues/TRT-issues.md`
- Plan file: `docs/planning/TRT-plan.md`
- Issue ID prefix: `TRT-`
- Task ID prefix: `TASK-TRT-`
- Dependencies: `PAT, APPT, CLIN, INV, FIN`
- Last updated: `2026-03-06`

# Scope

- Review module `TRT` theo 4 lop: architecture, database, domain logic, UI/UX.
- Trong pham vi review:
  - `treatment_plans`, `plan_items`, `treatment_sessions`, `treatment_progress_days`, `treatment_progress_items`, `treatment_materials`
  - Filament resources/page/form/relation manager cho treatment plan/session/material usage
  - observer/service sync sang exam session, EMR, care ticket, inventory transaction
  - surface xuat vat tu lien quan truc tiep den treatment (`material_issue_notes`, `material_issue_items`) o muc giao diem voi `INV`
- Flow chinh duoc xem xet:
  - tao ke hoach dieu tri
  - de xuat/benh nhan phe duyet hang muc
  - tao/phat sinh buoi dieu tri
  - dong bo treatment progress va exam session
  - ghi nhan vat tu su dung / xuat vat tu cho dieu tri

# Context

- He thong la CRM phong nha khoa Laravel 12 + Filament 4, da co baseline sach cho `GOV`, `PAT`, `APPT`, `CLIN`.
- `TRT` la boundary nghiep vu noi giua consent, dieu tri, vat tu, EMR progress va doanh thu.
- Day la module nhay cam vi bat ky drift nao o treatment state, material usage hoac delete surface deu lan sang `INV`, `FIN`, `CLIN`.
- Thong tin con thieu lam giam do chinh xac review:
  - chua co SOP chinh thuc cho quy trinh duyet ke hoach dieu tri theo vai tro (bac si/quan ly/chu phong kham)
  - chua co quy tac van hanh ro rang cho FIFO/FEFO batch consumption khi ghi nhan vat tu trong treatment session
  - chua co policy retention/immutable guideline cho treatment session sau khi da xuat hoa don

# Executive Summary

🚨 CANH BAO NGHIEM TRONG

- `TreatmentMaterial` dang tru ton kho tren `materials.stock_qty` bang model event, khong khoa transaction, khong tru `material_batches.quantity`, va `batch_id` gan nhu khong duoc su dung trong UI hot path. Day la lo hong nghiem trong cho traceability theo lo/han dung va inventory correctness.
- `TreatmentPlan` co the duoc tao truc tiep o trang thai `approved`/`in_progress` tu form, trong khi model chi chan transition draft -> in_progress/completed khi update record da ton tai. Approval boundary cua ke hoach dieu tri vi vay bi bypass.
- `TreatmentProgressSyncService` dang tu `find/create` `ExamSession` va `TreatmentProgressDay` ngoai transaction, dong thoi bo qua `ExamSessionProvisioningService` transactional da duoc harden o `CLIN`.

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`, chua dat baseline an toan cho treatment progression va material usage
- Diem tot dang ke:
  - `PlanItem` da co approval workflow, transition guard va consent gate o domain layer
  - query scope/policy theo branch cho `TreatmentPlan`, `PlanItem`, `TreatmentSession`, `TreatmentMaterial` da co nen tang tot
  - `MaterialIssueNote` da co status machine `draft -> posted/cancelled` va `lockForUpdate()` khi xuat kho

# Architecture Findings

## Security

- Danh gia: `Trung binh`
- Evidence:
  - `app/Policies/TreatmentPlanPolicy.php`
  - `app/Policies/PlanItemPolicy.php`
  - `app/Policies/TreatmentSessionPolicy.php`
  - `app/Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanForm.php:61-67`
  - `app/Filament/Resources/TreatmentSessions/Schemas/TreatmentSessionForm.php:72-83`
  - `app/Filament/Resources/TreatmentMaterials/Schemas/TreatmentMaterialForm.php:54-59`
- Findings:
  - Diem tot:
    - resource query va policy cua plan/item/session/material deu da branch-aware.
    - `PlanItem` approval change da gate bang `ActionPermission::PLAN_APPROVAL` o model layer.
  - Diem yeu:
    - `doctor_id`, `assistant_id`, `used_by` trong form chua scope theo branch, chi dua vao relationship chung.
    - Treatment plan status actions (`approve`, `start`, `complete`) dang update truc tiep tu table/page, khong the hien ro boundary permission nghiep vu rieng cho approval.
- Suggested direction:
  - Scope staff selectors theo `BranchAccess`/doctor assignment.
  - Dua plan transition ve service/action ro rang, co gate approval tren domain layer thay vi chi dua vao UI.

## Data Integrity & Database

- Danh gia: `Kem`
- Evidence:
  - Schema `treatment_materials`, `plan_items`, `treatment_sessions`, `material_batches`, `inventory_transactions`
  - `app/Models/TreatmentMaterial.php:14-18`
  - `app/Models/TreatmentMaterial.php:88-128`
  - `app/Models/MaterialBatch.php:51-117`
- Findings:
  - `treatment_materials.batch_id` ton tai nhung hot path ghi nhan vat tu khong bat nguoi dung chon batch, va created/deleted hooks khong dong vao `material_batches.quantity`.
  - `TreatmentMaterial` ghi ledger vao `inventory_transactions` nhung chi luu theo `material_id`, khong co batch-level trace.
  - Mot so FK hien dang qua permissive cho destructive flow:
    - `plan_items.treatment_plan_id` nullable va `on delete set null`
    - `treatment_materials.treatment_session_id/material_id/batch_id` deu nullable `set null`
  - Dieu nay mo ra nguy co orphan usage/history neu force delete xay ra.
- Suggested direction:
  - Chuyen consumption boundary sang batch-aware service transactional.
  - Xem lai FK/delete strategy de khong de orphan record cho treatment core.
  - Tach aggregate stock (`materials.stock_qty`) khoi source of truth batch consumption, hoac dam bao luon cap nhat ca 2 trong cung transaction.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - `app/Models/TreatmentMaterial.php:30-79`
  - `app/Models/TreatmentMaterial.php:88-128`
  - `app/Services/TreatmentProgressSyncService.php:16-98`
  - `app/Services/ExamSessionProvisioningService.php:12-68`
- Findings:
  - `TreatmentMaterial` validate ton kho o `creating`, sau do moi `decrement()` trong hook `created`; khong co transaction/lock bao quanh, nen 2 request dong thoi co the cung qua check va tru qua so luong.
  - `TreatmentProgressSyncService` dung `first()`/`create()` cho `TreatmentProgressDay` va `ExamSession` ngoai transaction, de va cham duplicate hoac unique collision duoi load dong thoi.
  - Service nay bo qua `ExamSessionProvisioningService` transactional/idempotent da co san.
- Suggested direction:
  - Tao `TreatmentMaterialUsageService` dung transaction + `lockForUpdate()` tren material/batch/session.
  - Refactor `TreatmentProgressSyncService` reuse `ExamSessionProvisioningService` va khoa day/item boundary.
  - Neu van giu observer side-effects, can chot after-commit behavior ro rang.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php`
  - `app/Filament/Resources/TreatmentSessions/Tables/TreatmentSessionsTable.php`
  - `app/Filament/Resources/TreatmentMaterials/Schemas/TreatmentMaterialForm.php:89-120`
- Findings:
  - Diem tot:
    - plan/session tables da eager load patient/doctor/assistant, giam N+1 co ban.
    - progress tables co unique/index tot cho patient/date/session dimension.
  - Diem yeu:
    - `materialOptionsForSession()` load toi 300 vat tu theo branch moi lan mo form, khong co cache/search strategy sau hon.
    - plan updateProgress / relation manager action goi `save()`/`updateProgress()` lap lai tren tung record trong bulk flow, de tang query chatter.
- Suggested direction:
  - Sau khi chot correctness, toi uu material selector bang search async va batch filter.
  - Gom state update vao service/bulk boundary thay vi loop tung record + `save()` lien tiep.

## Maintainability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Filament/Resources/TreatmentPlans/RelationManagers/PlanItemsRelationManager.php`
  - `app/Filament/Resources/TreatmentPlans/Relations/PlanItemsRelationManager.php`
  - `app/Filament/Resources/TreatmentPlans/Relations/SessionsRelationManager.php`
  - `tests/Feature/PatientTreatmentPlanListEditTest.php`
- Findings:
  - Diem tot:
    - module da co nhieu feature tests cho approval flow, branch isolation, treatment progress UI.
  - Diem yeu:
    - ton tai hai bo relation manager cho treatment plan (`RelationManagers` va `Relations`) voi quality rat khac nhau; bo `Relations/*` trong tinh trang legacy/dead code, de gay drift.
    - co edit page cho `TreatmentMaterial` du model cam update; UX va code surface lech nhau.
    - nhieu destructive actions dang duoc xem la behavior mong doi trong test source-inspection, nen regression de tiep tuc nuoi risk.
- Suggested direction:
  - Loai bo/merge legacy relation managers.
  - Bien treatment material thanh create/delete-only read model ro rang.
  - Chuyen mindset test tu `surface co DeleteAction` sang `surface bi khoa neu nghiep vu khong cho phep`.

## Better Architecture Proposal

- Tach `TRT` thanh 4 boundary nghiep vu ro rang:
  - `TreatmentPlanWorkflowService` cho approve/start/complete/cancel plan va plan item
  - `TreatmentProgressOrchestrator` cho sync session -> progress -> exam session, reuse provisioning services da harden
  - `TreatmentMaterialUsageService` cho consume/revert material theo batch, transaction-safe, idempotent, audit-ready
  - `TreatmentDestructiveGuard` cho chan delete/force delete neu da co session, progress, inventory tx, invoice hoac audit log
- Muc tieu kien truc:
  - 1 state machine ro rang cho plan/item/session
  - 1 transaction boundary ro rang cho material usage va progress sync
  - 1 source of truth cho inventory consumption theo batch

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Trung binh`
- Workflow hien tai:
  - tao ke hoach dieu tri
  - them plan item
  - gui de xuat / benh nhan dong y / tu choi
  - tao treatment session
  - dong bo treatment progress va exam session
  - ghi nhan vat tu su dung hoac xuat vat tu
- Nhan xet:
  - `PlanItem` la phan co domain guard tot nhat trong module hien tai.
  - plan/session/material usage van chua duoc rang buoc boi 1 workflow service thong nhat, nen state va inventory co the troi qua UI action/model event.

## State transitions

- Danh gia: `Kem`
- Hien co:
  - `TreatmentPlan.status`: `draft`, `approved`, `in_progress`, `completed`, `cancelled`
  - `PlanItem.approval_status`: `draft`, `proposed`, `approved`, `declined`
  - `PlanItem.status`: `pending`, `in_progress`, `completed`, `cancelled`
  - `TreatmentSession.status`: `scheduled`, `done`, `follow_up`
  - `MaterialIssueNote.status`: `draft`, `posted`, `cancelled`
- Van de:
  - `TreatmentPlan` chi chan mot phan transition o update, nhung create moi co the vao thang `approved`/`in_progress` tu form.
  - Chua thay state machine ro rang cho `TreatmentSession`; delete van mo rong du da dong bo sang progress/care/inventory.
  - `TreatmentPlansTable` approve/start/complete dang mutate record truc tiep, khong set day du `approved_at` hoac qua service transition.

## Missing business rules

- Chua co rule batch consumption khi ghi nhan `TreatmentMaterial`:
  - batch nao duoc tru
  - FIFO/FEFO hay explicit lot selection
  - xu ly batch het han/thu hoi
- Chua co rule khong cho delete/force delete plan-item-session sau khi da phat sinh progress, inventory transaction, invoice hoac care follow-up.
- Chua co rule branch-scoped staff assignment tren treatment plan/session/material usage.
- Chua co rule ro rang ai duoc approve ke hoach dieu tri cap plan.

## Invalid states / forbidden transitions

- Tao moi treatment plan o trang thai `in_progress` tu form khi chua qua approval.
- Ghi nhan treatment material khong co batch nhung van tru stock aggregate, lam mat traceability theo lo.
- Xoa `TreatmentSession` sau khi da sync sang `TreatmentProgressItem`/`ExamSession`/`CareTicket`, dan den rollback nghiep vu khong duoc xac dinh ro.
- Force delete `TreatmentPlan` co the de lai `PlanItem` orphan do FK `set null`.
- Gan doctor/assistant/used_by ngoai branch tren treatment flow.

## Service / action / state machine / transaction boundary de xuat

- Tao `TreatmentPlanWorkflowService`:
  - `submitDraft()`
  - `approve()`
  - `start()`
  - `complete()`
  - `cancel()`
  - enforce permission, timestamp, audit metadata
- Tao `TreatmentMaterialUsageService`:
  - consume theo session + batch
  - revert khi delete/void
  - `lockForUpdate()` tren batch/material
  - tao `InventoryTransaction` cung transaction
- Refactor `TreatmentProgressSyncService`:
  - dung `ExamSessionProvisioningService`
  - transaction cho `progress_day` + `progress_item`
  - safe re-entrant khi observer goi nhieu lan
- Tao `TreatmentDeleteGuardService`:
  - chan delete neu record da co lien ket van hanh/finance/inventory

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Diem tot:
  - treatment plan/session list co group theo benh nhan, hop voi le tan/bac si khi scan workspace.
  - relation manager cho plan item co quick actions ro rang cho `gui de xuat`, `KH dong y`, `KH tu choi`, `hoan thanh 1 lan`.
- Friction chinh:
  - form treatment plan cho phep chon trang thai cao ngay tu luc tao, de nguoi dung nhap sai quy trinh.
  - material usage form khong bat batch, khien user khong biet dang tru lo nao.
  - treatment material co edit page nhung save se fail theo model guard, tao UX rat nham.

## Filament UX

- Danh gia: `Trung binh`
- Diem tot:
  - query list da eager load co ban.
  - create/edit session co safe return URL va patient context, giam lac huong trong workspace.
- Diem yeu:
  - destructive actions van mo o nhieu cho: treatment plan, plan item, treatment session, treatment material.
  - dropdown `doctor_id`, `assistant_id`, `used_by` khong scope theo branch.
  - co relation manager legacy trong `TreatmentPlans/Relations/*` voi options khong scope va delete surface mo, du khong phai surface chinh.

## Edge cases quan trong

- 2 nguoi cung ghi nhan cung mot vat tu cho hai session dong thoi khi ton kho sap het.
- 1 session dieu tri bi xoa sau khi da tao `TreatmentProgressItem`, `CareTicket`, `InventoryTransaction`.
- bac si tao thang ke hoach o `in_progress` khi benh nhan chua dong y.
- session branch A chon doctor/assistant hoac `used_by` branch B.
- session done tao `ExamSession` moi trong khi `CLIN` da ton tai session cung patient/ngay/branch.
- vat tu co nhieu batch, mot batch het han nhung UI khong buoc chon batch hop le.
- force delete treatment plan sau khi da co plan item/session/material usage, gay orphan record va drift finance.
- material issue note da harden o `posted`, nhung treatment material hot path van co the lech voi ledger kho tong.

## Diem de thao tac sai

- Chon sai trang thai ke hoach ngay luc tao.
- Chon sai doctor/assistant vi dropdown load toan bo user.
- Mo trang `EditTreatmentMaterial` de sua, nhap xong moi bi model throw exception.
- Dung delete/force delete nhu mot thao tac binh thuong du nghiep vu da phat sinh nhieu side effect.

## De xuat cai thien UX

- Form tao treatment plan chi cho `draft`; cac transition cao hon phai qua action co confirm modal va permission ro rang.
- Treatment material form bat buoc chon batch, hien ton kho theo batch, han dung va canh bao FEFO.
- Loai bo `EditTreatmentMaterial`; giu create/delete-co-dieu-kien hoac `void` action ro rang hon.
- An/cam destructive actions khi record da co lien ket downstream; hien body modal neu can void thay vi xoa.
- Scope dropdown staff theo branch va them helper text `chi hien nhan su trong pham vi chi nhanh`.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| TRT-001 | Critical | Data Integrity | Treatment material consumption khong traceable theo batch va khong khoa transaction | Resolved | Da dua vao service transactional, bat batch selector va ledger theo batch |
| TRT-002 | Critical | Domain Logic | Treatment plan approval boundary bi bypass khi tao va khi table action mutate truc tiep | Resolved | Create payload da bi khoa ve `draft`; actions approve/start/complete/cancel da qua workflow service |
| TRT-003 | High | Concurrency | Treatment progress sync bypass provisioning service transactional | Resolved | Da reuse `ExamSessionProvisioningService`, bao `TreatmentSession -> ProgressDay/Item` trong transaction va observer sau commit |
| TRT-004 | High | Security | Staff selectors trong treatment flow chua branch-scoped | Resolved | Doctor/assistant options da scope theo branch, payload duoc sanitize server-side, `used_by` tro thanh server-owned |
| TRT-005 | High | Data Integrity | Delete/force delete surfaces va FK permissive de mo duong orphan treatment data | Resolved | Da khoa delete surface bang guard service, policy va UI gating |
| TRT-006 | Medium | Maintainability | Ton tai legacy relation managers yeu hon va de drift behavior | Resolved | Legacy relation managers da bi loai bo, canonical surface da duoc chot |
| TRT-007 | Medium | UX | Treatment material co edit page du model cam update | Resolved | Edit surface da duoc go bo, flow usage giu immutable boundary |
| TRT-008 | Medium | Maintainability | Regression suite chua khoa batch usage, state bypass va delete guard | Resolved | Regression suite TRT da bao phu state, sync, assignment, delete guard va material usage |

# Re-audit Update

- TRT da hoan thanh full lifecycle `review -> issues -> plan -> fix -> regression -> re-audit`.
- Code boundary da duoc khoa lai o 4 diem quan trong nhat:
  - material usage transaction-safe va traceable theo batch
  - treatment plan workflow khong con bypass state machine
  - treatment progress sync idempotent va reuse provisioning service cua `CLIN`
  - delete/force delete surface cua treatment core da bi khoa theo downstream linkage
- Full suite tren snapshot sau TRT dat:
  - `598 passed`
  - `3260 assertions`
  - `185.96s`
- Verdict duoc nang tu `D` len `B`.
- Module dat `Clean Baseline Reached`.

# Dependencies

- `PAT`: patient ownership va patient workspace entry point cho treatment
- `APPT`: lich hen va visit context de tao session dieu tri
- `CLIN`: consent gate, exam session provisioning, clinical evidence gate
- `INV`: batch, stock, inventory ledger
- `FIN`: invoice/payment linkage voi treatment plan/session

# Open Questions

- Approval final cua treatment plan cap plan thuoc vai tro nao: doctor, manager hay owner?
- Material usage trong treatment co bat buoc FEFO/FIFO, hay cho phep staff chon batch thu cong?
- Sau khi da co invoice/payment/prescription, treatment session co duoc `void` hay chi duoc tao adjustment event?
- Co can giu `TreatmentMaterial` nhu immutable append-only record khong, hay cho phep delete co kiem soat + reversal event?

# Recommended Next Steps

- Chuyen sang `FIN` de review va fix boundary thanh toan/hoa don khi `TRT` da on dinh.
- Sau `FIN`, review tiep `INV` de doi chieu them inventory side-effects tu treatment.
- Giu lai TRT regression suite trong cac full-suite run de chan drift cheo module.

# Current Status

- Clean Baseline Reached
