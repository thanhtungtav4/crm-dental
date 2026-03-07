# Metadata

- Module code: `OPS`
- Module name: `Production readiness / backup / observability`
- Current status: `In Fix`
- Current verdict: `D`
- Review file: `docs/reviews/modules/OPS-production-readiness.md`
- Issue file: `docs/issues/OPS-issues.md`
- Plan file: `docs/planning/OPS-plan.md`
- Issue ID prefix: `OPS-`
- Task ID prefix: `TASK-OPS-`
- Dependencies: `GOV, INT, KPI, ZNS, FIN, INV`
- Last updated: `2026-03-07`

# Scope

- Review module `OPS` theo 4 lop: architecture, database, domain logic, UI/UX.
- Pham vi review tap trung vao:
  - scheduler wrapper va automation actor boundary
  - backup artifact, backup health, restore drill
  - release gates, production readiness report, signoff artifact
  - observability health va runbook readiness
  - hot-path explain baseline cho KPI/CARE/APPT/FIN

# Context

- `OPS` la module chot release va safety net cho toan CRM sau khi cac module nghiep vu da dat baseline.
- Day khong phai module UI-heavy; phan lon blast radius nam o command/runtime pipeline, artifact backup va release-go-live checklist.
- `OPS` hien tai da co nhieu command va test, nhung con thieu mot so boundary quan trong de duoc xem la production-grade trong boi canh du lieu y te nhay cam.
- Thong tin con thieu lam giam do chinh xac review:
  - chua co mo ta chinh sach backup encryption/KMS tren ha tang that
  - chua co RPO/RTO chinh thuc va quy trinh restore runbook ngoai code
  - chua co ma tran role chinh thuc cho ai duoc ky QA/PM signoff production

# Executive Summary

- Muc do an toan hien tai: `Trung binh -> Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Chua dat baseline`
- Cac canh bao nghiem trong:
  - `ops:run-release-gates` dang goi `security:assert-action-permission-baseline --sync`, bien release gate thanh lenh mutating state ngay truoc deploy.
  - `ops:create-backup-artifact` tao dump plaintext vao `storage/app/backups`, khong co encryption/manfest checksum cho artifact backup.
  - `ops:run-restore-drill` chi copy file va so checksum, chua verify kha nang restore that su.

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - `app/Console/Commands/RunReleaseGates.php:127-130`
  - `app/Console/Commands/CreateBackupArtifact.php:184-199`
  - `app/Console/Commands/RunProductionReadiness.php:26-165`
  - `app/Console/Commands/VerifyProductionReadinessReport.php:23-175`
- Findings:
  - Release gate dang include buoc mutating permission baseline bang `--sync`; release verification khong nen sua he thong trong luc gate dang chay.
  - Backup artifact duoc ghi ra dang plaintext `.sql/.bak` vao local storage, rat yeu ve mat bao mat voi du lieu PHI/finance.
  - Nhieu OPS command quan trong chua qua `ActionGate::authorize()`: `ops:create-backup-artifact`, `ops:check-backup-health`, `ops:run-release-gates`, `ops:run-production-readiness`, `ops:verify-production-readiness-report`, `reports:explain-ops-hotpaths`. Ket qua la actor audit co the `null` va boundary authorization khong dong nhat.
  - Signoff artifact chi nhan `qa`/`pm` free-text, khong rang buoc signer vao user/role hop le.
- Suggested direction:
  - Tach gate verify ra khoi remediation mutation.
  - Ma hoa artifact backup + manifest checksum.
  - Bat buoc authorization va actor audit cho moi OPS command co gia tri van hanh.
  - Chuan hoa signoff theo user id / role / audit event bat bien.

## Data Integrity & Database

- Danh gia: `Trung binh`
- Evidence:
  - `app/Console/Commands/CheckBackupHealth.php:24-43`
  - `app/Console/Commands/RunRestoreDrill.php:49-65`
  - `tests/Feature/BackupArtifactCommandTest.php:8-80`
  - `tests/Feature/RestoreDrillCommandTest.php:5-24`
- Findings:
  - Backup health gate chi check existence + age cua file moi nhat; khong verify size > 0, checksum manifest, hay valid dump shape.
  - Restore drill khong tao restore sandbox, khong import dump, khong verify schema/connection sau restore. Ve ban chat day moi la copy drill, khong phai restore drill.
  - `audit_logs` van la noi ghi nhan control-plane log cua OPS, nhung cac command OPS khong nhat quan ve actor context.
- Suggested direction:
  - Them backup manifest metadata va health rule dua tren `size/checksum/encryption/version` thay vi chi `mtime`.
  - Doi restore drill thanh workflow co restore sandbox va smoke check sau restore.
  - Ghi audit trail co actor, artifact id, checksum, encryption state ro rang.

## Concurrency / Race-condition

- Danh gia: `Trung binh`
- Evidence:
  - `routes/console.php:15-26`
  - `app/Support/ClinicRuntimeSettings.php:1270-1274`
  - `app/Console/Commands/CreateBackupArtifact.php:39-40`
  - `app/Console/Commands/RunProductionReadiness.php:33-53`
- Findings:
  - Scheduler wrapper da co `withoutOverlapping()` va `onOneServer()`, day la diem tot.
  - Tuy nhien artifact backup va readiness report dung timestamp den giay trong ten file; neu bi trigger song song bang tay, collision van co the xay ra.
  - Release/readiness pipeline chua co idempotency token hoac lock rieng cho manual run.
- Suggested direction:
  - Them lock/idempotency cho manual OPS run quan trong.
  - Dung ten artifact co suffix UUID hoac sequence.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Console/Commands/ExplainOpsHotpaths.php:20-132`
  - `app/Console/Commands/CheckObservabilityHealth.php:41-72`
  - `tests/Feature/OpsHotpathExplainStrictSlaTest.php`
- Findings:
  - Co baseline EXPLAIN va benchmark p95 cho hot-path, day la huong tot.
  - `CheckObservabilityHealth` dem `recent_automation_failures` bang tat ca `audit_logs` co `entity_type=automation` va `action=fail` trong cua so thoi gian; metric nay tron lan alert/control-plane failure voi failure nghiep vu thuc, de gay noise budget.
  - Chua co datasource rieng cho backup/restore artifact metadata nen quan sat runtime control-plane con phu thuoc nhieu vao text log + filesystem.
- Suggested direction:
  - Tach observability metrics theo channel/category.
  - Them storage metadata cho backup artifact va readiness report.

## Maintainability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Console/Commands/RunReleaseGates.php`
  - `app/Console/Commands/RunProductionReadiness.php`
  - `app/Console/Commands/VerifyProductionReadinessReport.php`
- Findings:
  - Module co nhieu command duoc test, nhung boundary giua `verify`, `remediate`, `backup`, `restore`, `signoff` chua tach dep.
  - `RunReleaseGates`, `RunProductionReadiness`, `VerifyProductionReadinessReport` dang gop ca orchestration, persistence va reporting trong mot command lon.
- Suggested direction:
  - Tach `ReleaseGatePlanBuilder`, `ProductionReadinessReportService`, `BackupArtifactService`, `RestoreDrillService`.

## Better Architecture Proposal

- Tach `verify` va `remediate` thanh 2 lane rieng:
  - `verify` command khong bao gio mutate state
  - `repair/sync` command chay rieng, explicit
- Dua backup/restore sang canonical service layer:
  - `BackupArtifactService`
  - `BackupManifestService`
  - `RestoreDrillService`
- Chuan hoa OPS audit trail:
  - moi command quan trong deu qua `ActionGate`
  - moi artifact co `artifact_id`, `checksum`, `encryption_state`, `actor_id`
- Refactor readiness workflow:
  - run gate -> persist report -> verify signoff -> immutable audit

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Kem`
- OPS workflow hien tai gom:
  - scheduler wrapper chay automation
  - tao backup artifact
  - kiem tra backup health
  - copy-based restore drill
  - release gates
  - production readiness report + signoff
- Workflow nay co day du buoc, nhung logic verify va remediation dang bi tron.

## State transitions

- Danh gia: `Trung binh`
- Co mot so transition ro:
  - release gate `pass/fail`
  - observability `healthy/unhealthy`
  - backup artifact `success/failed`
  - restore drill `pass/fail`
- Tuy nhien:
  - signoff artifact khong co state machine dung nghia
  - backup artifact khong co `created -> encrypted -> verified -> restorable`
  - release gate khong phan biet `assert-only` va `repair-before-pass`

## Missing business rules

- Thieu rule `release gate khong duoc mutate state production`.
- Thieu rule `backup artifact bat buoc duoc encrypt/manfested`.
- Thieu rule `restore drill phai verify restore thanh cong tren sandbox`.
- Thieu rule `signoff QA/PM phai map duoc den user/role hop le`.
- Thieu rule `OPS command quan trong bat buoc co actor audit`.

## Invalid states / forbidden transitions

- Release gate co the `PASS` sau khi vua tu dong `sync` lai permission baseline trong cung mot lan run, lam mo audit line giua he thong truoc va sau verify.
- Backup health co the `healthy` voi file moi nhat hop extension nhung khong du tin cay ve noi dung.
- Restore drill co the `pass` du backup dump khong import duoc, vi chi can copy + checksum khop.
- Signoff co the `pass` du signer chi la string bat ky.

## Service / action / state machine / transaction boundary de xuat

- Tao `ReleaseGateAuthorizationService` + `ReleaseGatePlanBuilder`.
- Tao `BackupArtifactService` va `BackupArtifactManifest` de control artifact lifecycle.
- Tao `RestoreDrillService` voi sandbox restore + smoke check.
- Tao `ProductionReadinessSignoffService` de xac thuc signer va persist immutable signoff audit.

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Operator flow hien tai la CLI-first, co report JSON va signoff JSON.
- Diem tot:
  - output de doc
  - co dry-run cho release gate va production readiness
  - co test baseline cho command output
- Diem yeu:
  - khong co control-plane UI/read model de xem backup artifacts, restore drill lich su, signoff lich su
  - runbook/map/readiness evidence van phan tan giua filesystem va audit logs

## Filament UX

- Danh gia: `Kem`
- Module `OPS` gan nhu khong co page UI rieng cho van hanh; operator phai dua vao CLI artifact va filesystem.
- Cho production-grade, day la friction cao cho owner khong ky thuat.

## Edge cases quan trong

- release gate dang chay thi permission baseline drift va command tu dong `sync`
- backup moi nhat la file rong hoac file plaintext khong hop le nhung van duoc chon lam latest
- restore drill pass du dump bi hong ve mat import
- 2 manual `ops:run-production-readiness` chay cung luc vao cung `report` path
- signer QA/PM nhap sai ten/email nhung van tao signoff artifact
- observability health fail do noise tu control-plane alert thay vi issue runtime thuc te

## Diem de thao tac sai

- `--strict` va `--dry-run` output de doc, nhung khong noi ro command nao dang mutating state.
- backup va readiness artifact luu tren filesystem path free-form, de operator ghi de artifact cu.
- signoff dung free-text signer, de sai traceability.

## De xuat cai thien UX

1. Them dashboard/read model cho backup artifacts, restore drill history, readiness reports.
2. Hien ro command la `verify-only` hay `repair/mutate` ngay trong output.
3. Signoff nen dung user id / email da xac thuc, khong nhap text tu do.
4. Them checksum/encryption status trong output backup health va restore drill.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| OPS-001 | Critical | Security | Release gates dang mutate permission baseline bang `--sync` | Resolved | Release gate da tro thanh verify-only, khong con mutate baseline trong lane readiness. |
| OPS-002 | Critical | Security | Backup artifact duoc tao dang plaintext, khong co encryption/manfest | In Fix | Command tao artifact da chuyen sang encrypted payload + manifest; can chot backup health/restore flow quanh contract moi. |
| OPS-003 | High | Domain Logic | Restore drill chi copy file, chua verify restore that | Open | `pass` hien tai chua chung minh recoverability. |
| OPS-004 | High | Security | OPS commands quan trong chua qua ActionGate va actor audit khong dong nhat | In Fix | Release gates/readiness/backup commands da co actor boundary; can chot not yet covered commands va regression. |
| OPS-005 | High | Data Integrity | Backup health gate chi check age, khong check size/checksum/manifest | Open | File moi nhat hop extension chua du de goi la healthy. |
| OPS-006 | Medium | Maintainability | Readiness signoff chua rang buoc signer vao user/role hop le | Open | QA/PM signoff hien la free-text, traceability yeu. |
| OPS-007 | Medium | Performance | Observability health gom nham moi automation fail vao cung budget | Open | Noise budget co the cao hon thuc te. |
| OPS-008 | Medium | Maintainability | Regression suite chua khoa cac edge case backup encryption/signoff/authorization | Open | Test hien tai cover happy path nhieu hon blast-radius path. |

# Dependencies

- GOV cho action permissions, automation actor governance va audit policy.
- INT va ZNS cho integration retention/runbook coupling.
- KPI cho snapshot SLA va hot-path explain baseline.
- FIN, INV cho release gates co lien quan finance reconciliation/schema drift.

# Open Questions

- Backup artifact tren production co duoc ma hoa boi ha tang/KMS ngoai code khong, hay can enforce trong ung dung?
- RPO/RTO muc tieu cua he thong la bao nhieu, va restore drill can dat muc nao de duoc goi la pass?
- QA/PM signer co can map vao `users` trong he thong hay duoc phep dung external approver registry?

# Recommended Next Steps

1. Chot batch `TASK-OPS-002`, rerun regression va commit.
2. Uu tien `TASK-OPS-003` va phan con lai cua `TASK-OPS-004` de bien backup/restore va command auth thanh contract co the tin cay.
3. Sau khi dong `OPS`, chay re-audit tong va full suite de chot baseline toan CRM.

# Current Status

- In Fix
