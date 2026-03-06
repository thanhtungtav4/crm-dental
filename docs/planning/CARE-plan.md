# Metadata

- Module code: `CARE`
- Module name: `Customer Care / Automation`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-CARE-`
- Source review: `docs/reviews/modules/CARE-customer-care-automation.md`
- Source issues: `docs/issues/CARE-issues.md`
- Dependencies: `PAT, APPT, FIN, ZNS, KPI`
- Last updated: `2026-03-06`

# Objective

- Dong baseline cho `CARE` theo 4 muc tieu: auth/scope dung, ticket invariant idempotent, UX canonical theo `Note`, va regression suite khoa duplicate/auth leak/search regression.

# Foundation fixes

## [TASK-CARE-001] Khoa page/report auth va branch scope cho CARE
- Based on issue(s): `CARE-001`, `CARE-002`
- Priority: Foundation
- Objective:
  - Dat boundary truy cap dung cho `CustomerCare` va `CustomsCareStatistical`.
- Scope:
  - page permission, `canAccess()`/Shield, branch filter options, global scope rules cho admin/non-admin.
- Why now:
  - Neu page/report van mo sai scope, moi fix khac deu khong du an toan.
- Suggested implementation:
  - Them permission page-level; chi allow role dung baseline.
  - Branch-scope query va filter options theo actor.
  - Block `branch_scope_id = 0` voi non-admin.
- Affected files or layers:
  - `app/Filament/Pages/CustomerCare.php`
  - `app/Filament/Pages/Reports/CustomsCareStatistical.php`
  - permission seeder / page auth helpers
- Tests required:
  - auth matrix tests cho page va report
  - non-admin khong doc duoc global aggregate
- Estimated effort: M
- Dependencies:
  - GOV
- Exit criteria:
  - Page/report chi accessible voi role duoc phep va branch-scope dung cho non-admin.

# Critical fixes

## [TASK-CARE-002] Chot canonical care ticket invariant va idempotent workflow boundary
- Based on issue(s): `CARE-003`
- Priority: Critical
- Objective:
  - Loai duplicate care tickets duoi retry/concurrent runs.
- Scope:
  - unique/index/migration, transaction retry, service canonical cho upsert/cancel.
- Why now:
  - Duplicate ticket lam hong SLA queue, audit va automation semantics.
- Suggested implementation:
  - Tao `CareTicketWorkflowService`.
  - Doi `CareTicketService` va cac command lien quan sang workflow service.
  - Them unique composite hoac generation key canonical.
- Affected files or layers:
  - `app/Services/CareTicketService.php`
  - care automation commands
  - `database/migrations/*notes*`
- Tests required:
  - concurrency/idempotency tests
  - retry-safe scheduler tests
- Estimated effort: L
- Dependencies:
  - TASK-CARE-001
- Exit criteria:
  - Re-run cung source event khong tao duplicate ticket.

## [TASK-CARE-003] Hardening birthday automation side effect ordering
- Based on issue(s): `CARE-004`
- Priority: Critical
- Objective:
  - Dam bao birthday flow khong gui duplicate greeting.
- Scope:
  - ordering giua dedupe ticket, event publish va persistence.
- Why now:
  - Birthday la flow de gap duplicate side effect nhat vi chay dinh ky va co ZNS.
- Suggested implementation:
  - Move dedupe truoc publish.
  - Dung workflow service/outbox idempotent cho birthday event.
- Affected files or layers:
  - `app/Console/Commands/GenerateBirthdayCareTickets.php`
  - service publisher/event layer
- Tests required:
  - rerun/retry tests
  - duplicate publish guard tests
- Estimated effort: M
- Dependencies:
  - TASK-CARE-002
  - ZNS
- Exit criteria:
  - Birthday rerun trong cung nam khong tao them greeting side effect.

# High priority fixes

## [TASK-CARE-004] Branch-scope assignee selector va sanitize manual care payload
- Based on issue(s): `CARE-005`
- Priority: High
- Objective:
  - Khong cho gan ticket cho user ngoai branch/ngoai role.
- Scope:
  - selector options, filter options, server-side validation va sanitize `user_id`.
- Why now:
  - Cross-branch assignment lam drift queue va lo nghiep vu ngay tren UI hang ngay.
- Suggested implementation:
  - Tao `CareStaffAuthorizer`.
  - Apply cho `PatientNotesRelationManager` va CARE filters.
- Affected files or layers:
  - `PatientNotesRelationManager`
  - co the ca `CustomerCare` filters
- Tests required:
  - forged payload tests
  - option list scope tests
- Estimated effort: M
- Dependencies:
  - TASK-CARE-001
  - GOV, PAT
- Exit criteria:
  - Non-admin chi gan/nhin thay duoc staff trong scope branch hop le.

## [TASK-CARE-005] Chot CustomerCare page ve datasource canonical theo Note
- Based on issue(s): `CARE-006`
- Priority: High
- Objective:
  - Lam cho page CSKH phan anh dung ticket workflow thay vi mix raw records.
- Scope:
  - tab query, columns, status/channel semantics, export path.
- Why now:
  - Neu UI khong dung source of truth, user se thao tac sai du thong su code ben duoi da dung.
- Suggested implementation:
  - Doi cac tab reminder/follow-up/birthday ve query `Note` canonical.
  - Neu can source-specific context, eager-load source record qua `source_type/source_id` map hoac metadata.
- Affected files or layers:
  - `app/Filament/Pages/CustomerCare.php`
  - care workflow service / source resolvers
- Tests required:
  - Livewire/feature tests cho tab data va status canonical
- Estimated effort: L
- Dependencies:
  - TASK-CARE-002
- Exit criteria:
  - Page `CustomerCare` doc va hien ticket state nhat quan tren moi tab.

## [TASK-CARE-006] Loai destructive/edit drift surface va thay bang workflow actions
- Based on issue(s): `CARE-007`
- Priority: High
- Objective:
  - Bao ve provenance va audit cua care tickets.
- Scope:
  - `NoteResource`, relation manager actions, delete/edit policies, workflow actions.
- Why now:
  - Sau khi ticket canonical da duoc khoa, destructive surface can duoc cat de khong quay lai drift.
- Suggested implementation:
  - Remove delete/force delete cho automation tickets.
  - Thay bang actions `complete`, `follow_up`, `fail`, `cancel` qua workflow service.
- Affected files or layers:
  - `app/Filament/Resources/Notes/*`
  - `PatientNotesRelationManager`
  - `NotePolicy` / `Note` model
- Tests required:
  - feature tests cho destructive guard va workflow actions
- Estimated effort: M
- Dependencies:
  - TASK-CARE-002
  - TASK-CARE-005
- Exit criteria:
  - Ticket automation khong con bi edit/delete tuy y o UI.

# Medium priority fixes

## [TASK-CARE-007] Sua search phone va toi uu hot-path query tren CARE
- Based on issue(s): `CARE-008`
- Priority: Medium
- Objective:
  - Dong bo CARE voi PAT PII hardening va tranh regression search.
- Scope:
  - hash search cho phone, query helpers, summary/query optimization neu can.
- Why now:
  - Day la hot-path cho CSKH, nhung khong can chan task auth/invariant truoc.
- Suggested implementation:
  - Reuse `Patient::phoneSearchHash()` / `Customer::phoneSearchHash()`.
  - Can nhac tach search service cho `CustomerCare`.
- Affected files or layers:
  - `CustomerCare` columns/search
  - possible shared search helper
- Tests required:
  - search regression tests tren cac tab quan trong
- Estimated effort: M
- Dependencies:
  - PAT
  - TASK-CARE-005
- Exit criteria:
  - Tim kiem so dien thoai tren CARE hoat dong dung sau PII hardening.

# Low priority fixes

- Chua co low priority item cho den khi dong xong baseline auth/invariant/UX canonical.

# Testing & regression protection

## [TASK-CARE-008] Bo sung regression suite cho CARE baseline
- Based on issue(s): `CARE-009`
- Priority: High
- Objective:
  - Khoa auth leak, duplicate automation, selector scope, report scope va search regression.
- Scope:
  - feature/livewire tests cho page, report, workflow service va command idempotency.
- Why now:
  - CARE co nhieu scheduler path; khong co suite dung tam se rat de drift khi refactor.
- Suggested implementation:
  - Them test role matrix page/report.
  - Them duplicate scheduler tests.
  - Them branch-scope selector tests.
  - Them search phone tests.
- Affected files or layers:
  - `tests/Feature/*CARE*`
  - co the them `tests/Feature/CustomerCare*`
- Tests required:
  - chinh task nay la regression backlog
- Estimated effort: M
- Dependencies:
  - `TASK-CARE-001` -> `TASK-CARE-007`
- Exit criteria:
  - CARE co regression suite bao phu auth, idempotency, branch scope, va search.

# Re-audit checklist

- `CustomerCare` va `CustomsCareStatistical` co auth/scope dung cho role non-admin.
- Duplicate scheduler run khong tao duplicate care tickets.
- Birthday rerun khong gui duplicate greeting.
- Assignee selector va payload khong nhan user ngoai branch.
- Page CSKH doc ticket canonical thay vi status hard-coded/derived drift.
- Search phone tren CARE hoat dong dung voi du lieu da ma hoa.
- Full test suite xanh sau khi CARE baseline duoc khoa.

# Execution order

1. `TASK-CARE-001`
2. `TASK-CARE-002`
3. `TASK-CARE-003`
4. `TASK-CARE-004`
5. `TASK-CARE-005`
6. `TASK-CARE-006`
7. `TASK-CARE-007`
8. `TASK-CARE-008`

# What can be done in parallel

- `TASK-CARE-003` co the bat dau song song voi phan cuoi `TASK-CARE-002` neu workflow service/idempotency key da co.
- `TASK-CARE-004` co the chay song song voi `TASK-CARE-003`.
- `TASK-CARE-007` co the bat dau sau khi quyet dinh datasource canonical cua `TASK-CARE-005` da ro.

# What must be done first

- `TASK-CARE-001` va `TASK-CARE-002` phai lam truoc vi do la auth boundary va ticket invariant.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-CARE-001`
  - `TASK-CARE-002`
- Milestone 2:
  - `TASK-CARE-003`
  - `TASK-CARE-004`
- Milestone 3:
  - `TASK-CARE-005`
  - `TASK-CARE-006`
- Milestone 4:
  - `TASK-CARE-007`
  - `TASK-CARE-008`
  - re-audit
