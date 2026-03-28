# Refactor and Review Execution Plan

Tai lieu nay chuyen backlog sau baseline thanh chuoi phase co the trien khai tuan tu, an toan voi repo dang co co che auto-deploy khi push.

## Metadata

- Backlog source: `docs/roadmap/refactor-review-master-backlog.md`
- Audit source: `docs/reviews/program-audit-summary.md`
- Last updated: `2026-03-28`

## Current State

- `13/13` module da dat `Clean Baseline Reached`.
- `100/100` issue baseline da `Resolved`.
- Docs hub, module inventory, review pipeline, issue register, va module plans da co.
- Production release gates va production readiness pack da pass tren environment that ngay `2026-03-28`.
- Priority hien tai la shared contracts va structural convergence.

## Phase 0 - Docs Cleanup and Audit Packaging

- Status: `Completed`
- Goal:
  - Co mot bo tai lieu review/doc/backlog ro rang de khong review thieu module hay lap lai issue da fix.
- Scope:
  - `README`
  - `docs/README.md`
  - `docs/modules/README.md`
  - `docs/reviews/*`
  - `docs/roadmap/*`
- Dependency:
  - none
- Rollback risk:
  - `Low`
- Test strategy:
  - link/path review
  - metadata consistency review
- Deploy safety note:
  - docs-only, khong can deploy app runtime.

## Phase 1 - Rollout and Smoke-Test Gate

- Status: `Completed`
- Goal:
  - Dong khoang cach giua code baseline va moi truong that truoc khi lam refactor sau hon.
- Scope:
  - `RRB-001`
  - `RRB-002`
- Dependency:
  - Phase 0
- Rollback risk:
  - `High`
- Test strategy:
  - backup truoc rollout
  - pre/post migration verification
  - module smoke test theo wave
  - queue/provider/control-plane drills
- Deploy safety note:
  - Khong push gom wave lon.
  - Moi wave phai co rollback checklist, owner ro rang, va thoi gian quan sat sau deploy.

## Phase 2 - Low-Risk Fixes and Operator UX

- Status: `Completed`
- Goal:
  - Giam thao tac sai cua operator ma khong thay doi domain boundary lon.
- Scope:
  - `RRB-004`
  - `RRB-005`
  - `RRB-006`
- Dependency:
  - Phase 1
- Rollback risk:
  - `Low`
- Test strategy:
  - feature/browser tests cho operator-facing flows
  - smoke test queue triage/read-model
- Deploy safety note:
  - Chia thanh batch UI nho, khong gom nhieu module workflow trong mot deploy.

## Phase 3 - Authorization and Scope Hardening Convergence

- Status: `Completed`
- Goal:
  - Rut gon cac pattern auth/branch scope thanh contract on dinh toan he thong.
- Scope:
  - `RRB-003`
  - `RRB-007`
  - `RRB-008`
- Dependency:
  - Phase 1
- Rollback risk:
  - `Medium`
- Test strategy:
  - permission matrix tests
  - actor/branch scope contract tests
  - spot-check regression o module dai dien
- Deploy safety note:
  - Mọi thay doi seeder/policy/scope phai duoc review rieng va deploy bat dau tu module co blast radius nho hon.

## Phase 4 - Workflow Consistency and Auditability

- Status: `In progress`
- Goal:
  - Chuan hoa cac workflow mutation lane va audit reason contract giua module co state machine.
- Scope:
  - `RRB-009`
  - `RRB-010`
- Dependency:
  - Phase 3
- Rollback risk:
  - `Medium`
- Test strategy:
  - workflow transition tests
  - audit timeline tests
  - browser checks cho dangerous actions
- Progress update:
  - `RRB-009` da cover lane uu tien cho `APPT`, `TRT`, `FIN`, `SUP`, `ZNS` va cac observer audit lien quan.
  - `RRB-009` da dong lane `Appointment` bang managed workflow context trong service, model guard raw status update, va observer audit hop nhat cho ca `status transition` lan `date-only reschedule`.
  - `RRB-009` da mo rong them sang `VisitEpisode` voi canonical `VisitEpisodeService`, model guard raw status update, va smoke flow appointment-to-encounter cap nhat theo workflow contract.
  - `RRB-009` da mo rong them sang `BranchTransferRequest` de dua workflow `request / apply / reject` vao managed context va observer audit co `trigger`.
  - `RRB-009` dang mo rong them sang `PopupAnnouncement` voi workflow service canonical, guided actions, va transition audit co ly do.
  - `RRB-009` da mo rong them sang `PlanItem` voi workflow service canonical cho start / complete / cancel / complete-visit lanes.
  - `RRB-009` da mo rong them sang `ReceiptExpense` voi workflow service canonical, model guard, table/page actions co ly do, va structured audit metadata.
  - `RRB-009` da mo rong them sang `MaterialIssueNote` voi workflow service canonical, model guard, table/page actions co ly do, va transition audit metadata.
  - `RRB-009` da mo rong them sang `InsuranceClaim` voi workflow service canonical, managed transition context, va payment-linked audit metadata.
  - `RRB-009` da mo rong them sang `ClinicalOrder` / `ClinicalResult` voi workflow service canonical, managed transition context, va EMR audit metadata co `reason` / `trigger`.
  - `RRB-009` da mo rong them sang `CareTicket/Note` voi phan tach `manual update` / `canonical transition`, model guard raw status update, va audit metadata co `trigger` / `reason`.
  - `RRB-009` da mo rong them sang `Consent` voi lifecycle service managed context, model guard raw status update, va audit metadata co `trigger` / `signature_source`.
  - `RRB-009` da mo rong them sang `ExamSession / TreatmentProgress` voi `ExamSessionWorkflowService`, model guard raw status update, thay the cac lane `ClinicalNote`, `TreatmentProgressSyncService`, `ExamSessionLifecycleService` dang doi `status` truc tiep, va keo them `TreatmentProgressDay / TreatmentProgressItem` vao managed mutation path.
  - `RRB-009` da mo rong them sang `InstallmentPlan` voi `InstallmentPlanLifecycleService`, giu lai model facade `syncFinancialState()` de tuong thich, va dua `PaymentObserver`, `installments:run-dunning`, `installments:sync-status` vao canonical lifecycle path.
  - `RRB-009` da keo them `Payment` refund / reversal audit metadata ve pattern `reason` / `trigger` / canonical identifiers.
  - `RRB-009` da mo rong them sang `MasterPatientDuplicate / MasterPatientMerge` voi model guard raw status update, `MasterPatientDuplicateWorkflowService` cho `ignore` / `merge-resolution` / `rollback restore`, va loai bo mass-update bypass o `MasterPatientIndexService`.
  - `RRB-009` da mo rong them sang `WebLeadEmailDelivery` voi model guard raw status update, canonical `markProcessing` / `markSent` / `markFailure` / `resetForReplay`, va service mail noi bo di qua managed workflow path.
  - `RRB-009` da mo rong them sang `ZnsCampaignDelivery` voi model guard raw status update, canonical `markSent` / `markFailure`, `claimForProcessing`, va loai bo stale-processing mass-update bypass trong `ZnsCampaignRunnerService`.
  - `RRB-009` da dong them cac lane outbox `ZnsAutomationEvent`, `GoogleCalendarSyncEvent`, `EmrSyncEvent` bang transition contract, replay methods, va stale-processing reclaim khong con bypass model events.
  - `RRB-010` da co read-model dau tien cho patient operational timeline ben canh `ClinicalAuditTimelineService`, va da cover them `PlanItem`, `ReceiptExpense`, `MaterialIssueNote`, `BranchTransferRequest`, va mo ta finance timeline than thien hon cho `Payment`.
  - `RRB-010` da mo rong sang `ReportSnapshot` reader conventions bang `OperationalKpiSnapshotReadModelService`, va da dua `OperationalKpiPack` cung `OpsControlCenter` dung chung latest snapshot date, SLA counts, filtered latest snapshot, va branch benchmark summary.
  - `RRB-010` da mo rong them lane `OperationalKpiAlertReadModelService` de hop nhat open/resolved KPI alert counts va open-alert summary giua `OperationalKpiPack` va `OpsControlCenter`.
  - `RRB-010` da tiep tuc dua `ops:check-observability-health` vao cung KPI reader contract, de `open_kpi_alerts` va `snapshot_sla_violations` khong con tu query raw tach rieng.
  - `RRB-010` da keo them write-side `OperationalKpiAlertService` vao reader contract cho phan active-alert counting sau snapshot evaluation.
  - `RRB-010` da mo rong them `OperationalAutomationAuditReadModelService` de gom tracked control-plane audit query cho `OpsControlCenterService` va `CheckObservabilityHealth`.
  - `RRB-010` da mo rong them `ReportSnapshotReadModelService` de gom generic snapshot lookup cho `CheckSnapshotSla` va `CompareReportSnapshots`.
  - `RRB-010` da mo rong them `PatientActivityTimelineReadModelService` de hop nhat appointment / treatment plan / invoice / payment / note / branch-log entries voi `PatientOperationalTimelineService` va `ClinicalAuditTimelineService`, dua `PatientActivityTimelineWidget` ve dung vai tro render read-model.
  - `RRB-010` da mo rong them `GovernanceAuditReadModelService` de gom recent governance-relevant audits va branch-visible filtering cho `OpsControlCenterService`.
  - `RRB-010` da mo rong them `ZnsOperationalReadModelService` de hop nhat summary-card counts, retention candidate rules, bao gom ca `automation log retention`, va phan ZNS backlog/retention/prune count duoc tai su dung boi `OpsControlCenterService`, `CheckObservabilityHealth`, va `zns:prune-operational-data`.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` de hop nhat web-lead retention candidates, lead-mail retry/dead backlog, webhook retention, EMR retention, va Google Calendar retention counts cho `OpsControlCenterService`, `ops:check-observability-health`, va `integrations:prune-operational-data`, loai bo raw integration counting/prune rules khoi cockpit service va release gate command.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` va `ZnsOperationalReadModelService` vao `sync:emr`, `sync:google-calendar`, va `sync:zns` cho scoped dead-letter backlog counts, tiep tuc loai bo raw command query khoi outbox sync lanes.
  - `RRB-010` da tiep tuc dua integration secret grace-state vao read-model layer: `OpsControlCenterService` va `IntegrationSettings` da doc active/expired grace rotations thong qua `IntegrationOperationalReadModelService` thay vi tro truc tiep vao write-side rotation service.
  - `RRB-010` da mo rong them `ZnsOperationalReadModelService` vao page `ZaloZns` cho branch-scoped operational summary cards, de page khong con tu dem ad-hoc raw query cho pending/retry/dead delivery va campaign failed.
  - `RRB-010` da bo sung `IntegrationSettingsAuditReadModelService` de page `IntegrationSettings` dung reader chung cho recent setting logs, tiep tuc loai bo raw audit-query khoi page logic.
  - `RRB-010` da bo sung `IntegrationProviderHealthReadModelService` de `OpsControlCenterService` render provider-readiness cho `Zalo OA`, `ZNS`, `Google Calendar`, `EMR`, va `DICOM / PACS` bang contract chung thay vi page/service/command tu dien giai tung provider rieng.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` sang lane `popups:prune` va `photos:prune`, de popup logs va patient photos retention candidate query khong con bi lap lai o command layer.
  - `RRB-010` da bo sung `OperationalAutomationAuditReadModelService` them `popups:dispatch-due`, `popups:prune`, va `photos:prune`, de recent OPS runs va tracked command catalog khop voi scheduler wrapper catalog.
  - `RRB-010` da mo rong them `OpsControlCenterService` de hien thi `Popup announcement logs` va `Patient photos` tu cung `IntegrationOperationalReadModelService`, giu prune backlog tren OPS dong bo voi command layer.
  - `RRB-010` da nang `OperationalAutomationAuditReadModelService` len wrapper-aware contract bang cach doc ca `metadata->target_command`, de cac command duoc chay qua `ops:run-scheduled-command` khong bi vo hinh trong recent OPS runs.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` va `OpsControlCenterService` sang lane `EMR clinical media`, de retention backlog theo `temporary` / `clinical_operational` khop giua `emr:prune-clinical-media`, OPS, va control-plane summary.
  - `RRB-010` da tiep tuc dong bo `emr:check-dicom-readiness` vao `OperationalAutomationAuditReadModelService`, de DICOM readiness gate xuat hien trong recent OPS runs va observability control-plane catalog thay vi ton tai rieng le ngoai reader contract.
  - `RRB-013` da tiep tuc lane config/runtime settings sang publisher-side: `ZnsAutomationEventPublisher`, `GoogleCalendarSyncEventPublisher`, va `EmrSyncEventPublisher` khong con enqueue them event/outbox moi khi provider runtime dang drift hoac thieu credential.
  - `RRB-013` da tiep tuc lane `secret rotation / revoke`: `integrations:revoke-rotated-secrets` doc expired-grace preview tu `IntegrationOperationalReadModelService` de summary/audit cua command dung cung reader contract voi OPS va Integration Settings.
  - `RRB-013` da tiep tuc lane `payload retention / pruning`: `popups:prune` va `photos:prune` da dung chung retention candidate reader voi control-plane, tranh viec command layer va OPS/Integration surfaces dien giai retention theo nhieu cach.
  - `RRB-013` da mo rong them lane `payload retention / pruning` sang `EMR clinical media`, de `emr:prune-clinical-media` khong con giu candidate query rieng tach khoi control-plane retention reader.
  - `RRB-013` da mo rong them lane `provider run/readiness` sang `DICOM / PACS`, de provider-health snapshot tren `IntegrationSettings` va `OpsControlCenterService` dung cung readiness contract voi `emr:check-dicom-readiness`, tranh viec command gate va page health surface dien giai readiness imaging theo hai cach khac nhau.
  - `CARE` da vao wave convergence qua `CareTicket/Note`; `OPS` van chua can wave rieng beyond baseline packs.
- Deploy safety note:
  - Lam tung workflow module mot.
  - Khong doi workflow contract cua nhieu module trong cung mot release.

## Phase 5 - Structural Refactor

- Goal:
  - Xu ly nhung diem coupling lon nhat giua domain va control-plane.
- Scope:
  - `RRB-011`
  - `RRB-013`
- Dependency:
  - Phase 4
- Rollback risk:
  - `High`
- Test strategy:
  - end-to-end reconciliation tests
  - concurrency tests
  - migration rehearsal
  - canary rollout va rollback drill
- Deploy safety note:
  - Chi nen lam tren branch/PR rieng, co feature flag hoac song song read-model neu can.

## Phase 6 - Performance and Reporting Hardening

- Goal:
  - Hoan thien layer report/read-model khi dataset va tan suat van hanh tang.
- Scope:
  - `RRB-012`
  - phan measurement/perf verification cua `RRB-002`
- Dependency:
  - Phase 1
  - Phase 4
- Rollback risk:
  - `Medium` den `High` tuy batch
- Test strategy:
  - explain/query measurement
  - export scope tests
  - production-like dataset smoke tests
  - snapshot freshness drills
- Deploy safety note:
  - Moi toi uu query/report phai co measurement truoc/sau, va tranh thay doi cung luc voi structural ledger refactor.

## Recommended Delivery Rhythm

1. Mot phase chi nen co `1-3` batch runtime.
2. Moi batch runtime nen co:
   - test file scope ro rang
   - smoke test list
   - rollback note
   - owner van hanh
3. Moi batch push len branch co PR rieng; chi merge `main` khi da chot deploy window.
4. Sau moi phase, cap nhat lai:
   - `docs/reviews/00-master-index.md`
   - `docs/reviews/program-audit-summary.md`
   - `docs/roadmap/refactor-review-master-backlog.md`

## Exit Conditions Per Phase

- Phase 1 xong khi:
  - migration/backfill con lai da chay
  - smoke tests control-plane/async lane co bang chung pass
- Phase 2 xong khi:
  - operator-facing friction chinh da duoc giam ma khong mo lai auth/scope regression
- Phase 3 xong khi:
  - shared auth/scope contracts da duoc chot o muc du dung lai
- Phase 4 xong khi:
  - workflow + audit reason pattern da nhat quan tren nhom module uu tien
- Phase 5 xong khi:
  - immutable/adjustment/control-plane refactor da duoc tach thanh lanes ro rang
- Phase 6 xong khi:
  - report/read-model hot path co metric va cadence theo doi ro rang
