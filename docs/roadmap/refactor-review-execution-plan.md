# Refactor and Review Execution Plan

Tai lieu nay chuyen backlog sau baseline thanh chuoi phase co the trien khai tuan tu, an toan voi repo dang co co che auto-deploy khi push.

## Metadata

- Backlog source: `docs/roadmap/refactor-review-master-backlog.md`
- Audit source: `docs/reviews/program-audit-summary.md`
- Last updated: `2026-04-03`

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
  - `RRB-009` da mo rong them sang `PatientTreatmentPlanSection` voi `PatientTreatmentPlanDraftService`, dua `prepare draft`, `sync diagnosis tu latest exam`, `resolve latest-or-create plan`, va `persist draft items` ra khoi Livewire component de dung chung workflow path cho tab `Khám & Điều trị`.
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
  - `RRB-009` da mo rong them `IntegrationProviderRuntimeGate` sang inbound `ValidateWebLeadToken`, `ValidateInternalEmrToken`, va `ZaloWebhookController`, de Web Lead API / EMR internal API / Zalo webhook ingress dung chung contract `skip / fail / ready` cho `enabled/token/secret configured` thay vi middleware/controller tu lap lai runtime gate rieng.
  - `RRB-010` da co read-model dau tien cho patient operational timeline ben canh `ClinicalAuditTimelineService`, va da cover them `PlanItem`, `ReceiptExpense`, `MaterialIssueNote`, `BranchTransferRequest`, va mo ta finance timeline than thien hon cho `Payment`.
  - `RRB-010` da mo rong sang `ReportSnapshot` reader conventions bang `OperationalKpiSnapshotReadModelService`, va da dua `OperationalKpiPack` cung `OpsControlCenter` dung chung latest snapshot date, SLA counts, filtered latest snapshot, va branch benchmark summary.
  - `RRB-010` da mo rong them lane `OperationalKpiAlertReadModelService` de hop nhat open/resolved KPI alert counts va open-alert summary giua `OperationalKpiPack` va `OpsControlCenter`.
  - `RRB-010` da tiep tuc dua `ops:check-observability-health` vao cung KPI reader contract, de `open_kpi_alerts` va `snapshot_sla_violations` khong con tu query raw tach rieng.
  - `RRB-010` da keo them write-side `OperationalKpiAlertService` vao reader contract cho phan active-alert counting sau snapshot evaluation.
  - `RRB-010` da mo rong them `OperationalAutomationAuditReadModelService` de gom tracked control-plane audit query cho `OpsControlCenterService` va `CheckObservabilityHealth`.
  - `RRB-010` da mo rong them `ReportSnapshotReadModelService` de gom generic snapshot lookup cho `CheckSnapshotSla` va `CompareReportSnapshots`.
  - `RRB-010` da mo rong them `PatientActivityTimelineReadModelService` de hop nhat appointment / treatment plan / invoice / payment / note / branch-log entries voi `PatientOperationalTimelineService` va `ClinicalAuditTimelineService`, dua `PatientActivityTimelineWidget` ve dung vai tro render read-model.
  - `RRB-010` da bo sung `PatientOverviewReadModelService` de `PatientOverviewWidget`, payment summary, tab counters, treatment progress, day summaries, material usages, factory orders, material issue notes, latest printable forms, va CTA `medical record` trong `ViewPatient` / `PatientExamForm` dung chung patient workspace aggregate contract, loai bo them mot lop invoice/payment/appointment/treatment-plan/tab-count/treatment-progress/lab-material/printable-form/EMR CTA query va permission wiring khoi widget/page/livewire layer.
  - `RRB-010` da mo rong them `PatientOverviewReadModelService` sang contract capability/tab visibility cua `ViewPatient`, de show-hide tabs `prescriptions / appointments / payments / forms / care / lab-materials` va create CTAs branch-scoped khong con nam rai rac trong page class.
  - `RRB-010` da bo sung `PatientAppointmentActionReadModelService`, de action `Xem lịch hẹn` tren `PatientsTable` dung chung contract `hasActiveAppointments` va `activeAppointmentOptions` thay vi query `Appointment` lap lai trong `visible` va `options`.
  - `RRB-010` da bo sung `PatientExamStatusReadModelService`, de `PatientExamForm` doc `treatmentProgressDates` va `toothTreatmentStates` qua mot read-model service, giu lai precedence state cho rang da co treatment progress / treatment session ma khong con query `TreatmentProgressDay`, `TreatmentSession`, va `PlanItem` truc tiep trong Livewire component.
  - `RRB-010` da bo sung `PatientExamMediaReadModelService`, de `PatientExamForm` doc `mediaTimeline`, `mediaPhaseSummary`, va `evidenceChecklist` qua mot read-model service, giu signed URL generation, missing evidence labels, va quality warnings trong service thay vi nam truc tiep trong `render()`.
  - `RRB-010` da bo sung `PatientExamReferenceReadModelService`, de `PatientExamForm` doc `ToothCondition` payload va `otherDiagnosisOptions` qua mot read-model service, bo query/map `conditionsJson`, `conditionOrder`, va `Disease` options khoi `render()`.
  - `RRB-010` da bo sung `PatientExamSessionReadModelService`, de `PatientExamForm` doc danh sach exam sessions va co `is_locked` thong nhat qua mot read-model service, bo loop `lockedDates` + `setAttribute('is_locked')` khoi `render()` va giu cung rule khoa cho lane `startEditingSession`.
  - `RRB-009` da bo sung `PatientExamClinicalNoteWorkflowService`, de `PatientExamForm` dung service cho `draftForSession`, `buildPayload`, `ensurePersisted`, `saveForSession`, va `optimistic update` cua clinical note, bo cac helper `makeDraftClinicalNoteForSession`, `ensurePersistedClinicalNote`, `buildClinicalNotePayload`, lane `saveData()`, va wiring `EncounterService` / `ClinicalNoteVersioningService` khoi Livewire component.
  - `RRB-010` da bo sung `PatientExamIndicationStateService`, de `PatientExamForm` dung chung normalize/toggle contract cho `indications`, `indication_images`, va `tempUploads`, bo stale upload cleanup + key normalization khoi component.
  - `RRB-009` da bo sung `PatientExamMediaWorkflowService`, de `PatientExamForm` dung service cho `createAsset`, `removeAsset`, va `storeUploads`, bo lane persist/archive `ClinicalMediaAsset` + `ClinicalMediaVersion`, checksum/size/mime lookup, temp-upload storage, va file cleanup khoi Livewire component.
  - `RRB-009` da bo sung `PatientExamSessionWorkflowService`, de cac lane `deleteSession`, `createSession`, va `saveEditingSession` cua `PatientExamForm` di qua workflow service va giu rule `locked by treatment progress`, `da phat sinh chi dinh / don thuoc`, `duplicate session date`, va `reschedule sync cho clinical note / standalone encounter` ngoai Livewire component.
  - `RRB-010` da mo rong `PatientOverviewReadModelService` sang payment-summary presentation contract (`formatted totals` + `create_payment_url`), de `ViewPatient` chi con cache/read payment widget state thay vi tu format va build action URL trong page.
  - `RRB-010` da bo sung `PatientExamDoctorReadModelService`, de `PatientExamForm` doc assignable doctor options va doctor-name lookup qua mot read-model service, bo query `scopeAssignableDoctors` va `User::find()` khoi component.
  - `RRB-010` da bo sung `OperationalStatsReadModelService` de `OperationalStatsWidget` dung chung aggregate contract cho `new customers today`, `appointments today`, va `pending confirmations`, dong thoi scope theo branch-access thay vi de widget tu query ad-hoc.
  - `RRB-010` da bo sung `PatientTreatmentPlanReadModelService` de `PatientTreatmentPlanSection` dung chung contract cho `plan items`, diagnosis map/options/details, service-category picker, service search, va financial totals, giam mot cum query/tong hop dang nam truc tiep trong Livewire component cua tab `Khám & Điều trị`; batch tiep theo da tach them draft mutation lane sang `PatientTreatmentPlanDraftService` de phan workflow va phan read-model khong con chen nhau trong component.
  - `RRB-010` da don `RiskScoringDashboard` ve shared branch-filter contract, bo raw `whereHas(patient.first_branch_id)` khoi page filter callback va de selected branch duoc resolve qua filter state + `PatientInsightReportReadModelService`, giam mot lop branch-scope duplication tren KPI report surface.
  - `RRB-010` da bo sung `CustomerCareSlaReadModelService` de `CustomerCare` dung chung read-model contract cho summary SLA, ownership buckets, va channel/branch/staff breakdown, giam them aggregate query dang nam trong page class.
  - `RRB-010` da mo rong them `GovernanceAuditReadModelService` de gom recent governance-relevant audits va branch-visible filtering cho `OpsControlCenterService`.
  - `RRB-010` da mo rong them `ZnsOperationalReadModelService` de hop nhat summary-card counts, retention candidate rules, bao gom ca `automation log retention`, va phan ZNS backlog/retention/prune count duoc tai su dung boi `OpsControlCenterService`, `CheckObservabilityHealth`, va `zns:prune-operational-data`.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` de hop nhat web-lead retention candidates, lead-mail retry/dead backlog, webhook retention, EMR retention, va Google Calendar retention counts cho `OpsControlCenterService`, `ops:check-observability-health`, va `integrations:prune-operational-data`, loai bo raw integration counting/prune rules khoi cockpit service va release gate command.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` va `ZnsOperationalReadModelService` vao `sync:emr`, `sync:google-calendar`, va `sync:zns` cho scoped dead-letter backlog counts, tiep tuc loai bo raw command query khoi outbox sync lanes.
  - `RRB-010` da tiep tuc dua integration secret grace-state vao read-model layer: `OpsControlCenterService` va `IntegrationSettings` da doc active/expired grace rotations thong qua `IntegrationOperationalReadModelService` thay vi tro truc tiep vao write-side rotation service.
  - `RRB-010` da bo sung `PopupAnnouncementCenterReadModelService` de `PopupAnnouncementCenter` doc pending delivery, active delivery, va formatted announcement payload qua mot read-model service thay vi giu raw query + payload mapping trong Livewire component.
  - `RRB-009` da bo sung `PopupAnnouncementDeliveryWorkflowService`, de `PopupAnnouncementCenter` khong con goi `markSeen / markAcknowledged / markDismissed` truc tiep tren model, dong thoi `PopupAnnouncementDispatchService` da dung chung workflow service nay cho lane `expire ended deliveries`.
  - `RRB-010` da tiep tuc don gian hoa `ViewPatient`, `PopupAnnouncementCenter`, va `RiskScoringDashboard` o lop render-state: `payments`, `forms`, `basic-info`, `workspace tabs`, `lab-material sections`, `header actions`, `popup action payload`, va `risk badge/stat formatting` dang duoc dua ve read-model/model helpers thay vi page/view/service lap lai adapter mapping.
  - `RRB-010` da tiep tuc keo `ViewPatient` ve adapter mong hon: `renderedWorkspaceTabs`, `renderedPaymentPanel`, `renderedFormsPanel`, `renderedTreatmentProgressPanel`, `renderedLabMaterialSections`, va `overviewCardPayload` da di qua `PatientOverviewReadModelService`, de page class khong con tu assemble render-state cho patient workspace.
  - `RRB-010` da bo sung them `workspaceViewState()` trong `PatientOverviewReadModelService`, de `ViewPatient` va Blade doc chung mot patient-workspace state hop nhat (`overview card`, `basic-info`, `tabs`, `payments`, `forms`, `treatment progress`, `lab-materials`) thay vi cache/goi reader rieng cho tung surface.
  - `RRB-010` da tiep tuc lam gon `ViewPatient` page-state: bo them mot lop proxy getter chi doc lai tung key tu `workspaceViewState`, de test va page class khoa truc tiep contract read-model thuc te thay vi duy tri adapter trung gian cho `overview card`, `basic-info panels`, `rendered tabs`, `payments`, `forms`, `treatment progress`, va `lab-material sections`.
  - `RRB-010` da tiep tuc lam gon `ViewPatient` adapter layer: bo them proxy `tabs` va `tabCounters`, de visible-tab logic doc truc tiep `workspaceViewState['tabs']` va regression khoa `PatientOverviewReadModelService::tabCounters()` thay vi page getters khong con consumer thuc te.
  - checkpoint `2026-04-03` da tiep tuc chuyen `ViewPatient` sang computed shell `workspaceViewState`, dong thoi regression manual-object da doi sang goi method shell truc tiep de khop voi runtime Livewire 3 thay vi tiep tuc bam vao `getWorkspaceViewStateProperty()` legacy.
  - `RRB-010` da bo sung them `centerViewState()` trong `PopupAnnouncementCenterReadModelService`, de wrapper view cua `PopupAnnouncementCenter` chi con render mot popup-center state hop nhat cho polling va active announcement thay vi tu cham vao cac bien Livewire/payload roi rac.
  - `RRB-010` da tiep tuc lam gon `PopupAnnouncementCenter` view-state: `centerViewState()` nay da bo key `has_active_announcement` va giu shell `announcement + polling_interval` la contract thuc te giua component, read-model, va Blade.
  - checkpoint `2026-04-03` da tiep tuc chuyen `PopupAnnouncementCenter` sang computed shell `viewState`, de component nay dong nhat hon voi `ViewPatient` o lop page/component state va khong con giu `getViewStateProperty()` legacy.
  - checkpoint `2026-04-03` da tiep tuc chuyen `CustomerCare` sang computed shell `slaSummary`, dong thoi chot `ConversationInbox` sang cac computed shell `conversationList`, `selectedConversation`, `branchOptions`, `assignableStaffOptions`, `conversationAssigneeOptions`, `handoffPriorityOptions`, `handoffStatusOptions`, `inboxTabOptions`, va `inboxStats`; sau batch nay, `app/Filament` + `app/Livewire` khong con public `get*Property()` legacy nao trong lane dang refactor.
  - `RRB-010` da mo rong them `ZnsOperationalReadModelService` vao page `ZaloZns` cho branch-scoped operational summary cards, `campaigns_running`, va `provider_status_code` option list, de page khong con tu dem ad-hoc raw query cho pending/retry/dead delivery, campaign state, hay provider-status filter values.
  - `RRB-010` da bo sung `IntegrationSettingsAuditReadModelService` de page `IntegrationSettings` dung reader chung cho recent setting logs, tiep tuc loai bo raw audit-query khoi page logic.
  - `RRB-010` da bo sung `IntegrationProviderHealthReadModelService` de `OpsControlCenterService` render provider-readiness cho `Zalo OA`, `ZNS`, `Google Calendar`, `EMR`, va `DICOM / PACS` bang contract chung thay vi page/service/command tu dien giai tung provider rieng.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` sang lane `popups:prune` va `photos:prune`, de popup logs va patient photos retention candidate query khong con bi lap lai o command layer.
  - `RRB-010` da bo sung `OperationalAutomationAuditReadModelService` them `popups:dispatch-due`, `popups:prune`, va `photos:prune`, de recent OPS runs va tracked command catalog khop voi scheduler wrapper catalog.
  - `RRB-010` da mo rong them `OpsControlCenterService` de hien thi `Popup announcement logs` va `Patient photos` tu cung `IntegrationOperationalReadModelService`, giu prune backlog tren OPS dong bo voi command layer.
  - `RRB-010` da nang `OperationalAutomationAuditReadModelService` len wrapper-aware contract bang cach doc ca `metadata->target_command`, de cac command duoc chay qua `ops:run-scheduled-command` khong bi vo hinh trong recent OPS runs.
  - `RRB-010` da mo rong them `IntegrationOperationalReadModelService` va `OpsControlCenterService` sang lane `EMR clinical media`, de retention backlog theo `temporary` / `clinical_operational` khop giua `emr:prune-clinical-media`, OPS, va control-plane summary.
  - `RRB-010` da tiep tuc dong bo `emr:check-dicom-readiness` vao `OperationalAutomationAuditReadModelService`, de DICOM readiness gate xuat hien trong recent OPS runs va observability control-plane catalog thay vi ton tai rieng le ngoai reader contract.
  - `RRB-010` da mo rong them tracked automation catalog va OPS smoke pack sang `emr:sync-events`, `emr:reconcile-integrity`, `emr:reconcile-clinical-media`, va `emr:prune-clinical-media`, de bo EMR maintenance commands da co trong scheduler/release gates duoc surfacing dong nhat trong cockpit.
  - `RRB-010` da mo rong them tracked automation catalog va OPS smoke pack sang `google-calendar:sync-events`, de Google Calendar sync lane cung xuat hien nhat quan trong recent OPS runs va command pack cua cockpit.
  - `RRB-010` da gom `tracked commands` va `smoke commands` vao `OpsAutomationCatalog`, de OPS cockpit reader va smoke pack dung chung mot command catalog thay vi drift theo hai danh sach rieng.
  - `RRB-010` da mo rong `OpsAutomationCatalog` sang scheduler definitions, de `routes/console.php` va `SchedulerHardeningCommandTest` doc chung target catalog cho wrapper automations thay vi lap lai danh sach command.
  - `RRB-010` da tao `OpsReleaseGateCatalog`, de `RunReleaseGates` va `VerifyProductionReadinessReport` dung chung release-gate contract cho required commands thay vi drift giua execute-side va verify-side.
  - `RRB-012` da bat dau lane hot-report aggregate convergence bang `HotReportAggregateReadModelService`, de `RevenueStatistical`, `CustomsCareStatistical`, va `TrickGroupStatistical` dung chung readiness, aggregate breakdown query, live fallback breakdown/summary, va summary stats contract tren `report_revenue_daily_aggregates` / `report_care_queue_daily_aggregates`.
  - `RRB-012` da mo rong them lane finance report convergence bang `FinancialReportReadModelService`, de `RevenueExpenditure` va `OwedStatistical` dung chung cashflow/invoice-balance query + summary contract thay vi moi page tu giu branch-scoped stats logic.
  - `RRB-012` da mo rong them lane financial dashboard convergence bang `FinancialDashboardReadModelService`, de `RevenueOverviewWidget`, `OutstandingBalanceWidget`, `QuickFinancialStatsWidget`, `MonthlyRevenueChartWidget`, `PaymentMethodsChartWidget`, `PaymentStatsWidget`, va heading summary cua `OverdueInvoicesWidget` dung chung payment/invoice aggregate contract thay vi moi widget tu giu branch-scoped query logic.
  - `RRB-012` da mo rong them `FinanceOperationalReadModelService` de `OpsControlCenterService` dung chung finance aging / overdue sync / dunning / reversible-receipt watchlist contract, thay vi cockpit service tu query finance watchlist summary rieng.
  - `RRB-012` da mo rong them lane patient insight report convergence bang `PatientInsightReportReadModelService`, de `PatientStatistical` va `RiskScoringDashboard` dung chung patient-breakdown / risk-summary contract thay vi giu query/stats branch-scoped rieng trong tung page.
  - `RRB-012` da mo rong them lane inventory/supplier report convergence bang `InventorySupplyReportReadModelService`, de `MaterialStatistical` va `FactoryStatistical` dung chung material-inventory / factory-order query + summary contract thay vi giu branch-scoped stats logic trong page class, dong thoi da dong branch-filter option leak tren `FactoryStatistical`.
  - `RRB-012` da mo rong them lane appointment report convergence bang `AppointmentReportReadModelService`, de `AppointmentStatistical` dung chung appointment-query / visit-episode metrics summary contract thay vi giu query/stats branch-scoped rieng trong page.
  - `RRB-012` da mo rong them `AppointmentReportReadModelService` sang `CalendarAppointments`, de weekly operational status metrics tren page calendar khong con tu giu branch-scoped count query rieng trong page class.
  - `RRB-013` da tiep tuc lane config/runtime settings sang publisher-side: `ZnsAutomationEventPublisher`, `GoogleCalendarSyncEventPublisher`, va `EmrSyncEventPublisher` khong con enqueue them event/outbox moi khi provider runtime dang drift hoac thieu credential.
  - `RRB-013` da bo sung `IntegrationProviderRuntimeGate`, de command/publisher/runner lanes `EMR / Google Calendar / ZNS` dung chung contract `skip / fail / ready`, thay vi moi lane tu lap lai check `enabled`, `sync mode`, hay `runtime_error_message`.
  - `RRB-013` da mo rong them `IntegrationProviderRuntimeGate` sang inbound `ValidateWebLeadToken`, `ValidateInternalEmrToken`, va `ZaloWebhookController`, de Web Lead API / EMR internal API / Zalo webhook ingress cung dung contract `skip / fail / ready` cho `enabled/token/secret configured`.
  - `RRB-013` da bo sung `IntegrationProviderActionService`, de `IntegrationSettings` dung chung contract `test connection / readiness / config-url` cho `EMR`, `Google Calendar`, `Zalo OA`, va `ZNS`, tiep tuc loai bo page-side message formatting rieng le.
  - `RRB-013` da mo rong them `IntegrationProviderActionService` sang `DICOM / PACS` va `Web Lead API`, de readiness button tren `IntegrationSettings` dong bo voi provider-health card/read-model thay vi de hai provider nay dung ngoai action contract chung.
  - `RRB-013` da bo sung presentation payload cho `IntegrationProviderHealthReadModelService`, de `IntegrationSettings` render provider-health snapshot qua `renderedProviderHealthCards` thay vi giu tone-class mapping, meta slicing, va runtime issue fallback trong Blade.
  - `RRB-013` da tiep tuc dua `IntegrationSettings` sang panel-state `secretRotationPanel / providerHealthPanel / auditLogPanel`, de page va Blade doc mot lop control-plane view-state ro nghia thay vi cham truc tiep vao nhieu getter `rendered*`.
  - `RRB-013` da mo rong `IntegrationProviderActionService` sang notification payload contract (`status/title/body`) cho `EMR`, `Google Calendar`, va readiness checks; `IntegrationSettings` page nay chi con dispatch notification payload da chuan hoa va page-action tests da khoa lai `google_calendar_account_email` + success/readiness notifications.
  - `RRB-013` da don them hai action page con sot trong `IntegrationSettings` (`openEmrConfigUrl`, `generateWebLeadApiToken`) ve `sendNotificationPayload()` thay vi `Notification::make()` inline, va da bo sung page-action tests cho warning/success notifications cua cac nut nay.
  - `RRB-013` da mo rong `IntegrationOperationalReadModelService` va `IntegrationSettingsAuditReadModelService` sang rendered payload cho `active grace rotations` va `recent setting logs`, de `IntegrationSettings` page va Blade chi con render read-model thay vi tu parse date/context ngay trong view.
  - `RRB-013` da mo rong tiep `IntegrationOperationalReadModelService` sang `renderedExpiredGraceRotations()`, de `OpsControlCenterService` va `IntegrationSettings` cung doc mot grace-rotation presentation contract cho ca active/expired tokens thay vi OPS tu format lai `grace_expires_at` / `expired_minutes` rieng.
  - `RRB-013` da mo rong tiep `OpsControlCenterService` sang `snapshotCards()` cua `IntegrationProviderHealthReadModelService`, de section `Provider readiness` tren OPS cockpit dung cung `status badge / summary badge / issue badge / meta preview / status message` contract voi `IntegrationSettings` thay vi giu raw provider card rendering rieng.
  - `RRB-013` da mo rong tiep `IntegrationOperationalReadModelService` sang `retentionCandidates()`, de `OpsControlCenterService` khong con giu label/retention/tone matrix rieng cho integration prune backlog (`web lead`, `webhook`, `EMR`, `Google Calendar`, `popup`, `patient photos`, `clinical media`) ma doc thang tu reader control-plane chung.
  - `RRB-013` da mo rong tiep `ZnsOperationalReadModelService` sang `retentionCandidates()`, de `OpsControlCenterService` khong con giu label/retention/tone matrix rieng cho `ZNS automation logs / events / deliveries` ma doc thang prune backlog presentation tu reader ZNS chung.
  - `RRB-013` da mo rong tiep `ZaloZns` page sang `snapshotCards()` cua `IntegrationProviderHealthReadModelService` va `dashboardSummaryCards()` cua `ZnsOperationalReadModelService`, de page triage ZNS doc cung provider-health presentation contract voi `IntegrationSettings` / `OPS` va bo 7 summary-card hardcode khoi Blade.
  - `RRB-013` da tiep tuc dua `ZaloZns` sang `dashboardViewState`, de page + Blade doc mot state hop nhat cho `summary cards`, `provider health`, `triage notes`, va `guidance notes` thay vi cham truc tiep vao nhieu getter presentation rieng le.
  - `RRB-013` da tiep tuc gom markup `provider health` vao partial chung cho `IntegrationSettings`, `ZaloZns`, va `OpsControlCenter`, de ba surface control-plane dung cung renderer cho `status/summary/issue badges`, `meta preview`, va `status message`.
  - `RRB-013` da tiep tuc dua `IntegrationSettings` sang `providerActionGroups`, de cac nut `readiness / test / open config / generate token` cua `Zalo OA`, `ZNS`, `Google Calendar`, `EMR`, va `Web Lead API` doc tu mot action contract chung thay vi giu `wire:click` hardcode theo tung provider trong Blade; dong thoi `secret rotation` cards va `audit log` table da duoc tach thanh shared partials.
  - `RRB-013` da tiep tuc dua `IntegrationSettings` sang `providerSupportPanels`, de provider action buttons va guide partials (`Zalo OA`, `Web Lead API`, `Popup`) doc tu mot support-state chung thay vi tiep tuc giu `if(provider group)` rieng trong Blade.
  - `RRB-013` da tiep tuc gom render action buttons cua `IntegrationSettings` vao partial chung `provider-action-buttons`, de page settings khong con giu markup button lặp trong vong lap provider.
  - `RRB-013` da tiep tuc tach renderer field cua `IntegrationSettings` thanh partial chung theo `boolean / select / roles / textarea / json / input`, va bo sung `resolveFieldPartialView()` de Blade khong con giu nhanh `if/elseif` lon cho tung field type.
  - `RRB-013` da tiep tuc dua `IntegrationSettings` sang `providerPanels`, de provider definition + support state (`actions`, `guide_partial`) duoc hop nhat truoc khi render thay vi Blade tu ghep `getProviders()` voi `providerSupportPanels`.
  - `RRB-013` da tiep tuc nang `IntegrationSettings` len them `rendered_fields` trong `providerPanels`, de provider partial khong con tu lap `statePath` hay goi `resolveFieldPartialView()` ngay trong Blade ma chi render field payload da duoc page state chuan hoa.
  - `RRB-013` da tiep tuc nang `IntegrationSettings` len `pre_form_sections` va `post_form_sections`, de shell page loop section payload cho `secret rotation`, `provider health`, va `audit log` thay vi giu cac block `x-filament::section` lap tay quanh form.
  - `RRB-013` da tiep tuc nang `IntegrationSettings` len `provider_sections`, de shell page loop section payload cho tung provider thay vi goi truc tiep raw `providers` + include trong form shell; dong thoi provider partial khong con tu wrap `x-filament::section`.
  - `RRB-013` da tiep tuc gom shell `x-filament::section` lap lai cua `IntegrationSettings` va `OpsControlCenter` vao partial chung `control-plane-section`, de cac page control-plane chi con loop section payload thay vi lap lai renderer `heading / description / include_data`.
  - `RRB-013` da tiep tuc lam gon `IntegrationSettings` page-state: bo cac key adapter khong con consumer thuc te (`read_only_notice`, `secret_rotation_notice`, `control_plane`, `providers`) va bo `support` legacy khoi `providerPanels`, de state chi con phan anh contract dang render.
  - `RRB-013` da tiep tuc ha `noticePanels`, `preFormSections`, `providerSections`, `postFormSections`, va cac notice/submit adapters lien quan xuong helper noi bo, de `pageViewState` giu vai tro shell contract duy nhat ma Blade va regression can dung.
  - `RRB-013` da tiep tuc ha `renderedRecentLogs`, `renderedActiveSecretRotations`, `renderedProviderHealthCards`, va cac panel `secretRotation / providerHealth / auditLog` xuong helper noi bo, de regression khoa truc tiep panel payload trong `pageViewState` thay vi tiep tuc neo vao computed adapters cua page.
  - `RRB-013` da tiep tuc ha `providerActionGroups` va `providerSupportPanels` xuong helper noi bo, de regression khoa `providerPanels/support_sections` la render contract thuc te thay vi computed adapters trung gian.
  - `RRB-013` da tiep tuc ha `providerPanels` xuong helper noi bo, de `IntegrationSettings` chi con expose `pageViewState` la shell contract thuc te; regression nay da chuyen sang derive provider payload truc tiep tu `pageViewState['provider_sections']` thay vi goi mot computed adapter rieng.
  - `RRB-013` da tiep tuc doi `ZaloZns` tu state phang sang panel-state (`summary_panel`, `provider_health_panel`, `triage_panel`, `guidance_panel`), de page triage dung cung pattern view-state voi `IntegrationSettings` / `OpsControlCenter`.
  - `RRB-013` da tiep tuc gom hai khung note cua `ZaloZns` (`Triage nhanh`, `Gợi ý xử lý`) vao partial chung `control-plane-note-panel`, de page triage ZNS khong con giu hai block markup tach biet trong Blade.
  - `RRB-013` da tiep tuc nang `ZaloZns` len `note_panels`, de dashboard partial co the loop note payload theo cung contract thay vi van phai giu adapter rieng cho `triage_panel` va `guidance_panel`.
  - `RRB-013` da tiep tuc nang `ZaloZns` len `dashboard_sections`, de dashboard partial loop section payload cho `summary`, `provider health`, `note panels`, va `table` thay vi giu knowledge truc tiep ve cac khung nay trong mot Blade duy nhat.
  - `RRB-013` da tiep tuc nang `ZaloZns` len `dashboard_section`, de shell page nay dung cung partial `control-plane-section` voi `IntegrationSettings` / `OpsControlCenter` thay vi giu wrapper `x-filament::section` rieng cho dashboard triage.
  - `RRB-013` da tiep tuc lam gon `ZaloZns` page-state: `dashboardViewState` nay da bo cac key top-level trung lap va giu `dashboard_section` la contract duy nhat cho page shell, de triage page khong con mang hai lop state song song.
  - `RRB-013` da tiep tuc tach builder `summary / provider readiness / note panels / dashboard section` cua `ZaloZns` thanh helper noi bo, de `dashboardViewState` giu shell gon va page khong con giu mot computed method qua day dac.
  - `RRB-013` da tiep tuc dua `OpsControlCenterService` sang `dashboardSummaryCards()` cua `ZnsOperationalReadModelService`, de OPS `ZNS triage` doc cung `value_label` + card-class contract voi `ZaloZns` thay vi tu `number_format()` va badge formatting lai trong Blade.
  - `RRB-010` da tiep tuc dua `KPI freshness` tren `OpsControlCenterService` sang `renderedSnapshotCountCards()` cua `OperationalKpiSnapshotReadModelService` va `aggregate_readiness_cards`, de OPS cockpit khong con tu format `snapshot count` / `aggregate readiness` badge trong Blade.
  - `RRB-013` da tiep tuc dua `OpsControlCenter` sang rendered panel-state (`renderedIntegrationsPanel`, `renderedKpiPanel`, `renderedFinancePanel`, `renderedZnsPanel`, `renderedObservabilityPanel`, `renderedGovernancePanel`), de page + Blade cockpit khong con cham truc tiep vao raw state arrays cua service.
  - `RRB-013` da tiep tuc gom `OPS cockpit presentation` vao partial chung `section-summary-banner`, `signal-badge-card`, va `retention-candidate-card`, de `Integrations`, `KPI`, `Finance`, `ZNS`, va `Governance` dung chung renderer cho summary/signal/retention cards thay vi lap lai badge markup trong Blade.
  - `RRB-013` da tiep tuc dua `OpsControlCenter` sang `dashboardViewState`, de overview, automation actor, backup/readiness artifacts, va cac panel control-plane doc tu mot state hop nhat thay vi Blade goi getter rieng cho tung khuc.
  - `RRB-013` da tiep tuc nang `OpsControlCenter` len `primary_sections`, de cot trai cua cockpit loop section payload (`Automation actor`, `Backup & restore`, `Readiness artifacts`) thay vi giu 3 block `x-filament::section` lap tay trong page shell; dong thoi `Backup` va `Readiness` da duoc tach them qua partial rieng.
  - `RRB-013` da tiep tuc nang `OpsControlCenter` len `secondary_sections`, de cot phai cua cockpit loop section payload (`Integrations`, `KPI`, `Finance`, `ZNS`, `Observability`, `Governance`, `Smoke pack`, `Recent runs`) thay vi giu 8 block `x-filament::section` lap tay trong page shell.
  - `RRB-013` da tiep tuc lam gon `OpsControlCenter` page-state: `dashboardViewState` nay da bo cac panel top-level trung lap va giu `overview_cards`, `primary_sections`, `secondary_sections` la shell contract thuc te, trong khi render getter van duoc giu lai de compose payload va khoa regression.
  - `RRB-013` da tiep tuc ha `primaryColumnSections` va `secondaryColumnSections` xuong helper noi bo, de `dashboardViewState` giu vai tro shell duy nhat ma cockpit shell va regression can dung.
  - `RRB-013` da tiep tuc ha toan bo `rendered*Panel` adapters cua `OpsControlCenter` xuong helper noi bo, de regression khoa payload trong `dashboardViewState['primary_sections'/'secondary_sections']` thay vi tiep tuc goi cac computed getters presentation cua page.
  - `RRB-013` da tiep tuc ha cac raw-state getters con lai cua `OpsControlCenter` (`overview cards`, `automation actor`, `runtime backup`, `backup fixtures`, `integrations`, `KPI`, `finance`, `ZNS`, `governance`, `observability`, `recent runs`, `smoke commands`) xuong helper noi bo, de page nay gan nhu chi con expose `dashboardViewState` la shell contract cong khai.
  - checkpoint `2026-04-03` da tiep tuc chuyen `OpsControlCenter`, `ZaloZns`, va `IntegrationSettings` sang computed shell (`dashboardViewState`, `pageViewState`) thay vi `get*Property` legacy, de control-plane pages dong nhat hon voi Livewire 3 va regression manual-object chi con khoa method shell/public contract.
  - `RRB-013` da tiep tuc chuan hoa `active_grace` va `expired_grace` tren `OpsControlCenter` thanh render-ready item payload (`display_name / detail_text / card_classes`) va dua hai khung nay qua partial chung `grace-rotation-panel`, de cockpit khong con giu hai block markup gan nhu copy-paste.
  - `RRB-013` da tiep tuc dua `OpsControlCenter` sang `renderedAutomationActorPanel` va `renderedRuntimeBackupPanel`, dong thoi tach `Automation actor` va `Backup & restore` sang partial rieng de page/view khong con xu ly badge/meta/issues/path inline.
  - `RRB-013` da tiep tuc dua `OpsControlCenter` sang `renderedReadinessArtifactsPanel`, `renderedSmokePackPanel`, va `renderedRecentRunsPanel`, dong thoi tach `Readiness artifacts`, `Smoke pack`, va `Recent operator runs` sang partial chung `ops-artifact-list`, `ops-command-list`, va `ops-recent-runs-table`.
  - `RRB-013` da tiep tuc nang cap `renderedObservabilityPanel` va `renderedGovernancePanel` voi `metric_cards`, `breach_cards`, `missing_runbook_panel`, `scenario_user_panel`, va `recent_audit_panel`, dong thoi tach `Observability` va `Governance & audit scope` sang partial rieng de cockpit khong con giu card markup inline trong Blade.
  - `RRB-013` da tiep tuc nang cap `renderedKpiPanel` va `renderedFinancePanel` voi `open_alert_panel` va `watchlist_panel`, dong thoi tach `KPI freshness & alerts` va `Finance & collections` sang partial rieng de cockpit khong con giu alert/watchlist card markup inline trong Blade.
  - `RRB-013` da tiep tuc tach `Integrations & secret rotation` va `ZNS triage cockpit` sang partial rieng `ops-integrations-panel` va `ops-zns-panel`, de page shell khong con giu provider-health/grace/retention/link loops va ZNS summary/retention/link loops inline.
  - `RRB-013` da tiep tuc lane `secret rotation / revoke`: `integrations:revoke-rotated-secrets` doc expired-grace preview tu `IntegrationOperationalReadModelService` de summary/audit cua command dung cung reader contract voi OPS va Integration Settings.
  - `RRB-013` da tiep tuc lane `payload retention / pruning`: `popups:prune` va `photos:prune` da dung chung retention candidate reader voi control-plane, tranh viec command layer va OPS/Integration surfaces dien giai retention theo nhieu cach.
  - `RRB-013` da mo rong them lane `payload retention / pruning` sang `EMR clinical media`, de `emr:prune-clinical-media` khong con giu candidate query rieng tach khoi control-plane retention reader.
  - `RRB-013` da mo rong them lane `provider run/readiness` sang `DICOM / PACS`, de provider-health snapshot tren `IntegrationSettings` va `OpsControlCenterService` dung cung readiness contract voi `emr:check-dicom-readiness`, tranh viec command gate va page health surface dien giai readiness imaging theo hai cach khac nhau.
  - `RRB-013` da mo rong them lane `maintenance visibility` sang bo EMR commands, de scheduler/release-gate lanes `sync / reconcile / prune / dicom readiness` khong con bi tach khoi tracked OPS automation catalog va smoke surface.
  - `RRB-013` da mo rong them lane `maintenance visibility` sang `google-calendar:sync-events`, de provider lane nay duoc surfacing dung muc uu tien van hanh nhu `EMR` va `ZNS`.
  - `RRB-013` da mo rong them lane `release gate / readiness verification`, de command execute-side va strict signoff verify-side dung cung mot release-gate catalog khi xac dinh checklist production bat buoc.
  - `RRB-013` da mo rong them provider-health/readiness lane sang `Web Lead API`, de inbound token, default branch drift, realtime notify roles, va runtime mailer noi bo cung dung contract health chung tren `IntegrationSettings` va `OpsControlCenterService`.
  - `CARE` da vao wave convergence qua `CareTicket/Note`; `OPS` van chua can wave rieng beyond baseline packs.
- Deploy safety note:
  - Lam tung workflow module mot.
  - Khong doi workflow contract cua nhieu module trong cung mot release.

## Phase 5 - Structural Refactor

- Status: `In progress`
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
- Progress update:
  - `RRB-013` da co `IntegrationProviderRuntimeGate` cho contract `skip / fail / ready` tren cac lane `EMR / Google Calendar / ZNS`.
  - `RRB-013` da mo rong them runtime gate nay sang inbound `Web Lead API`, `EMR internal API`, va `Zalo webhook` ingress gates.
  - `RRB-013` da co `IntegrationProviderActionService` de `IntegrationSettings` dung chung contract action/readiness cho `EMR`, `Google Calendar`, `Zalo OA`, va `ZNS`.
- Deploy safety note:
  - Chi nen lam tren branch/PR rieng, co feature flag hoac song song read-model neu can.

## Phase 6 - Performance and Reporting Hardening

- Status: `In progress`
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
- Progress update:
  - `RRB-012` da co `HotReportAggregateReadModelService`, `FinancialReportReadModelService`, `PatientInsightReportReadModelService`, `InventorySupplyReportReadModelService`, va `AppointmentReportReadModelService` cho cac page report branch-scoped.
  - `RRB-012` da mo rong them `ReportSnapshotComparisonService` va `ReportSnapshotSlaService`, de `CompareReportSnapshots` va `CheckSnapshotSla` khong con tu giu logic diff/SLA ad-hoc trong command class.
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
