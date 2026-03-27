# Metadata

- Module code: `INT`
- Module name: `Integrations`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-INT-`
- Source review: `docs/reviews/modules/INT-integrations.md`
- Source issues: `docs/reviews/issues/INT-issues.md`
- Dependencies: `GOV, APPT, CLIN, ZNS, OPS`
- Last updated: `2026-03-07`

# Objective

- Dong baseline cho `INT` theo 4 muc tieu: auth/settings boundary dung, internal EMR mutation co record scope, runtime settings save race-safe, payload governance/retention du an toan production, va secret rotation production-safe.
- Tat ca task trong plan nay da duoc hoan tat cho baseline hien tai.

# Foundation fixes

## [TASK-INT-001] Tach quyen xem va quyen sua IntegrationSettings
- Based on issue(s): `INT-001`
- Priority: Foundation
- Objective:
  - Dat auth boundary dung cho page `IntegrationSettings`, khong de role van hanh chung sua secret va runtime endpoint.
- Scope:
  - permission matrix, page gate, save action guard, audit log visibility theo role.
- Why now:
  - Neu auth boundary chua dung, moi fix sau do deu van co the bi actor sai role override.
- Suggested implementation:
  - Them permission rieng cho `view health`, `manage runtime settings`, `manage secrets`.
  - Chi `Admin` duoc luu secret/endpoint.
  - Role khac chi xem readiness/health hoac mot subset runtime policy neu duoc phep.
- Affected files or layers:
  - `database/seeders/RolesAndPermissionsSeeder.php`
  - `app/Filament/Pages/IntegrationSettings.php`
  - governance permission services/tests
- Tests required:
  - feature test auth matrix cho page
  - Livewire test chan `save()` voi actor chi co quyen view
- Estimated effort: M
- Dependencies:
  - GOV
- Exit criteria:
  - `Manager` khong con sua duoc secret/endpoint; page chi cho phep thao tac dung scope.

# Critical fixes

## [TASK-INT-002] Khoa branch/record scope cho internal EMR mutation
- Based on issue(s): `INT-002`
- Priority: Critical
- Objective:
  - Dam bao internal EMR API chi sua du lieu lam sang trong scope record/branch cho phep.
- Scope:
  - middleware/controller/service boundary cho amend clinical note.
- Why now:
  - Day la lo hong cross-branch tren du lieu lam sang, khong the de mo khi production.
- Suggested implementation:
  - Tao `InternalEmrMutationScopeService`.
  - Kiem tra `ClinicalNote.branch_id`, patient ownership, branch access cua automation actor truoc khi amend.
  - Tra `403` neu request ngoai scope.
- Affected files or layers:
  - `app/Http/Middleware/ValidateInternalEmrToken.php`
  - `app/Http/Controllers/Api/InternalEmrMutationController.php`
  - `app/Services/InternalEmrMutationService.php`
  - neu can: `ClinicalNoteVersioningService`
- Tests required:
  - feature test token hop le nhung note ngoai scope bi `forbidden`
  - feature test actor dung scope van amend duoc
- Estimated effort: M
- Dependencies:
  - `TASK-INT-001`
  - CLIN
- Exit criteria:
  - Internal EMR mutation khong con sua duoc `ClinicalNote` ngoai scope branch/record.

# High priority fixes

## [TASK-INT-003] Transactionalize va them optimistic revision cho IntegrationSettings
- Based on issue(s): `INT-003`
- Priority: High
- Objective:
  - Loai partial write va lost update tren `IntegrationSettings`.
- Scope:
  - save flow, compare token/revision, setting audit trail.
- Why now:
  - Runtime settings dang la central switch cua toan he thong, khong the de bulk save last-write-wins.
- Suggested implementation:
  - Dua save vao transaction chung.
  - Them revision hash/version cho page va reject stale form.
  - Neu save fail giua chung, rollback toan bo thay doi.
- Affected files or layers:
  - `app/Filament/Pages/IntegrationSettings.php`
  - `app/Models/ClinicSetting.php`
  - co the them migration metadata neu can
- Tests required:
  - Livewire test stale revision
  - feature test rollback khong partial write
- Estimated effort: M
- Dependencies:
  - `TASK-INT-001`
- Exit criteria:
  - Save settings hoac commit tron goi, hoac rollback toan bo; stale form bi chan.

## [TASK-INT-004] Chuan hoa payload governance va retention cho operational integration tables
- Based on issue(s): `INT-004`
- Priority: High
- Objective:
  - Giam blast radius cua PII/PHI trong inbound/outbound integration logs.
- Scope:
  - web lead ingestions, zalo webhook events, EMR sync events/logs, Google sync events/logs.
- Why now:
  - Day la bang operational nhay cam nhat cua module va dang phinh to dan theo thoi gian.
- Suggested implementation:
  - Tao sanitizer/encrypted cast strategy cho tung bang.
  - Giu lai checksum, ids va telemetry can thiet.
  - Them prune command/chinh sach retention.
- Affected files or layers:
  - migrations/models/services/commands lien quan
- Tests required:
  - feature test raw payload khong con plaintext
  - prune/retention tests
- Estimated effort: L
- Dependencies:
  - `TASK-INT-001`
  - OPS, ZNS, CLIN
- Exit criteria:
  - Payload nhay cam duoc redact/encrypt theo policy va co retention boundary ro rang.

# Medium priority fixes

## [TASK-INT-005] Tao workflow secret rotation co grace window va metadata audit
- Based on issue(s): `INT-005`
- Priority: Medium
- Objective:
  - Bien secret rotation thanh quy trinh production-safe thay vi overwrite ngay lap tuc.
- Scope:
  - shared token cho web lead, Zalo webhook, EMR internal API va UX warning lien quan.
- Why now:
  - Sau khi auth/save boundary da dung, can giam rui ro outage khi rotate token.
- Suggested implementation:
  - Tao `IntegrationSecretRotationService`.
  - Ho tro old/new token grace window neu kha thi; neu chua duoc thi can co warning, scheduled revoke, va metadata audit.
- Affected files or layers:
  - `IntegrationSettings`
  - token middleware
  - `ClinicSettingLog`/rotation metadata
- Tests required:
  - feature test rotation flow
  - feature test revoke old token sau grace period
- Estimated effort: L
- Dependencies:
  - `TASK-INT-001`
  - OPS
- Exit criteria:
  - Secret rotation co quy trinh ro rang, audit duoc, va khong cat client cu ngay lap tuc neu chua revoke.

# Low priority fixes

- Chua co low priority item truoc khi dong xong baseline auth/scope/payload governance.

# Testing & regression protection

## [TASK-INT-006] Bo sung regression suite cho auth matrix, settings concurrency va payload governance
- Based on issue(s): `INT-006`
- Priority: High
- Objective:
  - Khoa regression cho nhung boundary quan trong nhat cua `INT`.
- Scope:
  - auth matrix page settings, EMR internal scope, stale settings revision, payload redaction/retention.
- Why now:
  - `INT` la module co blast radius cao, regression nho se anh huong chao dao den module khac.
- Suggested implementation:
  - Them feature tests cho `IntegrationSettings` auth matrix.
  - Them tests cho stale settings save va rollback.
  - Them tests cho internal EMR mutation out-of-scope.
  - Them tests cho payload governance/prune command.
- Affected files or layers:
  - `tests/Feature/*Integration*`
  - `tests/Feature/*Emr*`
  - `tests/Feature/*GoogleCalendar*`
  - `tests/Feature/*WebLead*`
- Tests required:
  - chinh task nay la regression backlog
- Estimated effort: M
- Dependencies:
  - `TASK-INT-001` -> `TASK-INT-005`
- Exit criteria:
  - Module co regression suite bao du auth, scope, race, payload governance.

# Re-audit checklist

- `Manager` khong con sua duoc secret/runtime endpoint ngoai scope cho phep.
- Internal EMR mutation reject record ngoai scope branch.
- `IntegrationSettings` save khong con partial write/lost update.
- Bang van hanh integration khong con giu raw payload vuot qua policy.
- Secret rotation co workflow va audit metadata ro rang.
- Full suite xanh sau khi fix `INT`.

# Execution order

1. `TASK-INT-001`
2. `TASK-INT-002`
3. `TASK-INT-003`
4. `TASK-INT-004`
5. `TASK-INT-005`
6. `TASK-INT-006`

# What can be done in parallel

- `TASK-INT-002` va phan thiet ke `TASK-INT-003` co the chuan bi song song sau khi auth boundary `TASK-INT-001` duoc chot.
- `TASK-INT-004` co the tach thanh 2 lane: inbound payload governance va outbound payload governance.
- `TASK-INT-006` co the viet dan song song theo tung task khi boundary da on dinh.

# What must be done first

- `TASK-INT-001` phai lam truoc vi page settings dang la surface nguy hiem nhat cua module.
- `TASK-INT-002` phai lam ngay sau do vi day la lo hong cross-branch tren du lieu lam sang.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-INT-001`
  - `TASK-INT-002`
- Milestone 2:
  - `TASK-INT-003`
- Milestone 3:
  - `TASK-INT-004`
- Milestone 4:
  - `TASK-INT-005`
  - `TASK-INT-006`
  - re-audit
