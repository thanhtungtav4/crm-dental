# Metadata

- Module code: `ZNS`
- Module name: `Zalo / ZNS`
- Current status: `In Fix`
- Current verdict: `D`
- Task ID prefix: `TASK-ZNS-`
- Source review: `docs/reviews/modules/ZNS-zalo-zns.md`
- Source issues: `docs/issues/ZNS-issues.md`
- Dependencies: `PAT, APPT, CARE, INT, OPS`
- Last updated: `2026-03-06`

# Objective

- Dong baseline cho `ZNS` theo 4 muc tieu: auth dung, workflow canonical, outbound race-safe, va payload governance du an toan production.

# Foundation fixes

## [TASK-ZNS-001] Khoa auth boundary cho page/resource/command ZNS
- Based on issue(s): `ZNS-001`, `ZNS-006`
- Priority: Foundation
- Objective:
  - Dat boundary truy cap dung cho `ZaloZns`, `ZnsCampaignResource` va command `zns:run-campaigns`.
- Scope:
  - policy/page gate, command auth, role matrix, branch-aware record access.
- Why now:
  - Neu auth boundary chua dung, moi fix khac deu chua du an toan.
- Suggested implementation:
  - Them `ZnsCampaignPolicy` va `canAccess()` cho `ZaloZns`.
  - Them `ActionGate::authorize()` cho `RunZnsCampaigns`.
  - Khoa create/edit/delete/run surface theo role baseline.
- Affected files or layers:
  - `app/Filament/Pages/ZaloZns.php`
  - `app/Filament/Resources/ZnsCampaigns/*`
  - `app/Console/Commands/RunZnsCampaigns.php`
- Tests required:
  - auth matrix tests cho page/resource/command
- Estimated effort: M
- Dependencies:
  - GOV
- Exit criteria:
  - `Doctor` va role khong dung khong con vao/chay duoc module ZNS.

# Critical fixes

## [TASK-ZNS-002] Chot campaign workflow qua service canonical
- Based on issue(s): `ZNS-002`
- Priority: Critical
- Objective:
  - Loai mutation status truc tiep va dua campaign lifecycle ve 1 boundary duy nhat.
- Scope:
  - form/table/page actions, workflow transitions, audit metadata, delete/archive guard.
- Why now:
  - Day la source of truth cho outbound campaign, neu khong chot som se tiep tuc drift.
- Suggested implementation:
  - Tao `ZnsCampaignWorkflowService`.
  - Khoa `status` tren form, table/page chi goi workflow action.
  - Loai direct delete hoac chuyen sang archive flow co guard.
- Affected files or layers:
  - `app/Filament/Resources/ZnsCampaigns/*`
  - `app/Models/ZnsCampaign.php`
  - workflow service moi
- Tests required:
  - workflow transition tests
  - schema wiring tests
- Estimated effort: L
- Dependencies:
  - `TASK-ZNS-001`
- Exit criteria:
  - Moi transition campaign di qua workflow service va co audit ly do.

## [TASK-ZNS-003] Hardening cancel-processing race cho appointment reminder
- Based on issue(s): `ZNS-003`
- Priority: Critical
- Objective:
  - Dam bao event da bi cancel khong con co the gui ra provider khi worker dang xu ly.
- Scope:
  - automation publisher cancel path, sync worker claim/final pre-send check, appointment reminder source flow.
- Why now:
  - Day la business bug co the gui nham tin cho benh nhan sau khi lich hen da thay doi/huy.
- Suggested implementation:
  - Them cancel token/pre-send status re-check theo processing token.
  - Hoac dua provider send vao boundary transaction-aware co final cancel guard.
- Affected files or layers:
  - `app/Services/ZnsAutomationEventPublisher.php`
  - `app/Console/Commands/SyncZnsAutomationEvents.php`
- Tests required:
  - race regression test cancel-vs-processing
- Estimated effort: L
- Dependencies:
  - `TASK-ZNS-001`
  - APPT, CARE
- Exit criteria:
  - Event da bi cancel khong gui outbound du worker da claim.

# High priority fixes

## [TASK-ZNS-004] Giam PII footprint va them retention/redaction cho ZNS logs
- Based on issue(s): `ZNS-004`
- Priority: High
- Objective:
  - Giam blast radius cua PII trong event/delivery/log tables.
- Scope:
  - schema, model casts, provider request/response persistence, retention boundary.
- Why now:
  - `ZNS` dang giu raw phone va payload provider o nhieu bang operational.
- Suggested implementation:
  - Encrypt/redact phone/payload nhay cam.
  - Them retention/prune command cho log/event da het gia tri van hanh.
- Affected files or layers:
  - zns migrations/models/services
  - ops retention tooling
- Tests required:
  - payload redaction/encryption tests
  - retention command tests
- Estimated effort: L
- Dependencies:
  - PAT, OPS
- Exit criteria:
  - Khong con raw phone/payload qua muc can thiet trong ZNS operational tables.

## [TASK-ZNS-005] Them campaign-level lock cho runner
- Based on issue(s): `ZNS-005`
- Priority: High
- Objective:
  - Tranh duplicate audience scan va status flap khi nhieu worker chay cung campaign.
- Scope:
  - campaign claim lock/token, runner gating, summary refresh sequencing.
- Why now:
  - Reliability cua delivery da tot o row-level, nhung campaign-level van con ho.
- Suggested implementation:
  - Them processing token/locked_at o `zns_campaigns` hoac workflow-level claim boundary.
- Affected files or layers:
  - `app/Services/ZnsCampaignRunnerService.php`
  - `app/Models/ZnsCampaign.php`
  - migration `zns_campaigns`
- Tests required:
  - concurrency test cho duplicate runner claim
- Estimated effort: M
- Dependencies:
  - `TASK-ZNS-002`
- Exit criteria:
  - Chi 1 runner active cho 1 campaign tai 1 thoi diem.

# Medium priority fixes

## [TASK-ZNS-006] Cung co operational UX cho ZNS
- Based on issue(s): `ZNS-007`
- Priority: Medium
- Objective:
  - Giam sai thao tac va ho tro triage production.
- Scope:
  - read-only status field, reason modal, deliveries filters, operational dashboard/page.
- Why now:
  - Sau khi auth va workflow da dung, operator can UI an toan de van hanh.
- Suggested implementation:
  - Bien `ZaloZns` thanh dashboard retry/dead-letter.
  - Them filters `provider_status_code`, `next_retry_at`, `attempt_count`, `normalized_phone`.
- Affected files or layers:
  - `app/Filament/Pages/ZaloZns.php`
  - `app/Filament/Resources/ZnsCampaigns/*`
- Tests required:
  - feature tests cho filter/schema wiring
- Estimated effort: M
- Dependencies:
  - `TASK-ZNS-002`
- Exit criteria:
  - Operator triage dead-letter/retry duoc ngay tren UI ma khong can SQL tay.

# Low priority fixes

- Chua co low priority item cho den khi dong xong baseline auth/workflow/race/PII.

# Testing & regression protection

## [TASK-ZNS-007] Bo sung regression suite cho auth, race va payload governance
- Based on issue(s): `ZNS-008`
- Priority: High
- Objective:
  - Khoa regression cho 3 blocker chinh cua module.
- Scope:
  - auth matrix, cancel-processing race, command auth, campaign concurrency, redaction/retention.
- Why now:
  - `ZNS` la outbound side-effect module; regression nho cung tao tac dong thuc.
- Suggested implementation:
  - Them feature tests rieng cho page/resource auth.
  - Them race tests cho appointment reminder cancellation.
  - Them command auth va runner lock tests.
  - Them redaction/retention tests.
- Affected files or layers:
  - `tests/Feature/*Zns*`
- Tests required:
  - chinh task nay la regression backlog
- Estimated effort: M
- Dependencies:
  - `TASK-ZNS-001` -> `TASK-ZNS-006`
- Exit criteria:
  - Module co regression suite bao du auth/workflow/race/PII governance.

# Re-audit checklist

- `Doctor` va role khong dung khong con access page/resource/command ZNS.
- Campaign status chi doi qua workflow service va co audit ly do.
- Appointment reminder cancel sau claim khong gui outbound sai.
- Phone/payload trong event/log table da duoc redact/encrypt theo policy.
- Campaign runner khong con duplicate audience scan khi nhieu worker chay cung luc.
- UI triage du filters cho failed/dead-letter/retry.
- Full suite xanh sau khi ZNS baseline duoc khoa.

# Execution order

1. `TASK-ZNS-001`
2. `TASK-ZNS-002`
3. `TASK-ZNS-003`
4. `TASK-ZNS-004`
5. `TASK-ZNS-005`
6. `TASK-ZNS-006`
7. `TASK-ZNS-007`

# What can be done in parallel

- `TASK-ZNS-003` co the song song voi phan cuoi `TASK-ZNS-002` neu workflow boundary campaign da ro.
- `TASK-ZNS-004` co the bat dau song song voi `TASK-ZNS-005` sau khi schema strategy duoc thong nhat.
- `TASK-ZNS-006` co the lam sau khi workflow actions cua `TASK-ZNS-002` on dinh.

# What must be done first

- `TASK-ZNS-001` va `TASK-ZNS-002` phai lam truoc vi do la auth boundary va workflow source of truth.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-ZNS-001`
  - `TASK-ZNS-002`
- Milestone 2:
  - `TASK-ZNS-003`
  - `TASK-ZNS-005`
- Milestone 3:
  - `TASK-ZNS-004`
  - `TASK-ZNS-006`
- Milestone 4:
  - `TASK-ZNS-007`
  - re-audit
