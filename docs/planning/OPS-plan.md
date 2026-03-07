# Metadata

- Module code: `OPS`
- Module name: `Production readiness / backup / observability`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-OPS-`
- Source review: `docs/reviews/modules/OPS-production-readiness.md`
- Source issues: `docs/issues/OPS-issues.md`
- Dependencies: `GOV, INT, KPI, ZNS, FIN, INV`
- Last updated: `2026-03-07`

# Objective

- Tach ro verify lane va mutation lane trong release/ops pipeline.
- Dam bao backup artifact an toan cho du lieu nhay cam va co the duoc verify/restore that.
- Chuan hoa authorization, auditability va signoff contract cho control-plane commands.
- Dong baseline observability va regression suite cho module cuoi cua CRM.

# Foundation fixes

## [TASK-OPS-001] [Tach release gate verify-only khoi remediation mutation]
- Based on issue(s): `OPS-001`
- Priority: Foundation
- Objective:
  - Bien `ops:run-release-gates` thanh lenh assert-only, khong tu dong sua permission baseline hay state production.
- Scope:
  - `RunReleaseGates`, `RunProductionReadiness`, release SOP output.
- Why now:
  - Day la blocker logic lon nhat cua module; neu release gate con mutate state thi moi artifact readiness sau do deu yeu gia tri audit.
- Suggested implementation:
  - Bo `--sync` khoi `security:assert-action-permission-baseline` trong release gate.
  - Neu can remediation lane, tao command/option rieng explicit va khong cho phep trong readiness flow.
  - Them output ro `verify-only`.
- Affected files or layers:
  - `app/Console/Commands/RunReleaseGates.php`
  - `app/Console/Commands/RunProductionReadiness.php`
  - `tests/Feature/RunReleaseGatesCommandTest.php`
  - `tests/Feature/ProductionReadinessCommandTest.php`
- Tests required:
  - release gates khong truyen `--sync`
  - readiness khong auto-repair baseline
- Estimated effort: S
- Dependencies:
  - GOV
- Exit criteria:
  - Release gate chi verify, khong con mutate permission baseline.

# Critical fixes

## [TASK-OPS-002] [Ma hoa backup artifact va them manifest checksum]
- Based on issue(s): `OPS-002`, `OPS-005`
- Priority: Critical
- Objective:
  - Bao ve backup artifact chua PHI/finance va cho backup health co contract du lieu dang tin cay.
- Scope:
  - backup artifact command, backup health gate, artifact metadata/manifest.
- Why now:
  - Backup plaintext la risk compliance cao nhat cua module va la blocker production ro rang.
- Suggested implementation:
  - Tao `BackupArtifactService` sinh encrypted artifact + manifest JSON (checksum, size, format version, created_at, encryption state).
  - `ops:check-backup-health` doc manifest, check checksum/size > 0/encryption state.
- Affected files or layers:
  - `app/Console/Commands/CreateBackupArtifact.php`
  - `app/Console/Commands/CheckBackupHealth.php`
  - service layer moi cho backup/manifest
  - tests backup artifact/health
- Tests required:
  - encrypted artifact success path
  - zero-byte/manifest mismatch fail path
- Estimated effort: L
- Dependencies:
  - `TASK-OPS-001`
- Exit criteria:
  - Backup artifact khong con plaintext raw dump va health gate phu thuoc vao manifest hop le.

## [TASK-OPS-003] [Bien restore drill thanh restore sandbox that su]
- Based on issue(s): `OPS-003`
- Priority: Critical
- Objective:
  - Dam bao `restore drill` co y nghia nghiep vu: artifact backup thuc su co the duoc khoi phuc.
- Scope:
  - restore drill command/service, restore sandbox verification, checksum/manifest integration.
- Why now:
  - Sau khi co backup an toan, restore drill phai xac nhan recoverability thay vi copy file.
- Suggested implementation:
  - Tao `RestoreDrillService` restore vao sandbox sqlite/mysql temp, verify schema/toi thieu row contract, cleanup sau test.
  - Luu ket qua restore drill vao audit metadata co artifact id/checksum.
- Affected files or layers:
  - `app/Console/Commands/RunRestoreDrill.php`
  - service layer restore drill
  - release gate tests
- Tests required:
  - restore sandbox pass
  - import failure strict fail
- Estimated effort: L
- Dependencies:
  - `TASK-OPS-002`
- Exit criteria:
  - `ops:run-restore-drill` chi PASS khi restore sandbox thanh cong va smoke checks dat.

# High priority fixes

## [TASK-OPS-004] [Chuan hoa authorization va actor audit cho OPS commands]
- Based on issue(s): `OPS-004`
- Priority: High
- Objective:
  - Moi lenh control-plane quan trong deu co actor boundary va audit trail day du.
- Scope:
  - `ops:create-backup-artifact`, `ops:check-backup-health`, `ops:run-release-gates`, `ops:run-production-readiness`, `ops:verify-production-readiness-report`, `reports:explain-ops-hotpaths`.
- Why now:
  - Day la least-privilege va auditability baseline cho module cuoi.
- Suggested implementation:
  - Them `ActionGate::authorize()` hoac control-plane permission rieng.
  - Centralize actor resolution helper cho console commands va assert actor id duoc ghi vao audit.
- Affected files or layers:
  - OPS commands
  - `ActionGate`/automation actor support
  - tests auth matrix
- Tests required:
  - invalid actor fail
  - valid actor ghi audit actor id
- Estimated effort: M
- Dependencies:
  - GOV
- Exit criteria:
  - Khong con OPS command nhay cam nao chay voi `actor_id=null` trong path hop le.

## [TASK-OPS-005] [Rang buoc readiness signoff vao signer hop le va audit bat bien]
- Based on issue(s): `OPS-006`
- Priority: High
- Objective:
  - Bien signoff artifact thanh bang chung co traceability that su, khong phai free-text checklist.
- Scope:
  - verify readiness report command, signer validation, audit metadata.
- Why now:
  - Signoff la artifact cuoi truoc deploy; neu signer khong xac minh duoc thi module OPS van chua dat baseline.
- Suggested implementation:
  - Validate signer theo user/email noi bo + role/policy duoc phep.
  - Ghi immutable audit event cho moi signoff pass/fail.
- Affected files or layers:
  - `app/Console/Commands/VerifyProductionReadinessReport.php`
  - co the them signer resolver service
  - tests signoff validation
- Tests required:
  - signer invalid bi reject
  - signer hop le duoc accept va audit duoc ghi
- Estimated effort: M
- Dependencies:
  - `TASK-OPS-004`, GOV
- Exit criteria:
  - Signoff artifact map duoc den signer hop le va audit trail khong mo ho.

## [TASK-OPS-006] [Giam noise budget trong observability health]
- Based on issue(s): `OPS-007`
- Priority: High
- Objective:
  - Tach metric observability de release gate khong fail vi noise control-plane.
- Scope:
  - `CheckObservabilityHealth`, metric definition, runbook categories.
- Why now:
  - Sau khi verify/backup boundary dung, observability la gate cuoi can tin cay.
- Suggested implementation:
  - Chi dem `recent_automation_failures` theo whitelist channel/category hoac command contract.
  - Ghi ro metric lineage trong audit metadata.
- Affected files or layers:
  - `app/Console/Commands/CheckObservabilityHealth.php`
  - runtime settings budgets neu can
  - tests observability health
- Tests required:
  - noise channel khong bi dem
  - tracked channel van bi dem
- Estimated effort: M
- Dependencies:
  - KPI, INT, ZNS
- Exit criteria:
  - Observability error budget phan anh dung signal can gate release.

# Medium priority fixes

## [TASK-OPS-007] [Dong goi regression suite cho backup, restore, signoff va command auth]
- Based on issue(s): `OPS-008`
- Priority: Medium
- Objective:
  - Khoa regression cho cac boundary moi cua module OPS.
- Scope:
  - tests backup artifact, backup health, restore drill, release gates, readiness signoff, command auth.
- Why now:
  - OPS la module cuoi; neu khong co regression suite dung, baseline toan CRM se mong manh.
- Suggested implementation:
  - Bo sung test negative paths va weird paths sau khi cac task tren on dinh.
- Affected files or layers:
  - `tests/Feature/*Ops*`
  - `tests/Feature/*Backup*`
  - `tests/Feature/*Readiness*`
- Tests required:
  - chinh task nay la regression backlog
- Estimated effort: M
- Dependencies:
  - `TASK-OPS-001` -> `TASK-OPS-006`
- Exit criteria:
  - Module OPS co regression suite bao du verify-only, backup encryption, restore sandbox, signoff auth va observability scope.

# Low priority fixes

- Chua de xuat low-priority truoc khi dong xong baseline verify/backup/restore/signoff.

# Testing & regression protection

- Feature test: release gate khong auto `--sync`
- Feature test: backup artifact duoc ma hoa va co manifest
- Feature test: backup health fail voi artifact rong/manifest sai
- Feature test: restore drill strict fail khi khong import duoc sandbox
- Feature test: OPS command auth matrix voi automation actor khong hop le
- Feature test: signoff reject signer khong hop le
- Feature test: observability health chi dem failure channel duoc track

# Re-audit checklist

- Release gates da tro thanh verify-only chua
- Backup artifact da co encryption/manfest va khong con raw dump plaintext chua
- Restore drill da verify recoverability that su chua
- OPS commands nhay cam da co actor boundary va audit day du chua
- Signoff da map duoc den signer hop le chua
- Observability budget da giam noise chua
- Full suite xanh sau khi fix OPS chua

# Execution order

1. `TASK-OPS-001`
2. `TASK-OPS-002`
3. `TASK-OPS-003`
4. `TASK-OPS-004`
5. `TASK-OPS-005`
6. `TASK-OPS-006`
7. `TASK-OPS-007`

# What can be done in parallel

- `TASK-OPS-004` co the chuan bi song song voi `TASK-OPS-002` sau khi verify-only contract da ro.
- `TASK-OPS-007` co the duoc viet dan song song theo tung task sau khi API/command contract on dinh.

# What must be done first

- `TASK-OPS-001` phai lam truoc vi release gate mutation dang pha vo audit semantics cua toan module.
- `TASK-OPS-002` phai xong truoc `TASK-OPS-003` vi restore drill nen dua tren artifact contract moi.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-OPS-001`
  - `TASK-OPS-004`
- Milestone 2:
  - `TASK-OPS-002`
  - `TASK-OPS-005`
- Milestone 3:
  - `TASK-OPS-003`
  - `TASK-OPS-006`
- Milestone 4:
  - `TASK-OPS-007`
  - re-audit
