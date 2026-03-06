# Metadata

- Module code: `GOV`
- Module name: `Governance / Branches / RBAC / Audit`
- Current status: `In Fix`
- Current verdict: `D`
- Review file: `docs/reviews/modules/GOV-branches-rbac-audit.md`
- Issue file: `docs/issues/GOV-issues.md`
- Plan file: `docs/planning/GOV-plan.md`
- Issue ID prefix: `GOV-`
- Task ID prefix: `TASK-GOV-`
- Dependencies: `PAT, APPT, CLIN, TRT, FIN, INV, CARE, ZNS, OPS`
- Last updated: `2026-03-06`

# Scope

- Review module `GOV` theo 4 lop: architecture, database, domain logic, UI/UX.
- Trong pham vi review: `branches`, `doctor_branch_assignments`, `branch_transfer_requests`, `branch_logs`, `audit_logs`, `roles`, `permissions`, Filament resources GOV va cac service/policy lien quan.
- Dependency edge duoc xem xet khi anh huong truc tiep toi `PAT`, `APPT`, `CLIN`, `FIN`, `OPS`.

# Context

- He thong la CRM phong nha khoa Laravel 12 + Filament 4, da chi nhanh, nhieu vai tro, co du lieu nhay cam benh nhan va thanh toan.
- GOV la module nen cho branch scoping, RBAC, auditability va operational safety.
- Evidence chinh duoc lay tu schema, policy, resource, service va runtime permission checks.
- Thong tin con thieu lam giam do chinh xac review: chua co ma tran phan quyen nghiep vu chinh thuc theo vai tro/chi nhanh; chua co retention policy cho audit log; chua co quy trinh van hanh chinh thuc cho branch transfer approval.

# Executive Summary

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`, chua dat clean baseline cho module GOV.
- Cac canh bao nghiem trong:
  - `Manager` dang co CRUD tren `Branch`, `User`, `Role`, tao duong leo thang dac quyen ro rang.
  - `UserForm` cho phep gan role, permission va chi nhanh qua rong, khong loc theo actor.
  - `AuditLogResource` dang overexpose; runtime xac nhan `doctor` co the `canViewAny()`.
  - `requestTransfer()` co race window tao duplicate pending transfer request.

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - `database/seeders/RolesAndPermissionsSeeder.php:27-121`
  - `app/Policies/UserPolicy.php:12-65`
  - `app/Policies/RolePolicy.php:15-68`
  - `app/Policies/BranchPolicy.php:15-68`
  - `app/Filament/Resources/Users/Schemas/UserForm.php:18-145`
  - `app/Filament/Resources/AuditLogs/AuditLogResource.php:17-91`
- Findings:
  - Manager duoc cap CRUD cho tat ca resource baseline, bao gom `Branch`, `User`, `Role`.
  - Policy GOV chi proxy thang sang permission string, khong co guard bo sung cho scope hoac resource nhay cam.
  - `UserForm` mo toan bo `roles`, `permissions`, `branch` options cho actor hien tai.
  - `AuditLogResource` khong co policy rieng; runtime check xac nhan `doctor` co the xem.
- Suggested direction:
  - Tach `RoleBaselineRegistry` hoac `RBAC baseline service` khoi seeder.
  - Khoa quyen `Branch`, `User`, `Role` chi cho `Admin` hoac role governance rieng.
  - Dua logic assignable roles/permissions vao `UserProvisioningAuthorizer`.
  - Them `AuditLogPolicy` rieng va gioi han resource theo branch/actor scope.

## Data Integrity & Database

- Danh gia: `Trung binh`
- Evidence:
  - Schema `branches`, `doctor_branch_assignments`, `branch_transfer_requests`, `audit_logs`
  - `app/Models/AuditLog.php:14-130`
  - `app/Models/EmrAuditLog.php:48-120`
- Findings:
  - `branches.code` unique va `doctor_branch_assignments(user_id, branch_id)` unique la diem tot.
  - `branch_transfer_requests` co index tim kiem, nhung chua co uniqueness guard cho pending request.
  - `audit_logs` thieu `branch_id`, `patient_id`, `occurred_at` rieng, lam yeu traceability va filter efficiency.
  - `AuditLog` chua immutable o model layer, trong khi `EmrAuditLog` da duoc khoa update/delete.
- Suggested direction:
  - Bo sung structured context columns va index cho `audit_logs`.
  - Bien `AuditLog` thanh immutable event log nhat quan voi `EmrAuditLog`.
  - Xem xet unique guard cho branch transfer pending state.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - `app/Services/PatientBranchTransferService.php:17-53`
  - `app/Services/PatientBranchTransferService.php:55-153`
  - `app/Models/BranchTransferRequest.php:13-44`
- Findings:
  - `requestTransfer()` dung pattern `exists()` roi `create()` ngoai transaction.
  - `applyTransferRequest()` co transaction + `lockForUpdate()`, day la diem tot.
  - `rejectTransferRequest()` chua khoa row va chua co transaction boundary ro rang.
- Suggested direction:
  - Bao boi `requestTransfer()` va `rejectTransferRequest()` trong transaction.
  - Them lock/idempotency guard cho pending transfer creation.
  - Can nhac unique pending request rule o schema hoac guard table.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Support/BranchAccess.php:10-174`
  - `app/Filament/Resources/Branches/BranchResource.php:19-69`
  - `app/Filament/Resources/Users/UserResource.php:17-57`
  - `app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php:14-113`
- Findings:
  - `BranchAccess` la helper nen tot, nhung chua duoc dung nhat quan trong resource GOV.
  - `BranchResource`, `UserResource`, `AuditLogResource` chua override `getEloquentQuery()` de scope branch.
  - `AuditLogsTable` thieu filter branch/patient/actor, se som nghen khi volume tang.
- Suggested direction:
  - Ap dung `BranchAccess::scopeQueryByAccessibleBranches()` o resource GOV.
  - Eager load actor va bo sung structured filters cho audit table.
  - Tach report query khoi UI table neu volume audit log lon.

## Maintainability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Support/BranchAccess.php`
  - `app/Services/ActionPermissionBaselineService.php`
  - `app/Support/SensitiveActionRegistry.php`
  - `tests/Feature/ActionSecurityCoverageTest.php`
  - `tests/Feature/SecurityMfaAndSessionPolicyTest.php`
- Findings:
  - Nen tang permission baseline va MFA/session middleware la diem manh.
  - Cau truc governance van bi phan manh: permission matrix nam trong seeder, audit model khong dong nhat, branch log UI khong ro muc dich.
- Suggested direction:
  - Dua RBAC baseline vao registry/service de audit va test de hon.
  - Chuan hoa log model va resource read-only behavior.
  - Giam permission complexity trong UI, tang service boundary.

## Better Architecture Proposal

- Tach GOV thanh 4 boundary ro rang:
  - `RBAC baseline registry`
  - `User provisioning authorizer/service`
  - `Audit visibility + immutable audit recorder`
  - `Branch transfer state machine`
- Filament resource GOV chi la lop giao dien. Moi logic nhay cam phai di qua service/action va transaction boundary.
- Mọi system log (`AuditLog`, `BranchLog`) can duoc xem nhu immutable event stream thay vi CRUD resource.

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Trung binh`
- Workflow GOV hien co:
  - Quan tri branch
  - Quan tri user va phan quyen
  - Gan bac si/nhan su vao chi nhanh
  - Chuyen benh nhan giua cac chi nhanh
  - Theo doi audit/branch logs
- Nhan xet:
  - Branch transfer da co service rieng la huong dung.
  - Workflow user/role provisioning hien chua co boundary nghiep vu du manh.

## State transitions

- Danh gia: `Trung binh`
- `BranchTransferRequest` co cac state `pending`, `applied`, `rejected`, `cancelled`.
- `apply` duoc bao ve tot hon `request` va `reject`.
- Chua co state machine ro rang de dinh nghia forbidden transitions va idempotency rule.

## Missing business rules

- Chua co quy tac chinh thuc `role nao duoc gan role nao`.
- Chua co quy tac `manager chi duoc quan ly user thuoc pham vi branch nao`.
- Chua co quy tac `ai duoc xem audit log cap he thong`.
- Chua co quy tac `system log la immutable, khong duoc sua/xoa`.
- Chua co idempotency rule cho branch transfer request dang pending.

## Invalid states / forbidden transitions

- Manager co the tu tang quyen bang cach cap role/permission qua user form.
- Doctor co the xem audit log he thong neu resource guard tiep tuc hoat dong nhu hien tai.
- Branch log dang la system log nhung van co create/edit/delete surface.
- Co the ton tai nhieu pending transfer request trung nhau cho cung patient va target branch.

## Service / action / state machine / transaction boundary de xuat

- Tao `UserProvisioningService` de xu ly role/permission assignment, branch scope va audit.
- Tao `BranchTransferStateMachine` hoac it nhat service transition co transaction cho `request/apply/reject/cancel`.
- Tao `AuditLogRecorder` bat buoc metadata co cau truc va branch context.
- Chuyen `BranchLog` thanh derived immutable event, read-only end-to-end.

# QA/UX Findings

## User flow

- Danh gia: `Kem`
- Form user hien tai qua nguy hiem cho role khong phai admin.
- Audit log table khong toi uu cho dieu tra van hanh that su vi thieu filter branch/patient/actor.
- Branch log resource co form rong, tao cam giac flow nua vo, nua he thong.

## Filament UX

- Danh gia: `Trung binh`
- Diem tot:
  - Resource GOV co cau truc ro, table co search co ban.
  - Audit log UI da view-only o resource level.
- Diem yeu:
  - `UsersTable` va `BranchesTable` van co destructive actions mac dinh ma khong co warning nghiep vu manh.
  - Metadata audit log dang hien JSON tho.
  - Khong co hien thi pham vi branch tren man provisioning user.

## Edge cases quan trong

- Manager mo 2 tab, gui 2 yeu cau transfer cung luc cho cung patient.
- Manager vo tinh tu gan role nhay cam cho minh hoac nguoi khac.
- Doctor xem duoc audit log ngoai pham vi branch.
- Branch dich bi deactivate trong luc transfer dang pending.
- User dang login bi doi branch/role trong khi session van mo.
- Branch log bi sua/xoa neu permission duoc mo sau nay.
- Van hanh can tra audit theo patient/branch nhung schema hien tai kho ho tro.

## Diem de thao tac sai

- Checkbox roles/permissions qua rong, de click nham.
- Branch select cho doctor khong scope theo actor.
- Resource branch log co create/edit pages du khong nen thao tac tay.
- Bulk delete tren user/branch de gay sai sot nghiem trong neu khong co governance rules ro rang.

## De xuat cai thien UX

- An hoac khoa hoan toan `roles`/`permissions` doi voi non-admin.
- Dung grouped select va helper text cho role nhay cam.
- Them confirm modal cho moi thao tac cap quyen, doi branch, revoke role.
- Audit log table can filter `branch`, `patient`, `actor`, `entity_type` va hien metadata dang key-value thay vi JSON tho.
- Branch transfer UI can co thong diep idempotent nhu `yeu cau dang cho xu ly` thay vi tao record trung.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| GOV-001 | Critical | Security | Manager co the leo thang dac quyen qua User/Role/Branch | In Fix | Seeder baseline da duoc siet lai, can tiep tuc re-audit sau khi rollout. |
| GOV-002 | Critical | Security | User form cho phep gan role/permission va branch khong gioi han | In Fix | Authorizer + form/page guard da duoc noi vao code, can re-audit sau khi rollout. |
| GOV-003 | Critical | Security | Audit log bi lo cho role nghiep vu do thieu policy va scope | In Fix | `doctor` co the `canViewAny()` tren AuditLogResource; dang khoa bang policy + query scope. |
| GOV-004 | High | Data Integrity | AuditLog thuong chua immutable va thieu structured context | In Fix | Dang bo sung `branch_id`, `patient_id`, `occurred_at` va immutable guard. |
| GOV-005 | High | Concurrency | Tao request chuyen chi nhanh chua an toan truoc race-condition | In Fix | `requestTransfer()` va `rejectTransferRequest()` dang duoc harden bang transaction + row lock + regression test. |
| GOV-006 | High | Maintainability | BranchLog dang la system log nhung van co edit/delete surface | In Fix | Resource da bi cat create/edit/delete; model dang duoc khoa immutable + branch-scoped view. |
| GOV-007 | High | Security | Resource GOV chua branch-aware o query layer | In Fix | `BranchResource` va `UserResource` dang duoc bo sung query scope + route binding scope + delegated-view tests. |
| GOV-008 | Medium | Maintainability | Coverage chua chan regression o RBAC va transfer concurrency | Open | Thieu test cho escalation, audit auth va duplicate pending transfer. |

# Dependencies

- `PAT`: patient branch ownership va transfer history.
- `APPT`: doctor-branch assignment va appointment branch isolation.
- `CLIN`: truy cap clinical records theo branch va actor scope.
- `TRT`: dieu tri va material usage phu thuoc actor/branch governance.
- `FIN`: payment visibility, reversal authorization, auditability.
- `INV`: branch-scoped inventory ownership va stock movements.
- `CARE`: CSKH permissions, automation actor scope.
- `ZNS`: integration-level action permissions va audit trace.
- `OPS`: observability, forensic audit, platform governance.

# Open Questions

- Role `Manager` trong nghiep vu thuc te duoc phep quan ly nhung gi va trong pham vi branch nao?
- Co ton tai role governance rieng cho HR/IT/system administration hay khong?
- Audit log co can branch-level visibility cho manager hay chi admin/co soat noi bo?
- Branch transfer co can luong duyet 2 buoc hay SLA xu ly hay khong?
- Co can retention / archive policy rieng cho audit log va branch log hay khong?

# Recommended Next Steps

- Tao issue file canonical cho GOV va khoa map issue ID.
- Tao implementation plan cho GOV voi uu tien `RBAC -> audit visibility -> transfer concurrency -> immutable logs -> regression tests`.
- Chua nen fix sau `PAT`, `APPT`, `CLIN`, `FIN` truoc khi GOV dat baseline toi thieu.

# Current Status

- In Fix
