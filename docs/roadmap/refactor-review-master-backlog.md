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
- Last updated: `2026-04-13`

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
    - da dua `PatientTreatmentPlanSection` draft lane vao `PatientTreatmentPlanDraftService`, de `prepare draft`, `sync diagnosis tu latest exam`, `resolve latest-or-create plan`, va `persist draft items` di qua mot workflow service canon thay vi nam truc tiep trong Livewire component
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
    - da bo sung `ZnsCampaign::cancel()` canonical boundary noi ve `ZnsCampaignWorkflowService`, de entry point huy campaign di qua workflow service thay vi caller tu doi trang thai
    - da dua cac lane outbox noi bo `ZnsAutomationEvent`, `GoogleCalendarSyncEvent`, `EmrSyncEvent` vao transition contract, canonical replay methods, va loai bo stale-processing mass-update bypass
    - da mo rong them `IntegrationProviderRuntimeGate` sang inbound `ValidateWebLeadToken`, `ValidateInternalEmrToken`, va `ZaloWebhookController`, de Web Lead API / EMR internal API / Zalo webhook ingress dung chung contract `skip / fail / ready` cho `enabled/token/secret configured` thay vi middleware/controller tu lap lai runtime gate rieng
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
    - da bo sung `PatientOverviewReadModelService` cho `PatientOverviewWidget`, payment summary, tab counters, treatment progress, day summaries, material usages, factory orders, material issue notes, latest printable forms, va CTA `medical record` tren `ViewPatient` / `PatientExamForm`, de patient workspace khong con tu giu treatment-plan / appointment / invoice / payment / tab-count / treatment-progress / lab-material / printable-form / EMR CTA query va permission wiring truc tiep trong widget, page, va livewire component
    - da mo rong them `PatientOverviewReadModelService` sang contract capability/tab visibility cua `ViewPatient`, de show-hide tabs `prescriptions / appointments / payments / forms / care / lab-materials` va create CTAs duoc resolve qua mot read-model/capability surface thay vi nam rai rac trong page class
    - checkpoint `2026-04-01` da tiep tuc dua cac render-contract cua `ViewPatient` (`rendered tabs`, `payments`, `forms`, `treatment progress`, `lab-material sections`, `overview card`) ve `PatientOverviewReadModelService`, de page class giam them mot lop adapter/formatting logic
    - checkpoint `2026-04-01` da bo sung them `workspaceViewState()` trong `PatientOverviewReadModelService`, de `ViewPatient` va Blade doc chung mot patient-workspace state hop nhat (`overview card`, `basic info`, `tabs`, `payments`, `forms`, `treatment progress`, `lab-materials`) thay vi duy tri nhieu cache/getter render rieng le trong page class
    - checkpoint `2026-04-02` da tiep tuc lam gon `ViewPatient` page-state: bo them mot lop proxy getter chi doc lai tung key tu `workspaceViewState`, de test va page class khoa thang contract read-model cho `overview card`, `basic-info panels`, `rendered tabs`, `payments`, `forms`, `treatment progress`, va `lab-material sections`
    - checkpoint `2026-04-02` da tiep tuc lam gon `ViewPatient` adapter layer: bo them proxy `tabs` va `tabCounters`, de page doc visible-tab state truc tiep tu `workspaceViewState['tabs']` va regression khoa read-model `tabCounters()` thay vi page getter khong con consumer thuc te
    - da bo sung `PatientAppointmentActionReadModelService`, de action `Xem lịch hẹn` tren `PatientsTable` dung chung contract `hasActiveAppointments` va `activeAppointmentOptions` thay vi query `Appointment` lap lai trong `visible` va `options`
    - da bo sung `PatientExamStatusReadModelService`, de `PatientExamForm` doc `treatmentProgressDates` va `toothTreatmentStates` qua mot read-model service va giu precedence state cua rang ngay trong service thay vi query/mapping truc tiep trong Livewire component
    - da bo sung `PatientExamMediaReadModelService`, de `PatientExamForm` doc `mediaTimeline`, `mediaPhaseSummary`, va `evidenceChecklist` qua mot read-model service thay vi giu query `ClinicalMediaAsset`, signed URL generation, missing evidence labels, va quality warnings trong `render()`
    - da bo sung `PatientExamReferenceReadModelService`, de `PatientExamForm` doc `ToothCondition` payload va `otherDiagnosisOptions` qua mot read-model service thay vi tu query/map `conditionsJson`, `conditionOrder`, va `Disease` option lists trong `render()`
    - da bo sung `PatientExamSessionReadModelService`, de `PatientExamForm` doc danh sach exam sessions va co `is_locked` thong nhat qua mot read-model service thay vi tu lap `lockedDates` va loop decorate sessions trong `render()`
    - da bo sung `PatientExamClinicalNoteWorkflowService`, de `PatientExamForm` dung service cho `draftForSession`, `buildPayload`, `ensurePersisted`, va `optimistic update` cua clinical note thay vi giu helper draft/persist/update note truc tiep trong component
    - da bo sung `PatientExamIndicationStateService`, de `PatientExamForm` dung chung normalize/toggle contract cho `indications`, `indication_images`, va `tempUploads`, bo stale upload cleanup + key normalization khoi component
    - da bo sung `PatientExamMediaWorkflowService`, de `PatientExamForm` dung service cho `createAsset`, `removeAsset`, va `storeUploads`, bo lane persist/archive `ClinicalMediaAsset` + `ClinicalMediaVersion`, checksum/size/mime lookup, temp-upload storage, va cleanup file khoi Livewire component
    - da bo sung `PatientExamSessionWorkflowService`, de cac lane `deleteSession`, `createSession`, va `saveEditingSession` cua `PatientExamForm` di qua workflow service va gom rule `locked by treatment progress`, `da phat sinh chi dinh / don thuoc`, `duplicate session date`, va `reschedule sync cho clinical note / standalone encounter` ra khoi Livewire component
    - da mo rong `PatientOverviewReadModelService` sang payment-summary presentation contract (`formatted totals` + `create_payment_url`) de `ViewPatient` khong con tu build payment widget state
    - da bo sung `PatientExamDoctorReadModelService`, de `PatientExamForm` doc assignable-doctor options va doctor-name lookup qua mot read-model service thay vi giu `scopeAssignableDoctors` va `User::find()` trong component
    - da bo sung `OperationalStatsReadModelService` cho `OperationalStatsWidget`, de dashboard stats `new customers today` / `appointments today` / `pending confirmations` khong con tu query truc tiep trong widget va da duoc branch-scope thong nhat qua `BranchAccess`
    - da bo sung `PatientTreatmentPlanReadModelService` cho `PatientTreatmentPlanSection`, de `plan items`, diagnosis map/options/details, service-category picker, service search, va financial totals khong con bi query/tong hop truc tiep trong Livewire component; song song do, draft mutation lane cua component nay da duoc tach sang `PatientTreatmentPlanDraftService`
    - da don `RiskScoringDashboard` ve mot nguon branch-scope duy nhat, loai bo page-local branch filter query va giu viec doc selected branch thong qua shared filter state + `PatientInsightReportReadModelService`
    - da bo sung `CustomerCareSlaReadModelService` de `CustomerCare` doc summary SLA / ownership / channel / branch / staff breakdown qua mot read-model service, thay vi giu aggregate query truc tiep trong page class
    - da tao `GovernanceAuditReadModelService` de gom recent governance-relevant audit query va branch-visible filtering cho `OpsControlCenterService`, loai bo them mot raw audit query khoi cockpit service
    - da tao `ZnsOperationalReadModelService` de gom summary-card counts, dead/retry backlog, va retention candidate rules cho `OpsControlCenterService`, `CheckObservabilityHealth`, va `zns:prune-operational-data`, bao gom ca `automation log retention`, thay cho viec page/command tu query status ZNS theo tung chot rieng le
    - da tao `IntegrationOperationalReadModelService` de gom web-lead retention candidates, lead-mail retry/dead backlog, webhook retention, EMR retention, va Google Calendar retention counts cho `OpsControlCenterService`, `ops:check-observability-health`, va `integrations:prune-operational-data`, thay cho viec cockpit/command tu query tung model integration rieng le
    - da mo rong them `IntegrationOperationalReadModelService` va `ZnsOperationalReadModelService` sang cac lenh `sync-emr`, `sync-google-calendar`, `sync-zns` cho dead-letter backlog count scoped, giam them query raw khoi sync commands
    - da dua them grace-state reader cho integration secrets vao `IntegrationOperationalReadModelService`, va `OpsControlCenterService` / `IntegrationSettings` da bat dau doc active-expired grace rotations qua reader thay vi tro truc tiep vao write-side rotation service
    - da mo rong `ZnsOperationalReadModelService` them branch-scoped summary cards, `campaigns_running`, va `provider_status_code` option list, va page `ZaloZns` da bat dau dung reader nay cho automation/delivery/campaign summary thay vi tu dem query raw tren page
    - da tao `IntegrationSettingsAuditReadModelService` de gom recent setting-log query, va page `IntegrationSettings` da doc recent audit trail qua reader nay thay vi tu query raw `ClinicSettingLog`
    - da tao `IntegrationProviderHealthReadModelService` de hop nhat provider readiness contract cho `Zalo OA`, `ZNS`, `Google Calendar`, `EMR`, va `DICOM / PACS`, va `OpsControlCenterService` da render cung mot lop provider-health thay vi page/command tu dien giai runtime drift theo tung cach rieng
    - da mo rong them `IntegrationProviderRuntimeGate` sang inbound `Web Lead API`, `EMR internal API`, va `Zalo webhook`, de `ValidateWebLeadToken` / `ValidateInternalEmrToken` / `ZaloWebhookController` dung chung ingress gate thay vi tu doc runtime setting rieng
    - da mo rong `IntegrationOperationalReadModelService` sang lane `popups:prune` va `photos:prune`, de popup log retention va patient photo retention candidate query duoc dung lai boi command thay vi moi lenh tu query rieng
    - da bo sung `PopupAnnouncementCenterReadModelService` de `PopupAnnouncementCenter` doc pending delivery, active delivery, va formatted announcement payload qua mot read-model service thay vi giu raw query va payload mapping trong Livewire component
    - da bo sung `PopupAnnouncementDeliveryWorkflowService`, de `PopupAnnouncementCenter` khong con goi `markSeen / markAcknowledged / markDismissed` truc tiep tren model, va `PopupAnnouncementDispatchService` da dung chung workflow service nay cho lane `expire ended deliveries`
    - da tiep tuc don gian hoa `ViewPatient`, `PopupAnnouncementCenter`, va `RiskScoringDashboard` o lop render/presentation contract: `payments`, `forms`, `basic-info`, `workspace tabs`, `lab-materials`, `header actions`, `popup action payload`, va `risk badge/stat formatting` dang duoc dua ve read-model/model helpers thay vi de page/view/service tu map lai
    - checkpoint `2026-04-01` da bo sung them `centerViewState()` trong `PopupAnnouncementCenterReadModelService`, de wrapper view cua `PopupAnnouncementCenter` chi con render mot state hop nhat cho polling va active announcement thay vi tu cham truc tiep vao tung bien Livewire/payload roi rac
    - checkpoint `2026-04-02` da tiep tuc lam gon `PopupAnnouncementCenter` view-state: `centerViewState()` nay da bo key `has_active_announcement` va giu shell `announcement + polling_interval` la contract thuc te giua component, read-model, va Blade
    - checkpoint `2026-04-03` da tiep tuc chuyen `ViewPatient` va `PopupAnnouncementCenter` sang computed shell (`workspaceViewState`, `viewState`) thay vi getter legacy, de regression manual-object goi method shell truc tiep va runtime Livewire 3 giu mot contract nhat quan hon
    - checkpoint `2026-04-03` da tiep tuc chuyen `CustomerCare` sang computed shell `slaSummary`, dong thoi chot `ConversationInbox` sang bo computed shells (`conversationList`, `selectedConversation`, `branchOptions`, `assignableStaffOptions`, `conversationAssigneeOptions`, `handoffPriorityOptions`, `handoffStatusOptions`, `inboxTabOptions`, `inboxStats`); trong lane dang lam, `app/Filament` + `app/Livewire` khong con public `get*Property()` legacy nao nua
    - checkpoint `2026-04-04` da chot them lane page-shell/read-model presentation cho `ViewPatient`, `PopupAnnouncementCenter`, `ConversationInbox`, `CustomerCare`, `CalendarAppointments`, `SystemSettings`, `DeliveryOpsCenter`, `FrontdeskControlCenter`, va `PlaceholderPage`: `ViewPatient` da doi sang `activeWorkspaceTabView()` + tab partials; `PopupAnnouncementCenter` da co shell partial va payload `has_announcement` / `aria_live`; `ConversationInbox` / `CustomerCare` da dung `inboxViewState()` / `careViewState()` de render queue/detail/SLA panels; `CalendarAppointments` da tach shell header/metrics/filters/modal sang partials va dung presentation payload cho metric/filter state; `SystemSettings` va `PlaceholderPage` da dung `pageViewState()` + shell partials; `DeliveryOpsCenter` va `FrontdeskControlCenter` da gom chung `pageViewState()` qua trait `BuildsControlCenterPageViewState`
    - checkpoint `2026-04-07` da tiep tuc dong bo lane `clinical form presentation`: `ToothChart` da duoc nang thanh custom Filament field co view data rieng, `TreatmentPlanForm` va `ClinicalNotesRelationManager` khong con dung `ViewField` + `@php` de boot `conditionsJson / conditionOrder / dentition state path`, `ToothChartModalViewState` da gom presenter contract cho `tooth-chart-modal`, va `InstallmentPlan` da co model-backed presentation methods cho `installment-schedule` modal; sau batch nay `resources/views/filament` + `resources/views/livewire` khong con `@php` inline nao trong current branch checkpoint
    - checkpoint `2026-04-09` da tiep tuc dong bo lane shell/read-model nho: `PatientActivityTimelineWidget` da chuyen sang `timelineViewState()` + widget render payload thay vi `getViewData()`, `PatientTreatmentPlanSection` da chuyen sang `sectionViewState()` hop nhat cho `list_panel / plan_modal / procedure_modal`, `PatientExamForm` da dua indications/evidence timeline ve payload `indicationOptions / indicationUploadCards / evidence*`, va `PasskeysComponent` da co `viewState()` + shell partial thay vi giu Blade/copy hardcode trong component view
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

- Status: `In progress`
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
  - Tien do hien tai:
    - checkpoint `2026-04-09` da bat dau lane immutable ledger cho `FIN`: `WalletLedgerEntry` da duoc khoa immutable o cap model, `PatientWalletService` da bo sung `trigger` + adjustment/reversal metadata cho ledger entries va audit trail, de wallet reconciliation co append-only contract ro hon.
    - checkpoint `2026-04-09` da bat dau lane immutable ledger cho `INV/TRT`: `InventoryTransaction` da duoc khoa immutable o cap model, va `TreatmentMaterialUsageService` da duoc khoa regression de inventory ledger sau khi post usage khong con bi sua/xoa truc tiep.
    - checkpoint `2026-04-09` da tiep tuc ha destructive surface cua `TRT`: `TreatmentMaterialsTable` da bo action `Hoan tac ghi nhan`, `TreatmentMaterialPolicy` khong con cho `delete/restore/forceDelete`, va regression da khoa direct delete/model-layer delete de lane vat tu dieu tri mac dinh di theo huong reversal-only thay vi destructive UI flow.
    - checkpoint `2026-04-09` da tiep tuc bo sung audit/timeline cho `TRT/FIN`: `TreatmentMaterialUsageService` nay da ghi `ACTION_CREATE`/`ACTION_REVERSAL` audit co cau truc cho usage va reversal, `PatientOperationalTimelineService` da map duoc `Ghi nhận vật tư điều trị`, `Hoàn tác vật tư điều trị`, va `Điều chỉnh ví bệnh nhân` thay vi de nhung bien dong nay nam rieng trong audit log thô.
    - checkpoint `2026-04-09` da tiep tuc siet lane `SUP`: `FactoryOrderPolicy` khong con cho `delete`, `EditFactoryOrder` va `FactoryOrdersTable` da go destructive delete surfaces, va `FactoryOrder` model da chan delete truc tiep; checkpoint `2026-04-13` bo sung them boundary `FactoryOrder::cancel()` canonical noi ve `FactoryOrderWorkflowService` de domain labo di qua workflow thay vi xoa ban ghi.
    - checkpoint `2026-04-10` da tiep tuc siet lane `INV`: `MaterialIssueNote` da duoc ha ve cancel-only, khi `EditMaterialIssueNote` va `MaterialIssueNotesTable` khong con expose delete surfaces, `MaterialIssueNotePolicy` khong con cho `delete/restore/forceDelete`, va model delete guard hard-deny moi thao tac xoa truc tiep de phieu xuat di qua workflow `cancel()`.
    - checkpoint `2026-04-10` da tiep tuc siet lane `TRT`: `TreatmentPlan` va `PlanItem` da duoc ha ve cancel-only, khi delete surfaces tren `EditTreatmentPlan`, `EditPlanItem`, va `PlanItemsRelationManager` bi go bo, `TreatmentPlanPolicy` / `PlanItemPolicy` khong con cho `delete/restore/forceDelete`, va model delete guard hard-deny moi thao tac xoa truc tiep de dieu tri di qua workflow `cancel()`.
    - checkpoint `2026-04-14` da tiep tuc chuan hoa lane `TRT` o model boundaries: `TreatmentPlan` va `PlanItem` da co them `cancel()` canonical noi ve `TreatmentPlanWorkflowService` / `PlanItemWorkflowService`, de caller layer di qua workflow entry point nhat quan thay vi goi service truc tiep.
    - checkpoint `2026-04-14` da bo sung them `TreatmentPlan::approve()` / `start()` / `complete()` canonical boundaries noi ve `TreatmentPlanWorkflowService`, de entry points workflow duoc goi tu model layer thay vi tu service truc tiep.
    - checkpoint `2026-04-10` da tiep tuc siet lane `FIN`: `Payment` da dong bo `PaymentPolicy` va `EditPayment` voi immutable ledger contract, khong con mo delete/restore/force-delete surface va buoc moi thao tac reversal di qua workflow canonical thay vi xoa truc tiep.
    - checkpoint `2026-04-10` da tiep tuc siet lane `FIN`: `ReceiptExpense` da hard-deny `delete/restore/forceDelete` o policy, va model delete guard moi da buoc phieu thu/chi di qua workflow state boundary thay vi xoa truc tiep bang Eloquent.
    - checkpoint `2026-04-14` da tiep tuc chuan hoa model boundaries cho lane `FIN`: `ReceiptExpense` da co them `approve()` va `post()` canonical noi ve `ReceiptExpenseWorkflowService`, con `Payment` da co them `reverse()` canonical noi ve `PaymentReversalService`, de caller layer khong con phai goi service truc tiep moi khi di qua workflow chinh.
    - checkpoint `2026-04-14` da tiep tuc chuan hoa lane `FIN` o `Invoice`: model da co them `cancel()` canonical noi ve `InvoiceWorkflowService`, va regression khoa lai cancel audit/status contract cho ca service path lan model boundary path.
    - checkpoint `2026-04-10` da tiep tuc siet lane `CLIN`: `ClinicalOrder` da co model delete guard moi, de chi dinh lam sang co `cancel()` canonical khong con bi xoa truc tiep ngoai workflow.
    - checkpoint `2026-04-13` da tiep tuc siet lane workflow-backed adjunct surfaces: `PopupAnnouncement` da duoc ha ve cancel-only, khi `EditPopupAnnouncement` va `PopupAnnouncementsTable` khong con expose delete/restore/force-delete surfaces, `PopupAnnouncementPolicy` hard-deny `delete/restore/forceDelete`, model delete guard moi chan xoa truc tiep, va model nay da co them `cancel()` canonical boundary noi ve `PopupAnnouncementWorkflowService`.
    - checkpoint `2026-04-13` da tiep tuc khoi sau lane `TRT` bang regression reversal idempotency cho `TreatmentMaterialUsageService`: retry/duplicate calls vao `delete()` gio chi restore batch ton kho, tao `InventoryTransaction` adjust, va ghi `ACTION_REVERSAL` audit duy nhat mot lan cho moi `TreatmentMaterial` usage da duoc hoan tac.
    - checkpoint `2026-04-13` da tiep tuc siet lane workflow-backed finance adjunct surface: `InsuranceClaim` da co them `cancel()` canonical boundary noi ve `InsuranceClaimWorkflowService`, model delete guard moi chan xoa truc tiep, va regression khoa lai cancel audit metadata cung destructive-path boundary cho ho so bao hiem.
- Tests needed:
  - end-to-end reconciliation suite
  - concurrency tests
  - rollback and reversal semantics tests
- Rollout note:
  - Can canary theo domain edge, khong rewrite cung luc TRT/INV/FIN/SUP.

## [RRB-012] Reporting and snapshot platform convergence

- Status: `In progress`
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
  - Tien do hien tai:
    - da tao `HotReportAggregateReadModelService` de gom readiness, aggregate breakdown query, live fallback breakdown/summary query, va summary stats cho `RevenueStatistical` va `CustomsCareStatistical`
    - da keo `RevenueStatistical` va `CustomsCareStatistical` ve dung chung hot-report reader thay vi moi page tu giu aggregate query/stats/fallback branch-scope rieng
    - da mo rong tiep hot-report reader sang `TrickGroupStatistical`, de lane doanh thu theo nhom thu thuat cung dung chung aggregate readiness, aggregate breakdown query, va summary stats contract tu `report_revenue_daily_aggregates`
    - da tao `FinancialReportReadModelService` de gom cashflow/invoice-balance query va summary contract cho `RevenueExpenditure` va `OwedStatistical`, de hai page finance report nay khong con giu branch-scoped stats/query logic rieng trong page class
    - da tao `FinancialDashboardReadModelService` de gom payment/invoice aggregate contract cho `RevenueOverviewWidget`, `OutstandingBalanceWidget`, `QuickFinancialStatsWidget`, `MonthlyRevenueChartWidget`, `PaymentMethodsChartWidget`, `PaymentStatsWidget`, va heading summary cua `OverdueInvoicesWidget`, de lane dashboard/payment widgets branch-scoped khong con lap lai cung mot set query trong tung widget
    - da tao `FinanceOperationalReadModelService` de gom finance aging / overdue sync / dunning / reversible-receipt watchlist cho `OpsControlCenterService`, de finance OPS summary khong con tu query truc tiep trong cockpit service
    - da tao `PatientInsightReportReadModelService` de gom patient-breakdown va risk-summary query contract cho `PatientStatistical` va `RiskScoringDashboard`, de hai page patient/risk report nay khong con tu giu stats/query branch-scoped rieng trong page class
    - da tao `InventorySupplyReportReadModelService` de gom material-inventory va factory-order report query/summary contract cho `MaterialStatistical` va `FactoryStatistical`, de lane `INV/SUP` khong con tu giu branch-scoped stats/query logic rieng trong page class; dong thoi `FactoryStatistical` da dong branch-filter option leak bang actor-scoped branch options
    - da tao `AppointmentReportReadModelService` de gom appointment-query va visit-episode metric summary contract cho `AppointmentStatistical`, de page report lich hen khong con tu giu stats/query branch-scoped rieng trong page class
    - da mo rong `AppointmentReportReadModelService` sang `CalendarAppointments` cho weekly operational status metrics, de page calendar khong con tu giu raw branch-scoped count query rieng trong `getOperationalStatusMetrics()`
    - da tao `ReportSnapshotComparisonService` de `CompareReportSnapshots` dung chung drift-aware metric diff contract thay vi command tu map numeric/scalar delta rieng
    - da tao `ReportSnapshotSlaService` de `CheckSnapshotSla` dung chung SLA evaluation + missing-placeholder contract thay vi command tu classify `on_time / late / stale / missing` va insert placeholder snapshot
    - checkpoint `2026-04-07` da tiep tuc mo rong `BaseReportPage` sang `pageViewState()` + shell partial chung cho stats cards, de `FactoryStatistical` va cac report con doc presentation contract cho stat cards thay vi giu `@php($stats = $this->getStats())` va markup stats inline trong `base-report.blade.php`
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
    - da bo sung `IntegrationProviderRuntimeGate`, de `emr:sync-events`, `google-calendar:sync-events`, `zns:sync-automation-events`, `zns:run-campaigns`, `ZnsCampaignRunnerService`, va cac publisher `EMR / Google Calendar / ZNS` dung chung contract `skip / fail / ready` thay vi tu lap lai runtime gate rieng
    - da mo rong them `IntegrationProviderRuntimeGate` sang inbound `ValidateWebLeadToken`, `ValidateInternalEmrToken`, va `ZaloWebhookController`, de Web Lead API / EMR internal API / Zalo webhook ingress cung dung contract `skip / fail / ready` cho `enabled/token/secret configured`
    - da bo sung `IntegrationProviderActionService`, de cac action `test connection / readiness / config-url` tren `IntegrationSettings` dung chung contract ket qua va message cho `EMR`, `Google Calendar`, `Zalo OA`, va `ZNS` thay vi page logic tu format rieng tung nut
    - da mo rong `IntegrationProviderActionService` sang `DICOM / PACS` va `Web Lead API`, de `IntegrationSettings` dung cung readiness notification contract cho tat ca provider card first-class thay vi chi cover `Zalo OA` va `ZNS`
    - da bo sung them presentation contract cho `IntegrationProviderHealthReadModelService`, de `IntegrationSettings` doc `status badge`, `summary badge`, `issue badge`, `meta preview`, va `status message` qua `renderedProviderHealthCards` thay vi tu `match` tone classes va tu slice meta/issues trong Blade
    - da tiep tuc dua `IntegrationSettings` sang panel-state ro nghia (`secretRotationPanel`, `providerHealthPanel`, `auditLogPanel`), de page/Blade doc mot lop view-state control-plane thay vi cham truc tiep vao nhieu getter `rendered*` roi rac
    - da mo rong them `IntegrationProviderActionService` sang notification payload contract (`status/title/body`) cho `EMR`, `Google Calendar`, va readiness checks; `IntegrationSettings` page nay chi con dispatch payload da chuan hoa thay vi tu map `success/danger/warning` cho tung action rieng
    - da don them hai action page con sot trong `IntegrationSettings` (`openEmrConfigUrl`, `generateWebLeadApiToken`) ve `sendNotificationPayload()` thay vi dung `Notification::make()` inline, va da bo sung page-action tests cho `EMR config URL` warning va `Web Lead API token` generation notification
    - da mo rong `IntegrationOperationalReadModelService` va `IntegrationSettingsAuditReadModelService` sang rendered payload cho `active grace rotations` va `recent setting logs`, de `IntegrationSettings` khong con tu parse `grace_expires_at`, `changed_at`, hay grace-context ngay trong Blade
    - da mo rong tiep `IntegrationOperationalReadModelService` sang `renderedExpiredGraceRotations()`, de `OpsControlCenterService` va `IntegrationSettings` dung chung grace-rotation presentation contract cho ca active/expired tokens thay vi OPS tu format lai ngay/phut trong cockpit service + Blade
    - da mo rong tiep `OpsControlCenterService` sang `snapshotCards()` cua `IntegrationProviderHealthReadModelService`, de OPS cockpit render `Provider readiness` bang cung `status badge`, `summary badge`, `issue badge`, `meta preview`, va `status message` contract voi `IntegrationSettings` thay vi giu lane provider-card presentation rieng
    - da mo rong tiep `IntegrationOperationalReadModelService` sang `retentionCandidates()`, de `OpsControlCenterService` doc thang prune backlog presentation cho `web lead`, `webhook`, `EMR`, `Google Calendar`, `popup`, `patient photos`, va `clinical media` thay vi tu giu label/retention/tone matrix trong cockpit service
    - da mo rong tiep `ZnsOperationalReadModelService` sang `retentionCandidates()`, de `OpsControlCenterService` doc thang prune backlog presentation cho `ZNS automation logs / events / deliveries` thay vi tu giu matrix rieng trong cockpit service
    - da mo rong tiep `ZaloZns` page sang `snapshotCards()` cua `IntegrationProviderHealthReadModelService` va `dashboardSummaryCards()` cua `ZnsOperationalReadModelService`, de page triage ZNS dung cung provider-health contract voi `IntegrationSettings` / `OPS` va bo 7 summary-card hardcode khoi Blade
    - da tiep tuc dua `ZaloZns` sang `dashboardViewState`, de Blade doc mot state hop nhat cho `summary cards`, `provider health`, `triage notes`, va `guidance notes` thay vi cham truc tiep vao nhieu getter/page property rieng le
    - da tiep tuc gom provider-health card markup vao partial chung cho `IntegrationSettings`, `ZaloZns`, va `OpsControlCenter`, de ba surface control-plane khong con copy-paste renderer cho `status/summary/issue badges`, `meta preview`, va `status message`
    - da tiep tuc dua `IntegrationSettings` sang `providerActionGroups`, de cac nut `readiness / test / open config / generate token` cua `Zalo OA`, `ZNS`, `Google Calendar`, `EMR`, va `Web Lead API` doc tu mot action contract chung thay vi giu `wire:click` hardcode theo tung provider trong Blade; dong thoi `secret rotation` cards va `audit log` table da duoc tach thanh shared partials
    - da tiep tuc dua `IntegrationSettings` sang `providerSupportPanels`, de action buttons va guide partials cua tung provider (`Zalo OA`, `Web Lead API`, `Popup`) doc tu mot support-state chung thay vi tiep tuc giu `if(provider group)` rieng trong Blade
    - da tiep tuc gom render action buttons cua `IntegrationSettings` vao partial chung `provider-action-buttons`, de page settings khong con giu markup button lặp trong vong lap provider
    - da tiep tuc tach renderer field cua `IntegrationSettings` thanh partial chung theo `boolean / select / roles / textarea / json / input`, va bo sung `resolveFieldPartialView()` de Blade khong con giu nhanh `if/elseif` lon cho tung field type
    - da tiep tuc dua `IntegrationSettings` sang `providerPanels`, de provider definition + support state (`actions`, `guide_partial`) duoc hop nhat truoc khi render thay vi Blade tu ghep `getProviders()` voi `providerSupportPanels`
    - da tiep tuc nang `IntegrationSettings` len them `rendered_fields` trong `providerPanels`, de provider partial khong con tu tuyen `statePath` hay goi `resolveFieldPartialView()` ngay trong Blade ma chi render field payload da duoc page state chuan hoa
    - da tiep tuc nang `IntegrationSettings` len `pre_form_sections` va `post_form_sections`, de shell page loop section payload cho `secret rotation`, `provider health`, va `audit log` thay vi giu cac block `x-filament::section` lap tay quanh form
    - da tiep tuc nang `IntegrationSettings` len `provider_sections`, de shell page loop section payload cho tung provider thay vi goi truc tiep raw `providers` + include trong form shell; dong thoi provider partial khong con tu wrap `x-filament::section`
    - da tiep tuc gom shell `x-filament::section` lap lai cua `IntegrationSettings` va `OpsControlCenter` vao partial chung `control-plane-section`, de cac page control-plane chi con loop section payload thay vi lap lai renderer `heading / description / include_data`
    - da tiep tuc lam gon `IntegrationSettings` page-state: bo cac key adapter khong con consumer thuc te (`read_only_notice`, `secret_rotation_notice`, `control_plane`, `providers`) va bo `support` legacy khoi `providerPanels`, de state chi con phan anh contract dang render
    - checkpoint `2026-04-02` da tiep tuc ha `noticePanels`, `preFormSections`, `providerSections`, `postFormSections`, va cac notice/submit adapters lien quan xuong helper noi bo, de `pageViewState` giu vai tro shell contract duy nhat ma Blade va regression can dung
    - checkpoint `2026-04-02` da tiep tuc ha `renderedRecentLogs`, `renderedActiveSecretRotations`, `renderedProviderHealthCards`, va cac panel `secretRotation / providerHealth / auditLog` xuong helper noi bo, de regression khoa truc tiep panel payload trong `pageViewState` thay vi tiep tuc neo vao computed adapters cua page
    - checkpoint `2026-04-02` da tiep tuc ha `providerActionGroups` va `providerSupportPanels` xuong helper noi bo, de regression khoa `providerPanels/support_sections` la render contract thuc te thay vi computed adapters trung gian
    - checkpoint `2026-04-03` da tiep tuc chuyen `IntegrationSettings` sang computed shell `pageViewState`, de page control-plane nay khop hon voi Livewire 3 va regression manual-object chi con khoa method shell/public contract
    - checkpoint `2026-04-04` da chot them lane shell/presentation contract cho `IntegrationSettings`, `OpsControlCenter`, va `ZaloZns`: `provider-health`, `dashboard summary`, `control-plane sections`, `field renderers`, `grace rotations`, va `OPS detail cards` da dung partial/state chung; `IntegrationSettings`, `OpsControlCenter`, va `ZaloZns` da tiep tuc giu `pageViewState()` / `dashboardViewState()` la shell contract chinh, giam them adapter state va markup lap
    - da tiep tuc dua `ZaloZns` tu state phang sang panel-state (`summary_panel`, `provider_health_panel`, `triage_panel`, `guidance_panel`), de page triage dung cung pattern view-state voi `IntegrationSettings` / `OpsControlCenter`
    - da tiep tuc gom hai khung note cua `ZaloZns` (`Triage nhanh`, `Gợi ý xử lý`) vao partial chung `control-plane-note-panel`, de page ZNS triage khong con giu hai block markup tach biet trong Blade
    - da tiep tuc nang `ZaloZns` len `note_panels`, de dashboard partial co the loop note payload theo cung contract thay vi van phai giu adapter rieng cho `triage_panel` va `guidance_panel`
    - da tiep tuc nang `ZaloZns` len `dashboard_sections`, de dashboard partial loop section payload cho `summary`, `provider health`, `note panels`, va `table` thay vi giu knowledge truc tiep ve cac khung nay trong mot Blade duy nhat
    - da tiep tuc nang `ZaloZns` len `dashboard_section`, de shell page nay dung cung partial `control-plane-section` voi `IntegrationSettings` / `OpsControlCenter` thay vi giu wrapper `x-filament::section` rieng cho dashboard triage
    - da tiep tuc lam gon `ZaloZns` page-state: `dashboardViewState` nay da bo cac key top-level trung lap va giu `dashboard_section` la contract duy nhat cho page shell
    - checkpoint `2026-04-02` da tiep tuc tach builder `summary / provider readiness / note panels / dashboard section` cua `ZaloZns` thanh helper noi bo, de `dashboardViewState` giu shell gon va page khong con giu mot computed method qua day dac
    - checkpoint `2026-04-03` da tiep tuc chuyen `ZaloZns` sang computed shell `dashboardViewState`, de page triage khong con giu getter legacy va dung cung pattern shell voi `OpsControlCenter`
    - da tiep tuc dua `OpsControlCenterService` sang `dashboardSummaryCards()` cua `ZnsOperationalReadModelService`, de cockpit `ZNS triage` dung chung `value_label` + card-class contract voi `ZaloZns` thay vi tu `number_format()` va tone-badge lai trong Blade
    - da tiep tuc dua lane `KPI freshness` cua `OpsControlCenterService` sang `renderedSnapshotCountCards()` cua `OperationalKpiSnapshotReadModelService` va `aggregate_readiness_cards`, de OPS cockpit khong con tu format `snapshot count` / `aggregate readiness` badge ngay trong Blade
    - da tiep tuc dua `OpsControlCenter` sang rendered panel-state (`renderedIntegrationsPanel`, `renderedKpiPanel`, `renderedFinancePanel`, `renderedZnsPanel`, `renderedObservabilityPanel`, `renderedGovernancePanel`), de page/view cockpit khong con cham truc tiep vao raw state arrays cua service
    - da tiep tuc gom `OPS cockpit presentation` vao partial chung `section-summary-banner`, `signal-badge-card`, va `retention-candidate-card`, de `Integrations`, `KPI`, `Finance`, `ZNS`, va `Governance` dung chung renderer cho summary/signal/retention cards thay vi lap lai badge markup trong Blade
    - da tiep tuc dua `OpsControlCenter` sang `dashboardViewState`, de overview, automation actor, backup/readiness artifacts, va cac panel control-plane doc tu mot state hop nhat thay vi Blade goi getter rieng cho tung khuc
    - da tiep tuc nang `OpsControlCenter` len `primary_sections`, de cot trai cua cockpit loop section payload (`Automation actor`, `Backup & restore`, `Readiness artifacts`) thay vi giu 3 block `x-filament::section` lap tay trong page shell; dong thoi `Backup` va `Readiness` da duoc tach them qua partial rieng
    - da tiep tuc nang `OpsControlCenter` len `secondary_sections`, de cot phai cua cockpit loop section payload (`Integrations`, `KPI`, `Finance`, `ZNS`, `Observability`, `Governance`, `Smoke pack`, `Recent runs`) thay vi giu 8 block `x-filament::section` lap tay trong page shell
    - da tiep tuc lam gon `OpsControlCenter` page-state: `dashboardViewState` nay da bo cac panel top-level trung lap va giu `overview_cards`, `primary_sections`, `secondary_sections` la shell contract thuc te
    - checkpoint `2026-04-02` da tiep tuc ha `primaryColumnSections` va `secondaryColumnSections` xuong helper noi bo, de `dashboardViewState` giu vai tro shell duy nhat ma page shell va regression can dung
    - checkpoint `2026-04-03` da tiep tuc chuyen `OpsControlCenter` sang computed shell `dashboardViewState`, de regression manual-object chi con khoa method shell va cockpit page-state khop pattern voi `ZaloZns` / `IntegrationSettings`
    - da tiep tuc chuan hoa `active_grace` va `expired_grace` tren `OpsControlCenter` thanh render-ready item payload (`display_name / detail_text / card_classes`) va dua hai khung nay qua partial chung `grace-rotation-panel`, de cockpit khong con giu hai block markup gan nhu copy-paste
    - da tiep tuc dua `OpsControlCenter` sang `renderedAutomationActorPanel` va `renderedRuntimeBackupPanel`, dong thoi tach `Automation actor` va `Backup & restore` sang partial rieng de page/view khong con xu ly badge/meta/issues/path inline
    - da tiep tuc dua `OpsControlCenter` sang `renderedReadinessArtifactsPanel`, `renderedSmokePackPanel`, va `renderedRecentRunsPanel`, dong thoi tach `Readiness artifacts`, `Smoke pack`, va `Recent operator runs` sang partial chung `ops-artifact-list`, `ops-command-list`, va `ops-recent-runs-table`
    - da tiep tuc nang cap `renderedObservabilityPanel` va `renderedGovernancePanel` voi `metric_cards`, `breach_cards`, `missing_runbook_panel`, `scenario_user_panel`, va `recent_audit_panel`, dong thoi tach `Observability` va `Governance & audit scope` sang partial rieng de cockpit khong con giu card markup inline trong Blade
    - da tiep tuc nang cap `renderedKpiPanel` va `renderedFinancePanel` voi `open_alert_panel` va `watchlist_panel`, dong thoi tach `KPI freshness & alerts` va `Finance & collections` sang partial rieng de cockpit khong con giu alert/watchlist card markup inline trong Blade
    - da tiep tuc tach `Integrations & secret rotation` va `ZNS triage cockpit` sang partial rieng `ops-integrations-panel` va `ops-zns-panel`, de page shell khong con giu provider-health/grace/retention/link loops va ZNS summary/retention/link loops inline
    - da mo rong tiep lane `secret rotation / revoke` bang `expiredGraceRotationSummary()` trong `IntegrationOperationalReadModelService`, de `integrations:revoke-rotated-secrets` preview expired keys qua cung reader voi OPS va Integration Settings, thay vi command tu ngầm doc trang thai grace tu write-side service
    - da mo rong tiep lane `payload retention / pruning` bang cach dua `popups:prune` va `photos:prune` qua `IntegrationOperationalReadModelService`, de command layer khong con tu sao chep retention candidate rules cua popup logs va patient photos
    - da mo rong tiep lane `payload retention / pruning` sang `EMR clinical media`, de `emr:prune-clinical-media` va OPS cung doc mot retention contract cho `temporary` / `clinical_operational`
    - da mo rong tiep lane `provider run/readiness` sang `emr:check-dicom-readiness`, de DICOM/PACS readiness gate duoc surfacing nhu mot provider card first-class va command nay di vao tracked OPS automation catalog thay vi ton tai rieng le ben ngoai provider-health contract
    - da mo rong tiep lane `provider run/readiness` va `maintenance visibility` sang bo `EMR maintenance commands`, de `emr:sync-events`, `emr:reconcile-integrity`, `emr:reconcile-clinical-media`, va `emr:prune-clinical-media` duoc surfacing nhat quan trong tracked OPS automation catalog va smoke pack
    - da mo rong tiep lane `provider run/readiness` sang `google-calendar:sync-events`, de Google Calendar sync lane khong bi tach khoi tracked OPS automation catalog va smoke pack khi so voi `EMR` / `ZNS`
    - da mo rong tiep lane `release gate / readiness verification` bang `OpsReleaseGateCatalog`, de checklist release production va strict readiness signoff dung chung mot source of truth cho required gates
    - da mo rong tiep lane `provider-health/readiness` sang `Web Lead API`, de token inbound, default branch drift, realtime notify roles, va runtime mailer noi bo duoc surfacing chung tren `IntegrationSettings` va `OpsControlCenterService`
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
