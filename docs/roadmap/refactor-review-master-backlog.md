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
- Module: `PAT`, `APPT`, `TRT`, `FIN`, `SUP`, `INT`, `ZNS`, `CARE`, `OPS`
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
    - da dua `Appointment` vao managed workflow context trong `AppointmentSchedulingService`, model guard raw status update, va observer audit hop nhat cho `reschedule` / `cancel` / `no_show` / `complete`
    - da dua `VisitEpisode` vao canonical `VisitEpisodeService`, model guard raw status update, va chot smoke flow appointment-to-encounter theo workflow contract
    - da dua `BranchTransferRequest` vao managed workflow context trong `PatientBranchTransferService`, model guard raw status update, va observer audit cho `request` / `apply` / `reject`
    - da keo observer-based workflow audit cua `Consent`, `InsuranceClaim`, `TreatmentSession` gan hon ve contract chung
    - da dua `PopupAnnouncement` vao workflow service canonical, guided actions, va audit-reason metadata tren nhanh backlog
    - da dua `PlanItem` mutation lane vao workflow service canonical, guided actions, va audit transition metadata tren nhanh backlog
    - da dua `ReceiptExpense` mutation lane vao workflow service canonical, model guard, guided actions, va structured audit metadata tren nhanh backlog
    - da dua `MaterialIssueNote` mutation lane vao workflow service canonical, model guard, guided actions, va transition audit metadata tren nhanh backlog
    - da dua `InsuranceClaim` vao workflow service canonical, managed transition context, va audit metadata `reason` / `trigger` / `payment_id`
    - da dua `ClinicalOrder` / `ClinicalResult` vao workflow service canonical, managed transition context, va EMR audit metadata `reason` / `trigger`
    - da dua `CareTicket/Note` vao workflow service canonical cho manual vs canonical status transitions, model guard raw status update, va managed audit metadata `reason` / `trigger`
    - da dua `Consent` vao managed workflow context trong lifecycle service, model guard raw status update, va audit metadata `trigger` / `signature_source`
    - da dua `ExamSession / TreatmentProgress` vao workflow service canonical cho clinical-note sync, treatment-progress prime, lifecycle refresh, va managed mutation cho `TreatmentProgressDay / TreatmentProgressItem`; model guard raw status update, va loai bo `saveQuietly()` doi `status` rai rac
    - da dua `InstallmentPlan` vao lifecycle service canonical, giu model facade `syncFinancialState()` cho backward compatibility, va keo `PaymentObserver`, `installments:run-dunning`, `installments:sync-status` qua cung lifecycle contract
    - da chuan hoa them `Payment` refund / reversal audit metadata theo pattern `reason` / `trigger` / canonical identifiers
    - da dua `MasterPatientDuplicate / MasterPatientMerge` vao workflow contract voi model guard raw status update, canonical workflow service cho `ignore`, `merge-resolution`, `rollback restore`, va duong `auto-ignore` tu `MasterPatientIndexService`
    - da dua `WebLeadEmailDelivery` vao managed workflow contract voi model guard raw status update, canonical resend / claim / sent / fail mutation methods, va giu service mail noi bo di qua canonical path
    - da dua `ZnsCampaignDelivery` vao managed workflow contract voi model guard raw status update, canonical sent / fail mutation methods, va loai bo stale-processing mass-update bypass trong `ZnsCampaignRunnerService`
    - da dua cac lane outbox noi bo `ZnsAutomationEvent`, `GoogleCalendarSyncEvent`, `EmrSyncEvent` vao transition contract, canonical replay methods, va loai bo stale-processing mass-update bypass
    - `OPS` chua can lane refactor rieng trong wave nay
- Tests needed:
  - workflow transition contract tests
  - browser checks cho dangerous/destructive actions
- Rollout note:
  - Nho batch theo workflow module, uu tien APPT/TRT/FIN truoc.

## [RRB-010] Unified audit timeline and read-model conventions

- Status: `In progress`
- Module: `PAT`, `GOV`, `CLIN`, `FIN`, `INT`, `OPS`, `ZNS`
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
    - da dua `BranchTransferRequest` vao patient operational timeline read-model
    - da dua them `PlanItem` vao patient operational timeline read-model
    - da dua them `ReceiptExpense` vao patient operational timeline read-model
    - da dua them `MaterialIssueNote` vao patient operational timeline read-model
    - da giu `CareTicket` tren audit contract moi ma khong lam vo patient activity / operational timeline surfaces
    - da lam ro them mo ta finance timeline cho `Payment` refund / reversal
    - cac audit metadata moi da du structured hon de phuc vu read-model phase sau
    - da tao `OperationalKpiSnapshotReadModelService` de gom read/query convention cho `OperationalKpiPack` va `OpsControlCenter` quanh latest snapshot date, SLA counts, filtered latest snapshot, va branch benchmark summary
    - da tao `OperationalKpiAlertReadModelService` de gom open/resolved alert counts va open-alert summary cho `OperationalKpiPack` va `OpsControlCenter`, giam query logic KPI alert dang bi rai rac
    - da mo rong `OperationalKpiSnapshotReadModelService` va `OperationalKpiAlertReadModelService` sang `ops:check-observability-health` de alert count va snapshot SLA violation count dung chung read-model contract giua page/service/command
    - da keo `OperationalKpiAlertService` dung chung reader contract cho active-alert counting sau snapshot evaluation, tranh viec write-side va read-side tu dien giai KPI alert theo hai cach khac nhau
    - da tao `OperationalAutomationAuditReadModelService` de hop nhat tracked command/channel catalog, recent failure count, va recent ops runs giua `OpsControlCenterService` va `CheckObservabilityHealth`
    - da tao `ReportSnapshotReadModelService` de hop nhat generic snapshot lookup cho `CheckSnapshotSla` va `CompareReportSnapshots`, trong khi van giu semantics tach rieng giua `global snapshot` va `across branches`
    - da tao `PatientActivityTimelineReadModelService` de hop nhat relation-backed patient activity entries voi `PatientOperationalTimelineService` va `ClinicalAuditTimelineService`, dong thoi dua `PatientActivityTimelineWidget` ve read-only rendering thay vi tu query va map du lieu truc tiep
    - da tao `GovernanceAuditReadModelService` de gom recent governance-relevant audit query va branch-visible filtering cho `OpsControlCenterService`, loai bo them mot raw audit query khoi cockpit service
    - da tao `ZnsOperationalReadModelService` de gom summary-card counts, dead/retry backlog, va retention candidate rules cho `OpsControlCenterService`, `CheckObservabilityHealth`, va `zns:prune-operational-data`, bao gom ca `automation log retention`, thay cho viec page/command tu query status ZNS theo tung chot rieng le
    - da tao `IntegrationOperationalReadModelService` de gom web-lead retention candidates, lead-mail retry/dead backlog, webhook retention, EMR retention, va Google Calendar retention counts cho `OpsControlCenterService`, `ops:check-observability-health`, va `integrations:prune-operational-data`, thay cho viec cockpit/command tu query tung model integration rieng le
    - da mo rong them `IntegrationOperationalReadModelService` va `ZnsOperationalReadModelService` sang cac lenh `sync-emr`, `sync-google-calendar`, `sync-zns` cho dead-letter backlog count scoped, giam them query raw khoi sync commands
    - da dua them grace-state reader cho integration secrets vao `IntegrationOperationalReadModelService`, va `OpsControlCenterService` / `IntegrationSettings` da bat dau doc active-expired grace rotations qua reader thay vi tro truc tiep vao write-side rotation service
    - da mo rong `ZnsOperationalReadModelService` them branch-scoped summary cards, va page `ZaloZns` da bat dau dung reader nay cho automation/delivery/campaign failure summary thay vi tu dem query raw tren page
    - da tao `IntegrationSettingsAuditReadModelService` de gom recent setting-log query, va page `IntegrationSettings` da doc recent audit trail qua reader nay thay vi tu query raw `ClinicSettingLog`
    - da tao `IntegrationProviderHealthReadModelService` de hop nhat provider readiness contract cho `Zalo OA`, `ZNS`, `Google Calendar`, `EMR`, va `DICOM / PACS`, va `OpsControlCenterService` da render cung mot lop provider-health thay vi page/command tu dien giai runtime drift theo tung cach rieng
    - da mo rong `IntegrationOperationalReadModelService` sang lane `popups:prune` va `photos:prune`, de popup log retention va patient photo retention candidate query duoc dung lai boi command thay vi moi lenh tu query rieng
    - da mo rong `OperationalAutomationAuditReadModelService` them `popups:dispatch-due`, `popups:prune`, va `photos:prune`, de tracked automation catalog khop voi scheduler wrapper va recent OPS runs khong bi thieu command
    - da mo rong `OpsControlCenterService` de hien thi them `Popup announcement logs` va `Patient photos` trong integration retention backlog, de OPS va command layer cung doc mot retention contract
    - da nang `OperationalAutomationAuditReadModelService` len wrapper-aware contract bang cach doc them `metadata->target_command`, de automation chay qua `ops:run-scheduled-command` van vao dung recent OPS runs va tracked command surface
    - da mo rong `IntegrationOperationalReadModelService` sang lane `EMR clinical media`, de `emr:prune-clinical-media` va `OpsControlCenterService` dung chung candidate query cho retention class `temporary` / `clinical_operational`
    - da mo rong them provider-health/readiness lane cho `DICOM / PACS`, de `IntegrationSettings` va `OpsControlCenterService` doc cung contract voi `emr:check-dicom-readiness`, dong thoi `OperationalAutomationAuditReadModelService` bat dau track command nay trong recent OPS runs va observability control-plane catalog
    - da mo rong them tracked control-plane command catalog sang `emr:sync-events`, `emr:reconcile-integrity`, `emr:reconcile-clinical-media`, va `emr:prune-clinical-media`, de recent OPS runs va smoke pack khong bo sot cac lane EMR maintenance da co trong scheduler/release gates
    - da mo rong them tracked control-plane command catalog sang `google-calendar:sync-events`, de Google Calendar sync lane duoc surfacing cung lop voi `EMR` / `ZNS` trong recent OPS runs va smoke pack
    - da gom `tracked commands` va `smoke commands` vao `OpsAutomationCatalog`, de `OperationalAutomationAuditReadModelService` va `OpsControlCenterService` doc chung mot command catalog thay vi giu hai danh sach rieng de drift
    - da mo rong `OpsAutomationCatalog` sang scheduler definitions, de `routes/console.php` va `SchedulerHardeningCommandTest` cung doc mot target catalog thay vi lap lai danh sach target command wrapper
    - da tao `OpsReleaseGateCatalog`, de `RunReleaseGates` va `VerifyProductionReadinessReport` doc chung mot release-gate contract thay vi giu required gate knowledge o hai noi rieng
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

- Status: `In progress`
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
  - Tien do hien tai:
    - da bat dau lane `config/runtime settings` bang fail-fast runtime readiness cho `emr:sync-events`, de command khong chay vao processing loop khi `emr.enabled=true` nhung `base_url/api_key` chua day du
    - da tiep tuc cung lane nay cho `zns:run-campaigns`, va da gom `ZnsProviderClient` thanh diem check chung cho provider credentials (`access_token`, `send_endpoint`) de `run-campaigns` va `sync-automation-events` khong dien giai runtime drift theo hai cach khac nhau
    - da mo rong lane nay bang `IntegrationProviderHealthReadModelService`, de `OpsControlCenterService`, `IntegrationSettings` (readiness notification + snapshot section), `zns:run-campaigns`, `zns:sync-automation-events`, `google-calendar:sync-events`, `emr:sync-events`, va `ZnsCampaignRunnerService` dung chung contract readiness / runtime-error message cho provider thay vi tu noi suy raw setting rieng le
    - da mo rong them publisher-side runtime guard cho `ZnsAutomationEventPublisher`, `GoogleCalendarSyncEventPublisher`, va `EmrSyncEventPublisher`, de control-plane khong tiep tuc sinh backlog outbox/event moi khi provider dang enabled nhung chua day du credential/runtime settings
    - da mo rong tiep lane `secret rotation / revoke` bang `expiredGraceRotationSummary()` trong `IntegrationOperationalReadModelService`, de `integrations:revoke-rotated-secrets` preview expired keys qua cung reader voi OPS va Integration Settings, thay vi command tu ngầm doc trang thai grace tu write-side service
    - da mo rong tiep lane `payload retention / pruning` bang cach dua `popups:prune` va `photos:prune` qua `IntegrationOperationalReadModelService`, de command layer khong con tu sao chep retention candidate rules cua popup logs va patient photos
    - da mo rong tiep lane `payload retention / pruning` sang `EMR clinical media`, de `emr:prune-clinical-media` va OPS cung doc mot retention contract cho `temporary` / `clinical_operational`
    - da mo rong tiep lane `provider run/readiness` sang `emr:check-dicom-readiness`, de DICOM/PACS readiness gate duoc surfacing nhu mot provider card first-class va command nay di vao tracked OPS automation catalog thay vi ton tai rieng le ben ngoai provider-health contract
    - da mo rong tiep lane `provider run/readiness` va `maintenance visibility` sang bo `EMR maintenance commands`, de `emr:sync-events`, `emr:reconcile-integrity`, `emr:reconcile-clinical-media`, va `emr:prune-clinical-media` duoc surfacing nhat quan trong tracked OPS automation catalog va smoke pack
    - da mo rong tiep lane `provider run/readiness` sang `google-calendar:sync-events`, de Google Calendar sync lane khong bi tach khoi tracked OPS automation catalog va smoke pack khi so voi `EMR` / `ZNS`
    - da mo rong tiep lane `release gate / readiness verification` bang `OpsReleaseGateCatalog`, de checklist release production va strict readiness signoff dung chung mot source of truth cho required gates
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
