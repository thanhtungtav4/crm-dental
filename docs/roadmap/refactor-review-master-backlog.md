# Refactor and Review Master Backlog

Tai lieu nay la backlog canonical cho phase sau baseline. Chi bao gom cong viec con mo sau khi `13/13` module da dat `Clean Baseline Reached`.

## Metadata

- Scope: cong viec follow-up sau baseline hardening
- Source of truth:
  - `docs/reviews/program-audit-summary.md`
  - `docs/reviews/00-master-index.md`
  - `docs/reviews/modules/*.md`
  - `docs/reviews/issues/*.md`
  - `docs/reviews/plans/*.md`
- Last updated: `2026-03-28`

## Working Rules

- Khong lap lai issue baseline da `Resolved`.
- Neu mot van de la cross-module, backlog chi giu 1 item canonical va link module lien quan.
- Moi batch implementation phai di kem test/smoke test toi thieu dung scope.
- Khong push/merge mot loat len `main` khi chua chot phase rollout va rollback plan.

## Quick Wins

## [RRB-001] Production migration and backfill rollout wave

- Status: `Completed`
- Module: `GOV`, `CARE`, `INV`, `SUP`, `INT`, `ZNS`
- Description:
  - Chay toan bo migration/backfill/schema gate con lai tren moi truong that va xac nhan du lieu sau rollout khong drift.
- Impact:
  - Dong khoang cach giua code baseline va data/DB baseline.
- Priority: `P0`
- Risk: `High`
- Effort estimate: `L`
- Dependency:
  - none
- Recommended direction:
  - Tach rollout thanh wave nho theo module/dependency edge.
  - Chay preflight backup, migrate, post-migrate verification, va rollback checklist cho tung wave.
- Tests needed:
  - pre/post data verification
  - migrate + smoke test module-specific
  - schema gate cho inventory/governance
- Rollout note:
  - Khong gom tat ca migration vao mot lan deploy; uu tien `GOV -> CARE -> SUP/INV -> INT/ZNS`.

## [RRB-002] Real-infrastructure smoke-test wave for control-plane and async systems

- Status: `Completed`
- Module: `APPT`, `INT`, `ZNS`, `KPI`, `OPS`, `CARE`
- Description:
  - Chay smoke test tren he thong that cho queue workers, control-plane commands, backup/restore, readiness gates, provider rotations, campaign run/prune, va snapshot/export.
- Impact:
  - Giam rui ro van hanh ma test local/full-suite khong mo phong het duoc.
- Priority: `P0`
- Risk: `High`
- Effort estimate: `M`
- Dependency:
  - `RRB-001`
- Recommended direction:
  - Chuan hoa smoke test pack theo tung lane:
    - async queue lane
    - provider/integration lane
    - report/snapshot lane
    - backup/restore lane
- Tests needed:
  - queue worker health verification
  - backup restore drill
  - readiness signoff drill
  - live provider smoke test voi sandbox credentials neu co
- Rollout note:
  - Chay ngoai khung deploy chinh neu provider/backup operations co side effect.

## [RRB-003] Governance delegation matrix and baseline permission review

- Status: `Completed`
- Module: `GOV`
- Description:
  - Formalize ma tran role/page/action sau baseline de tranh drift permission khi mo rong he thong.
- Impact:
  - Giam kha nang mo lai lỗ hong auth/scope khi them resource/page moi.
- Priority: `P1`
- Risk: `Medium`
- Effort estimate: `M`
- Dependency:
  - `RRB-001`
- Recommended direction:
  - Chot tai lieu delegation matrix cho `Admin`, `Manager`, `Doctor`, `Assistant`, `CSKH`, `Finance`, `AutomationService`.
  - Link matrix nay vao seeder/assert commands va review checklist.
- Tests needed:
  - permission matrix regression
  - page access matrix spot checks
- Rollout note:
  - Mọi doi baseline permission can di kem SOP van hanh va dry-run review.

## [RRB-004] Consent and imaging operator UX polish

- Status: `Completed`
- Module: `CLIN`, `TRT`, `INT`
- Description:
  - Nang cap UX ky consent, imaging upload, helper text, retry guidance, va read-preserving clinical surfaces.
- Impact:
  - Giam operator confusion trong flow clinical co do nhay cam cao.
- Priority: `P1`
- Risk: `Low`
- Effort estimate: `M`
- Dependency:
  - `RRB-002`
- Recommended direction:
  - Bien consent actions thanh guided operator flow.
  - Them helper text va retry guidance cho media/X-ray lon.
  - Giam raw PII/PHI tren list view, uu tien detail/restricted reveal.
- Tests needed:
  - browser/feature tests cho consent actions
  - upload validation and retry feedback tests
- Rollout note:
  - Batch nay co the deploy rieng, rui ro thap neu co test giao dien dung scope.

## [RRB-005] MPI, lead conversion, and care queue operator clarity

- Status: `Completed`
- Module: `PAT`, `CARE`, `APPT`
- Description:
  - Lam ro messaging cho convert/dedupe flow, MPI queue, va care queue states de operator khong thao tac sai doi tuong.
- Impact:
  - Giam ticket nham, convert nham, va khieu nai do UI thong bao mo ho.
- Priority: `P1`
- Risk: `Low`
- Effort estimate: `M`
- Dependency:
  - `RRB-001`
- Recommended direction:
  - Hien outcome ro rang cho convert lead -> patient.
  - Them aging/SLA cues cho MPI queue.
  - Lam ro status/owner semantics tren care queue.
- Tests needed:
  - feature/browser tests cho operator messaging
  - queue/read-model visibility checks
- Rollout note:
  - Uu tien lam sau khi du lieu migration/backfill da on dinh.

## [RRB-006] Queue observability and triage runbook pack

- Status: `Completed`
- Module: `APPT`, `CARE`, `ZNS`, `INT`, `OPS`
- Description:
  - Dong goi log, metric, queue state, retry/dead-letter, va runbook triage thanh mot operational pack de de theo doi sau deploy.
- Impact:
  - Giam MTTR cho async/control-plane incidents.
- Priority: `P1`
- Risk: `Medium`
- Effort estimate: `M`
- Dependency:
  - `RRB-002`
- Recommended direction:
  - Chuan hoa dashboard/read-model hoac toi thieu checklist triage cho mail, ZNS, appointment orchestration, va provider rotations.
- Tests needed:
  - command/runbook dry runs
  - smoke tests cho retry/dead-letter/readiness alerts
- Rollout note:
  - Co the lam song song voi batch UX vi chu yeu la van hanh/read-model.

## Medium Refactors

## [RRB-007] Shared hashed identity and search contract

- Status: `Completed`
- Module: `PAT`, `APPT`, `CARE`, `INT`
- Description:
  - Rut gon cac pattern hash search cho phone/email/identity thanh contract chung de tranh drift giua lead, customer, patient, va care surfaces.
- Impact:
  - Giam duplicate logic va regression khi thay doi search/normalization strategy.
- Priority: `P2`
- Risk: `Medium`
- Effort estimate: `M`
- Dependency:
  - `RRB-005`
- Recommended direction:
  - Trich xuat normalizer/search helper va reuse o form filters, API ingestion, va automation paths.
- Tests needed:
  - shared dataset tests cho normalization/hash lookup
  - regression tests cho APPT/CARE/INT hot paths
- Rollout note:
  - Lam theo batch nho, khong thay doi strategy ma khong co test o moi surface hot path.

## [RRB-008] Shared actor and branch scope contract

- Status: `Completed`
- Module: `GOV`, `PAT`, `APPT`, `CLIN`, `TRT`, `FIN`, `INV`, `SUP`, `CARE`
- Description:
  - Rut gon cac authorizer/scope helper thanh mot contract on dinh cho actor scope, branch scope, selector scope, va forged-payload sanitize.
- Impact:
  - Giam drift scope logic giua module va giam chi phi review khi them form/table/page moi.
- Priority: `P2`
- Risk: `Medium`
- Effort estimate: `L`
- Dependency:
  - `RRB-003`
- Recommended direction:
  - Xac dinh mot tap helper chung cho:
    - accessible branch ids
    - scoped selector options
    - server-side actor sanitize
    - record ownership assertions
- Tests needed:
  - contract tests cho accessible branches
  - spot regression tests o module tieu bieu
- Rollout note:
  - Refactor nay nen lam tung module mot, khong boi lai tat ca trong mot commit.

## [RRB-009] Shared workflow action and audit-reason contract

- Status: `In progress`
- Module: `APPT`, `TRT`, `FIN`, `SUP`, `ZNS`, `CARE`, `OPS`
- Description:
  - Chuan hoa pattern workflow service, guided actions, confirm modal, va audit reason metadata tren cac module co state machine.
- Impact:
  - Giam drift UX va drift domain boundary giua cac workflow module.
- Priority: `P2`
- Risk: `Medium`
- Effort estimate: `L`
- Dependency:
  - `RRB-008`
- Recommended direction:
  - Dinh nghia checklist/workflow contract chung cho:
    - form khong sua raw status
    - page/table actions di qua service canonical
    - ly do, actor, timestamp duoc ghi nhat quan
  - Tien do hien tai:
    - da chuan hoa `WorkflowAuditMetadata` tren `APPT`, `TRT`, `FIN`, `SUP`, `ZNS`
    - da bo sung audit trail / structured transition metadata cho `FactoryOrder`
    - da keo observer-based workflow audit cua `Appointment`, `Consent`, `InsuranceClaim`, `TreatmentSession` gan hon ve contract chung
    - da dua `PopupAnnouncement` vao workflow service canonical, guided actions, va audit-reason metadata tren nhanh backlog
    - da dua `PlanItem` mutation lane vao workflow service canonical, guided actions, va audit transition metadata tren nhanh backlog
    - `CARE` va `OPS` chua can lane refactor rieng trong wave nay
- Tests needed:
  - workflow transition contract tests
  - browser checks cho dangerous/destructive actions
- Rollout note:
  - Nho batch theo workflow module, uu tien APPT/TRT/FIN truoc.

## [RRB-010] Unified audit timeline and read-model conventions

- Status: `In progress`
- Module: `GOV`, `CLIN`, `FIN`, `INT`, `OPS`, `ZNS`
- Description:
  - Tiep tuc hop nhat cach doc audit timeline, operational event, va provenance read-model de de trace incident va nghiep vu.
- Impact:
  - Giam manh mun audit trail va tang kha nang forensic/debugging.
- Priority: `P2`
- Risk: `High`
- Effort estimate: `L`
- Dependency:
  - `RRB-003`
  - `RRB-009`
- Recommended direction:
  - Dung approach giong `ClinicalAuditTimelineService` lam tham chieu.
  - Tach event writer voi event reader de khong rang buoc UI vao table raw.
  - Tien do hien tai:
    - da tao `PatientOperationalTimelineService`
    - da tach patient activity timeline khoi widget query thuan raw
    - da dua `FactoryOrder`, `InsuranceClaim`, `TreatmentSession` vao patient operational timeline read-model
    - da dua them `PlanItem` vao patient operational timeline read-model
    - cac audit metadata moi da du structured hon de phuc vu read-model phase sau
- Tests needed:
  - timeline reader tests
  - authorization tests cho audit/read-model surfaces
- Rollout note:
  - Uu tien doc-model truoc, chua rewrite he thong ghi audit trong mot lan.

## High-Risk Structural Refactors

## [RRB-011] Immutable adjustment and reversal ledger strategy

- Module: `TRT`, `INV`, `FIN`, `SUP`
- Description:
  - Chot chien luoc append-only/immutable cho usage, void, reversal, cancel, va supplier-linked adjustments de giam drift downstream.
- Impact:
  - Day la refactor lon nhat cho data integrity giua treatment, kho, tai chinh, va labo.
- Priority: `P2`
- Risk: `High`
- Effort estimate: `XL`
- Dependency:
  - `RRB-008`
  - `RRB-009`
  - `RRB-010`
- Recommended direction:
  - Khong dua ve delete/update mutable cho record da phat sinh side effect.
  - Uu tien event/adjustment model va read-model reconciliation.
- Tests needed:
  - end-to-end reconciliation suite
  - concurrency tests
  - rollback and reversal semantics tests
- Rollout note:
  - Can canary theo domain edge, khong rewrite cung luc TRT/INV/FIN/SUP.

## [RRB-012] Reporting and snapshot platform convergence

- Module: `KPI`, `OPS`, `FIN`, `INV`, `CARE`, `SUP`
- Description:
  - Tiep tuc tach report pages khoi ad-hoc query logic, chot read-model/snapshot contract, freshness policy, export scope, va query performance lane.
- Impact:
  - Giam drift report va chi phi maintain khi dataset tang.
- Priority: `P2`
- Risk: `High`
- Effort estimate: `L`
- Dependency:
  - `RRB-002`
  - `RRB-008`
  - `RRB-010`
- Recommended direction:
  - Tiep tuc mo rong `BaseReportPage`/snapshot contracts.
  - Chot query-shape review, export scope, va freshness SLA o cap platform.
- Tests needed:
  - report scope/export tests
  - performance/explain checks cho heavy paths
  - production-like dataset smoke tests
- Rollout note:
  - Moi toi uu query can di kem measurement va feature flag neu co.

## [RRB-013] Integration control-plane platform convergence

- Module: `INT`, `ZNS`, `OPS`
- Description:
  - Tiep tuc tach ro control-plane health, runtime settings, secret rotation, payload governance, retry/dead-letter, va provider-specific runbooks thanh mot platform nhat quan.
- Impact:
  - Giam drift van hanh, giam cognitive load khi xu ly incident integration.
- Priority: `P2`
- Risk: `High`
- Effort estimate: `L`
- Dependency:
  - `RRB-002`
  - `RRB-006`
  - `RRB-010`
- Recommended direction:
  - Dinh nghia lane rieng cho:
    - config/runtime settings
    - secret rotation/grace windows
    - payload retention/pruning
    - provider run/retry/revoke
- Tests needed:
  - integration contract tests
  - lock/retry/runbook smoke tests
- Rollout note:
  - Nhat thiet can co sandbox credential, rollback drill, va deploy window ro rang.

## Suggested Order

1. `RRB-001`
2. `RRB-002`
3. `RRB-003`
4. `RRB-004`, `RRB-005`, `RRB-006`
5. `RRB-007`, `RRB-008`
6. `RRB-009`, `RRB-010`
7. `RRB-011`, `RRB-012`, `RRB-013`
