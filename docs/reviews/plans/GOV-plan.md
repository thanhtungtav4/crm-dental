# Metadata

- Module code: `GOV`
- Module name: `Governance / Branches / RBAC / Audit`
- Current status: `In Fix`
- Current verdict: `D`
- Task ID prefix: `TASK-GOV-`
- Source review: `docs/reviews/modules/GOV-branches-rbac-audit.md`
- Source issues: `docs/reviews/issues/GOV-issues.md`
- Dependencies: `PAT, APPT, CLIN, TRT, FIN, INV, CARE, ZNS, OPS`
- Last updated: `2026-03-06`

# Objective

- Dua module GOV tu `D` len it nhat `B` baseline bang cach dong 4 khe hong nen: RBAC escalation, user provisioning scope, audit log visibility/integrity, va branch transfer concurrency.
- Tao governance baseline de cac module `PAT`, `APPT`, `CLIN`, `FIN`, `INV` co the fix sau ma khong bi troi branch scope va authorization.

# Foundation fixes

## [TASK-GOV-001] Khoa lai baseline RBAC cho Manager va resource governance nhay cam
- Based on issue(s): `GOV-001`
- Priority: Foundation
- Objective:
  - Cat duong privilege escalation truc tiep cua `Manager`.
- Scope:
  - Permission seeder / baseline registry
  - Policy va navigation access lien quan `Branch`, `User`, `Role`
- Why now:
  - Day la issue nguy hiem nhat va la dieu kien tien de cho moi fix GOV khac.
- Suggested implementation:
  - Tach danh sach resource manager duoc phep quan ly khoi `$resources` chung.
  - Khong cap CRUD `Branch`, `User`, `Role` cho manager trong baseline.
  - Neu can role governance rieng, dinh nghia bang permission set tach biet.
- Affected files or layers:
  - `database/seeders/RolesAndPermissionsSeeder.php`
  - `app/Policies/*`
  - Cau hinh Filament navigation/auth neu can
- Tests required:
  - Feature test permission matrix cho `Admin`, `Manager`, `Doctor`, `CSKH`
  - Feature test manager bi chan CRUD `Branch`, `User`, `Role`
- Estimated effort: `M`
- Dependencies:
  - None
- Exit criteria:
  - Manager khong con co CRUD `Branch`, `User`, `Role`
  - Test permission matrix pass

# Critical fixes

## [TASK-GOV-002] Gioi han user provisioning theo actor, branch va role sensitivity
- Based on issue(s): `GOV-002`, `GOV-007`
- Priority: Critical
- Objective:
  - Bien user provisioning thanh flow an toan theo role va branch scope.
- Scope:
  - `UserForm`
  - User provisioning service/authorizer
  - Branch option loading
- Why now:
  - Sau khi cat baseline RBAC, can khoa ngay duong cap quyen sai o UI va submit path.
- Suggested implementation:
  - Tao `UserProvisioningAuthorizer` de tra ve assignable roles, permissions, branches theo actor.
  - An direct permission assignment cho non-admin.
  - Loc `branch_id` va `doctor_branch_ids` theo `BranchAccess`.
  - Them audit cho role/permission changes va confirm modal cho role nhay cam.
- Affected files or layers:
  - `app/Filament/Resources/Users/Schemas/UserForm.php`
  - `app/Filament/Resources/Users/UserResource.php`
  - service/support layer moi hoac hien co trong `app/Support`
- Tests required:
  - Feature test assignable role/permission theo actor
  - Feature/browser test branch-scoped provisioning
- Estimated effort: `L`
- Dependencies:
  - `TASK-GOV-001`
- Exit criteria:
  - Non-admin khong the thay/gan role nhay cam hoac permission tuy y
  - Branch options da duoc scope dung

## [TASK-GOV-003] Khoa audit log visibility va branch-aware access
- Based on issue(s): `GOV-003`, `GOV-007`
- Priority: Critical
- Objective:
  - Chan overexposure audit log va dat rule xem log cap he thong ro rang.
- Scope:
  - `AuditLogPolicy`
  - `AuditLogResource`
  - `AuditLogsTable`
- Why now:
  - Audit log dang lo cho role nghiep vu va co the tiep tuc ro rong neu chua sua.
- Suggested implementation:
  - Tao `AuditLogPolicy` rieng.
  - Restrict `canViewAny/canView` cho `Admin` hoac governance role.
  - Neu cho manager xem mot phan, phai scope query theo branch.
  - Bo sung filter `branch`, `patient`, `actor` neu schema cho phep.
- Affected files or layers:
  - `app/Policies/AuditLogPolicy.php`
  - `app/Filament/Resources/AuditLogs/AuditLogResource.php`
  - `app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php`
- Tests required:
  - Feature test doctor/manager non-governance bi chan
  - Feature test allowed role xem dung pham vi
- Estimated effort: `M`
- Dependencies:
  - `TASK-GOV-001`
- Exit criteria:
  - Doctor khong con truy cap duoc audit log
  - Query auth va policy auth thong nhat

# High priority fixes

## [TASK-GOV-004] Chuan hoa AuditLog thanh immutable event log co structured context
- Based on issue(s): `GOV-004`
- Priority: High
- Objective:
  - Tang do tin cay va kha nang forensic cho audit trail chung.
- Scope:
  - Migration `audit_logs`
  - `AuditLog` model
  - Audit recording helper/service
- Why now:
  - Khong the xay governance production neu audit log co the bi sua/xoa va kho filter.
- Suggested implementation:
  - Them `branch_id`, `patient_id`, `occurred_at` va index phu hop.
  - Them immutable guard cho `AuditLog`.
  - Chuan hoa method `record()` de nhan structured context.
- Affected files or layers:
  - migration moi
  - `app/Models/AuditLog.php`
  - noi goi `AuditLog::record()` quan trong
- Tests required:
  - Feature test update/delete bi chan
  - Feature test record audit co structured context
- Estimated effort: `L`
- Dependencies:
  - `TASK-GOV-003`
- Exit criteria:
  - Audit log khong the update/delete
  - Audit queries co the loc theo branch/patient bang column rieng

## [TASK-GOV-005] Hardening branch transfer request truoc race-condition va invalid transition
- Based on issue(s): `GOV-005`
- Priority: High
- Objective:
  - Dam bao branch transfer request la idempotent va nhat quan duoi concurrent traffic.
- Scope:
  - `PatientBranchTransferService`
  - `BranchTransferRequest` schema/index neu can
- Why now:
  - `PAT`, `APPT`, `CLIN` deu phu thuoc patient branch ownership dung.
- Suggested implementation:
  - Dua `requestTransfer()` va `rejectTransferRequest()` vao transaction.
  - Dung `lockForUpdate()` cho patient/request records lien quan.
  - Them pending uniqueness guard neu mo ta duoc invariant.
  - Re-validate branch dich tai moi transition.
- Affected files or layers:
  - `app/Services/PatientBranchTransferService.php`
  - migration/index neu can
  - `app/Models/BranchTransferRequest.php`
- Tests required:
  - Concurrency test duplicate pending request
  - Feature test reject/apply invalid transition
- Estimated effort: `M`
- Dependencies:
  - `TASK-GOV-001`
- Exit criteria:
  - Khong tao duoc 2 pending request trung nhau
  - Transition `request/apply/reject` on dinh duoi concurrent access

## [TASK-GOV-006] Bien BranchLog thanh read-only immutable event view
- Based on issue(s): `GOV-006`, `GOV-007`
- Priority: High
- Objective:
  - Loai bo CRUD surface khong phu hop cua system log va dong bo branch-scoped viewing.
- Scope:
  - `BranchLog` model
  - Filament BranchLog resource/pages/table/form
- Why now:
  - Branch log hien tai vua gay nham UX vua mo duong integrity risk neu permission thay doi sau nay.
- Suggested implementation:
  - Xoa create/edit/delete actions va pages hoac khoa hoan toan.
  - Neu giu resource, chi con list/view read-only.
  - Them immutable guard cho `BranchLog` neu van ton tai model operational.
  - Scope query theo branch accessible.
- Affected files or layers:
  - `app/Models/BranchLog.php`
  - `app/Filament/Resources/BranchLogs/*`
- Tests required:
  - Feature test read-only resource
  - Feature test branch-scoped visibility neu role duoc xem
- Estimated effort: `M`
- Dependencies:
  - `TASK-GOV-001`
  - `TASK-GOV-004`
- Exit criteria:
  - BranchLog khong con create/edit/delete surface
  - Read-only behavior duoc test va pass

# Medium priority fixes

## [TASK-GOV-007] Bo sung regression protection cho RBAC, audit auth va transfer concurrency
- Based on issue(s): `GOV-008`, `GOV-001`, `GOV-002`, `GOV-003`, `GOV-005`, `GOV-006`
- Priority: Medium
- Objective:
  - Khoa regression cho nhung khe hong GOV da sua.
- Scope:
  - Feature tests
  - Browser tests neu can cho provisioning UI
- Why now:
  - Sau khi fix logic nen, can khoa lai bang tests truoc khi mo rong sang module khac.
- Suggested implementation:
  - Them matrix tests cho role baseline.
  - Them tests cho resource auth, assignable form options, duplicate pending transfer, immutable logs.
- Affected files or layers:
  - `tests/Feature/*`
  - `tests/Browser/*` neu can
- Tests required:
  - Chinh task nay la backlog test regression
- Estimated effort: `M`
- Dependencies:
  - `TASK-GOV-001`
  - `TASK-GOV-002`
  - `TASK-GOV-003`
  - `TASK-GOV-004`
  - `TASK-GOV-005`
  - `TASK-GOV-006`
- Exit criteria:
  - Tat ca regression tests GOV moi deu pass va chan duong loi cu quay lai

# Low priority fixes

- Chua co low-priority task rieng. Toan bo backlog GOV hien tai deu can xu ly truoc khi module dat baseline dang tin cay.

# Testing & regression protection

- Feature test permission matrix cho role governance.
- Feature test manager khong CRUD duoc `Branch`, `User`, `Role`.
- Feature test non-admin khong the gan role nhay cam trong `UserForm`.
- Feature test `AuditLogResource` bi chan cho `doctor`.
- Feature test `AuditLog` immutable.
- Feature test `BranchLog` read-only.
- Concurrency/idempotency test cho `PatientBranchTransferService`.
- Neu UI provisioning co nhieu interaction, can bo sung browser test cho flow role assignment va branch option visibility.

# Re-audit checklist

- Xac nhan manager khong con duong leo thang dac quyen.
- Xac nhan user provisioning da scope theo actor va branch.
- Xac nhan doctor khong the xem audit log cap he thong.
- Xac nhan `AuditLog` va `BranchLog` la immutable neu duoc giu lai.
- Xac nhan khong tao duoc duplicate pending branch transfer.
- Xac nhan resource GOV da branch-aware o query layer.
- Xac nhan regression tests GOV moi deu pass.
- Danh gia lai verdict va clean baseline status.

# Execution order

1. `TASK-GOV-001`
2. `TASK-GOV-002`
3. `TASK-GOV-003`
4. `TASK-GOV-004`
5. `TASK-GOV-005`
6. `TASK-GOV-006`
7. `TASK-GOV-007`

# What can be done in parallel

- `TASK-GOV-002` va `TASK-GOV-003` co the tach nguoi lam sau khi `TASK-GOV-001` xong.
- `TASK-GOV-005` co the song song voi `TASK-GOV-004` neu khong dung chung migration.
- `TASK-GOV-007` nen bat dau viet test skeleton song song, nhung chi finalize sau khi logic chot.

# What must be done first

- `TASK-GOV-001` bat buoc phai lam truoc vi no cat duong privilege escalation va dinh nghia lai baseline authorization cho ca module.

# Suggested milestone breakdown

- Milestone 1: `TASK-GOV-001`
  - Cat baseline privilege escalation.
- Milestone 2: `TASK-GOV-002`, `TASK-GOV-003`
  - Khoa provisioning va audit visibility.
- Milestone 3: `TASK-GOV-004`, `TASK-GOV-005`, `TASK-GOV-006`
  - Dong data integrity, event immutability, transfer concurrency.
- Milestone 4: `TASK-GOV-007`
  - Khoa regression va chuan bi re-audit.
