# Program Audit Summary

## Metadata

- Scope: program-level synthesis cho toan bo 13 module CRM
- Canonical sources:
  - `docs/reviews/00-master-index.md`
  - `docs/reviews/modules/*.md`
  - `docs/reviews/issues/*.md`
  - `docs/reviews/plans/*.md`
- Canonical state rule:
  - Khi module review co ca phan review lich su va phan `Re-audit Summary`, `Re-audit Update`, hoac `Re-audit Outcome`, trang thai moi nhat va `00-master-index.md` la source of truth.
- Last updated: `2026-03-29`

## Executive Summary

- `13/13` module da dat `Clean Baseline Reached`.
- `100/100` issue baseline trong issue register da duoc danh dau `Resolved`.
- Khong con open code blocker co do tin cay cao trong baseline hien tai.
- Production rollout va real-infrastructure smoke-test pack da pass tren environment that ngay `2026-03-28`.
- Governance delegation matrix da duoc formalize thanh tai lieu van hanh.
- Trung tam rui ro da chuyen tu `rollout safety` sang `shared contracts`, `workflow convergence`, va `structural convergence`.
- Chuong trinh hien tai nen di tiep theo batch nho, uu tien rollout/smoke test truoc, refactor sau.
- `RRB-007` va `RRB-008` da duoc dong qua shared identity/search contract va actor/branch scope contract.
- `RRB-009` va `RRB-010` dang duoc trien khai theo batch nho tren branch lam viec; cac batch moi nhat da dua `Appointment`, `VisitEpisode`, `BranchTransferRequest`, `MasterPatientDuplicate / MasterPatientMerge`, `PopupAnnouncement`, `PlanItem`, `ReceiptExpense`, `MaterialIssueNote`, `InsuranceClaim`, `ClinicalOrder`, `ClinicalResult`, `CareTicket/Note`, `Consent`, `ExamSession / TreatmentProgress`, `InstallmentPlan`, `WebLeadEmailDelivery`, `ZnsCampaignDelivery`, cac lane outbox `ZnsAutomationEvent / GoogleCalendarSyncEvent / EmrSyncEvent`, `OperationalKpiSnapshotReadModelService`, `OperationalKpiAlertReadModelService`, `OperationalAutomationAuditReadModelService`, `ReportSnapshotReadModelService`, `PatientActivityTimelineReadModelService`, `GovernanceAuditReadModelService`, `ZnsOperationalReadModelService`, `IntegrationOperationalReadModelService`, `IntegrationSettingsAuditReadModelService`, `IntegrationProviderHealthReadModelService`, `IntegrationProviderRuntimeGate`, `IntegrationProviderActionService`, `OpsAutomationCatalog`, `OpsReleaseGateCatalog`, `HotReportAggregateReadModelService`, `FinancialReportReadModelService`, `FinancialDashboardReadModelService`, `FinanceOperationalReadModelService`, `OperationalStatsReadModelService`, `PatientOverviewReadModelService`, `PatientAppointmentActionReadModelService`, `PatientExamClinicalNoteWorkflowService`, `PatientExamDoctorReadModelService`, `PatientExamIndicationStateService`, `PatientExamStatusReadModelService`, `PatientExamMediaReadModelService`, `PatientExamMediaWorkflowService`, `PatientExamReferenceReadModelService`, `PatientExamSessionReadModelService`, `PatientExamSessionWorkflowService`, `PatientTreatmentPlanReadModelService`, `PatientTreatmentPlanDraftService`, `PatientInsightReportReadModelService`, `InventorySupplyReportReadModelService`, `AppointmentReportReadModelService`, `ReportSnapshotComparisonService`, `ReportSnapshotSlaService`, `PopupAnnouncementCenterReadModelService`, `PopupAnnouncementDeliveryWorkflowService`, va finance payment audit metadata vao workflow + audit contract; trong do `ExamSession / TreatmentProgress` da vao managed mutation path den cap `TreatmentProgressDay / TreatmentProgressItem`, `InstallmentPlan` da tach `syncFinancialState()` sang `InstallmentPlanLifecycleService` va keo `PaymentObserver`, `installments:run-dunning`, `installments:sync-status` vao canonical lifecycle path, `MasterPatientDuplicate` da co canonical workflow service cho ignore / merge-resolution / rollback-restore / auto-ignore tu MPI index, `BranchTransferRequest` da co them reader surface tren patient operational timeline, `WebLeadEmailDelivery` va `ZnsCampaignDelivery` da chuyen sang managed delivery mutation path, `PatientActivityTimelineWidget` da duoc doi sang render read-model hop nhat thay vi tu query du lieu truc tiep, `PatientOverviewReadModelService` da tiep tuc gom them tab counters, treatment progress, day summaries, material usages, factory orders, material issue notes, latest printable forms, CTA `medical record`, contract capability/tab visibility cho `ViewPatient`, va hien nay da gom them payment-summary presentation contract (`formatted totals` + `create_payment_url`) de page khong con tu build summary render-state; `PatientAppointmentActionReadModelService` da bat dau gom action `Xem lịch hẹn` tren `PatientsTable` ve contract `hasActiveAppointments` va `activeAppointmentOptions`, de table khong con query `Appointment` lap lai trong `visible` va `options`; `PatientExamDoctorReadModelService` da bat dau gom query selector / lookup ten cho bac si kham, bac si dieu tri trong `PatientExamForm`, de component khong con tu giu assignable-doctor query va `User::find()` cho name lookup; `PatientExamIndicationStateService` da bat dau gom normalize/toggle state cho `indications`, `indication_images`, va `tempUploads`, de `PatientExamForm` khong con tu giu key normalization, stale upload cleanup, va state reconciliation giua cac mang chi dinh trong component; `PatientExamStatusReadModelService` da bat dau gom `treatmentProgressDates` va `toothTreatmentStates` cho `PatientExamForm`, de lane `Khám bệnh` khong con tu query `TreatmentProgressDay / TreatmentSession / PlanItem` va tu map precedence trang thai rang ngay trong Livewire component; `PatientExamMediaReadModelService` da bat dau gom `mediaTimeline`, `mediaPhaseSummary`, va `evidenceChecklist` cho `PatientExamForm`, de Livewire component khong con tu query `ClinicalMediaAsset`, tao signed URL, va tu dien giai missing evidence / quality warnings trong `render()`; `PatientExamMediaWorkflowService` da bat dau gom `createAsset`, `removeAsset`, va `storeUploads` cho `PatientExamForm`, de component khong con tu giu lane persist/archive `ClinicalMediaAsset` + `ClinicalMediaVersion`, checksum/size/mime discovery, temp-upload storage, va file cleanup khi go anh chi dinh; `PatientExamReferenceReadModelService` da bat dau gom `ToothCondition` payload va `Disease` option lists cho `PatientExamForm`, de component khong con tu query/map `conditionsJson`, `conditionOrder`, va `otherDiagnosisOptions` ngay trong `render()`; `PatientExamSessionReadModelService` da bat dau gom danh sach sessions va co `is_locked` thong nhat cho `PatientExamForm`, de `render()` va `startEditingSession` khong con tu lap `lockedDates` va decorate session state tai cho; `PatientExamClinicalNoteWorkflowService` da bat dau gom `draftForSession`, `buildPayload`, `ensurePersisted`, `saveForSession`, va `optimistic update` cho `PatientExamForm`, de component khong con tu giu helper draft-note, payload builder, note persistence, va direct wiring `EncounterService` / `ClinicalNoteVersioningService`; `PatientExamSessionWorkflowService` da bat dau gom lane `deleteSession`, `createSession`, va `saveEditingSession` cua `PatientExamForm`, de rule `locked by treatment progress`, `da phat sinh chi dinh / don thuoc`, `duplicate session date`, va `reschedule sync cho clinical note / encounter` khong con nam truc tiep trong Livewire component; `PatientTreatmentPlanSection` da bat dau dung `PatientTreatmentPlanReadModelService` cho `plan items`, diagnosis map/options/details, service-category picker, service search, va financial totals, dong thoi nay da dua lane `prepare draft / sync diagnosis / resolve plan / persist draft items` vao `PatientTreatmentPlanDraftService` de Livewire component chi con orchestration va notifications; tiep theo `RiskScoringDashboard` da bo raw branch-filter query khoi page va chi dung shared filter state + `PatientInsightReportReadModelService` cho branch-scoped risk query, `PopupAnnouncementCenter` da bat dau doc pending delivery / active delivery / formatted announcement payload qua `PopupAnnouncementCenterReadModelService` va nay da dua ca `seen / acknowledge / dismiss / expire active deliveries` vao `PopupAnnouncementDeliveryWorkflowService` thay vi goi mutation truc tiep tren model, `OperationalStatsWidget` da duoc doi sang `OperationalStatsReadModelService` va branch-scope thong qua `BranchAccess`, `OpsControlCenterService` da tiep tuc mat raw governance audit query, raw ZNS state-count/automation-log retention/prune query, raw integration retention/backlog/prune query, raw recent setting-log query, raw provider-readiness drift query, raw finance aging/dunning/reversible-receipt watchlist query, va grace-state read query de chuyen sang reader rieng, `sync:emr` / `sync:google-calendar` / `sync:zns` da bat dau dung reader cho scoped dead-letter backlog counts, `ZaloZns` da bat dau dung reader cho branch-scoped operational summary cards, `campaigns_running`, `provider_status_code` option list, va provider-readiness snapshot, `IntegrationSettings` da bat dau dung provider-health reader chung cho readiness notifications va top-level snapshot, va nay da mo rong them provider-health lane sang `Web Lead API` cho inbound token, default branch drift, realtime notify roles, va runtime mailer noi bo, `ZnsAutomationEventPublisher` / `GoogleCalendarSyncEventPublisher` / `EmrSyncEventPublisher` da bat dau chan sinh them backlog moi khi provider runtime dang misconfigured, `IntegrationProviderRuntimeGate` da bat dau hop nhat contract `skip / fail / ready` cho `emr:sync-events`, `google-calendar:sync-events`, `zns:sync-automation-events`, `zns:run-campaigns`, `ZnsCampaignRunnerService`, va nay da mo rong them sang inbound `ValidateWebLeadToken`, `ValidateInternalEmrToken`, va `ZaloWebhookController` de Web Lead API / EMR internal API / Zalo webhook ingress khong con lap lai enabled/token/secret runtime gate rieng, `IntegrationProviderActionService` da bat dau hop nhat contract `test connection / readiness / config-url` cho cac nut action tren `IntegrationSettings`, dong thoi da mo rong them readiness action contract nay sang `DICOM / PACS` va `Web Lead API`, `integrations:revoke-rotated-secrets` da bat dau dung shared integration reader cho expired-grace preview de command/audit summary khop voi OPS va Integration Settings, `popups:prune` va `photos:prune` da bat dau dung `IntegrationOperationalReadModelService` cho retention candidate query thay vi tu mang query rieng, `emr:prune-clinical-media` da bat dau dung cung reader nay cho candidate query theo retention class, `OpsControlCenterService` da bat dau hien thi popup/photo retention backlog va clinical media retention backlog tu cung reader nay, `OperationalAutomationAuditReadModelService` da bat dau track `popups:dispatch-due`, `popups:prune`, `photos:prune`, `emr:sync-events`, `emr:reconcile-integrity`, `emr:reconcile-clinical-media`, `emr:check-dicom-readiness`, `emr:prune-clinical-media`, va `google-calendar:sync-events`, dong thoi doc ca `metadata->target_command` tu scheduler wrapper de recent OPS runs khop voi command catalog thuc te, `IntegrationProviderHealthReadModelService` da mo rong them provider card first-class cho `DICOM / PACS` va `Web Lead API`, `OpsAutomationCatalog` da gom `tracked commands`, `smoke commands`, va `scheduled automation targets` ve mot command catalog dung chung cho OPS cockpit + scheduler wrapper, `OpsReleaseGateCatalog` da gom release-gate steps va required production gate contract de execute-side va verify-side khong drift, `RevenueStatistical`, `CustomsCareStatistical`, va `TrickGroupStatistical` da bat dau dung chung hot-report aggregate reader cho readiness, aggregate breakdown query, summary stats, va nay da mo rong them live fallback breakdown/summary query de page khong con giu raw aggregate fallback logic, `RevenueExpenditure` va `OwedStatistical` da bat dau dung chung finance report reader cho cashflow/invoice-balance query + summary, va hien nay da mo rong them `RevenueOverviewWidget`, `OutstandingBalanceWidget`, `QuickFinancialStatsWidget`, `MonthlyRevenueChartWidget`, `PaymentMethodsChartWidget`, `PaymentStatsWidget`, va heading summary cua `OverdueInvoicesWidget` sang `FinancialDashboardReadModelService` de lop dashboard/payment widgets branch-scoped khong con giu payment/invoice aggregate query lap lai trong tung widget, `PatientStatistical` va `RiskScoringDashboard` da bat dau dung chung patient insight reader cho patient-breakdown/risk-summary contract, `MaterialStatistical` va `FactoryStatistical` da bat dau dung chung inventory/supplier report reader cho material-inventory/factory-order query + summary, dong thoi `FactoryStatistical` da dong branch-filter option leak bang actor-scoped branch options, `AppointmentStatistical` da bat dau dung chung appointment report reader cho appointment-query/visit-episode metric summary, va hien nay da mo rong them `CalendarAppointments` sang `AppointmentReportReadModelService` cho weekly operational status metrics, `OperationalKpiPack` / `OpsControlCenter` / `ops:check-observability-health` / `integrations:prune-operational-data` / `zns:prune-operational-data` / `OperationalKpiAlertService` / `CheckSnapshotSla` / `CompareReportSnapshots` / `IntegrationSettings` da bat dau dung chung reader convention cho KPI snapshot, KPI alert summary, control-plane automation audit, active-alert counting, snapshot SLA violation counts, ZNS backlog/retention counts, integration retention/backlog/grace-state counts, provider runtime readiness, va hien nay da tach them drift-aware snapshot diff + SLA evaluation contracts ra khoi command classes, chua push runtime len `main`.

## Program Verdict

- Overall verdict: `B`
- Production readiness:
  - Code baseline: `Dat`
  - Migration/backfill rollout: `Dat`
  - Real-infrastructure smoke test: `Dat`
  - Shared contracts: `Dang tien sat muc on dinh`
  - Workflow and audit convergence: `Dang trien khai`
  - Structural simplification: `Dang trien khai`

## Module Conclusions

### GOV - Governance / Branches / RBAC / Audit

- Conclusion: Khong con high-confidence finding mo trong baseline GOV.
- Residual risks:
  - Assistant va Finance chua duoc tach thanh seeded role rieng
  - permission drift van phai tiep tuc duoc chan bang assert/sync trong moi deploy
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu smoke test seed/sync governance tren moi truong that
- Follow-up opportunities:
  - tieu chuan hoa quy trinh tach role `Assistant` / `Finance` neu nghiep vu can
  - xac dinh retention va review cadence cho audit artifacts

### PAT - Customers / Patients / MPI

- Conclusion: Khong con high-confidence finding mo trong baseline PAT.
- Residual risks:
  - MPI workload va aging dashboard chua duoc productize
  - conversion/operator messaging cho truong hop lead dedupe vao patient cu van con dat nang UX hon la code safety
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu production-like smoke test cho duplicate workload, MPI queue, va lead conversion volume lon
- Follow-up opportunities:
  - dashboard aging/SLA cho MPI queue
  - polish operator messaging cho convert/dedupe flow

### APPT - Appointments / Calendar

- Conclusion: Khong con high-confidence finding mo trong baseline APPT.
- Residual risks:
  - queue worker health va do tre orchestration side-effect sau commit
  - can theo doi scheduling side-effects tren rollout dau tien
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu production smoke test cho async side-effects va reschedule flow tren du lieu that
- Follow-up opportunities:
  - ops dashboard cho scheduling orchestration latency
  - refine SOP cho state flow va overbooking policy neu nghiep vu muon ro hon

### CARE - Customer Care / Automation

- Conclusion: Khong con high-confidence finding mo trong baseline CARE.
- Residual risks:
  - outbound side-effects va provider semantics can duoc theo doi tiep qua `ZNS` va `KPI`
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu volume smoke test dai hon cho outbound/provider lane
- Follow-up opportunities:
  - tiep tuc don gian hoa operational messaging cho care queue o nhung lane con lai
  - tiep tuc giam coupling outbound side-effects voi `ZNS`/`KPI`

### CLIN - Clinical Records / Consent

- Conclusion: Khong con high-confidence finding mo trong baseline CLIN.
- Residual risks:
  - consent/imaging UX da duoc polish baseline, nhung van con du dia tiep tuc productize o browser flow sau nay
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu browser-level smoke test production-like cho media/upload va consent operator flow
- Follow-up opportunities:
  - browser flow cho consent ky/tu choi
  - tiep tuc polish read-preserving EMR UX

### TRT - Treatment Plans / Sessions / Materials Usage

- Conclusion: Khong con high-confidence finding mo trong baseline TRT.
- Residual risks:
  - can tiep tuc theo doi drift giua treatment, inventory, va finance bang full-suite va reconciliation smoke tests
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu cross-module smoke test cho treatment-inventory-finance chain
- Follow-up opportunities:
  - tiep tuc co dinh hoa immutable usage/void strategy
  - refine operator UX cho material usage va downstream adjustments

### FIN - Finance / Payments / Wallet / Installments

- Conclusion: Khong con high-confidence finding mo trong baseline FIN.
- Residual risks:
  - drift giua finance, inventory, va KPI van la rui ro he thong, khong con la lo hong module rieng
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu end-to-end smoke test cho invoice/payment/reversal/reporting tren production-like data
- Follow-up opportunities:
  - chot adjustment/void/reversal strategy nhat quan voi `TRT` va `INV`
  - dashboard reconciliation giua finance va reporting

### INV - Inventory / Batches / Stock

- Conclusion: Khong con high-confidence finding mo trong baseline INV.
- Residual risks:
  - inventory/reporting convergence voi FIN/TRT van la bai toan he thong
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu reconciliation cadence dai han tren stock data that
- Follow-up opportunities:
  - inventory reconciliation cadence
  - tiep tuc don gian hoa mutation/read-model cho kho

### SUP - Suppliers / Factory Orders

- Conclusion: Khong con high-confidence finding mo trong baseline SUP.
- Residual risks:
  - workflow va reporting supplier/labo van con la lane structural convergence
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu validation dai han cho supplier reporting tren du lieu production-like
- Follow-up opportunities:
  - formalize supplier ownership model
  - refine procurement/workflow states neu muon tach `received/verified`

### INT - Integrations

- Conclusion: Khong con high-confidence finding mo trong baseline INT.
- Residual risks:
  - grace token rotation/revoke can tiep tuc duoc theo doi theo cadence van hanh
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu periodic provider contract smoke tests va rollback drills
- Follow-up opportunities:
  - tach ro control-plane health, runtime settings, va secret rotation operations
  - dashboard hoa provider/runtime health

### ZNS - Zalo / ZNS

- Conclusion: Khong con high-confidence finding mo trong baseline ZNS.
- Residual risks:
  - observability threshold tuning cho campaign/retry lane van con the tinh chinh them
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu live-provider cadence va observability threshold tuning
- Follow-up opportunities:
  - tiep tuc polish dashboard triage dead-letter/retry
  - tiep tuc toi uu retained payload policy va runbook van hanh

### KPI - Reports / KPI

- Conclusion: Khong con high-confidence finding mo trong baseline KPI.
- Residual risks:
  - report/export tren dataset lon van can tiep tuc do do va toi uu
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu performance/export verification cadenced tren du lieu that
- Follow-up opportunities:
  - tiep tuc chuan hoa reporting platform chung
  - tiep tuc toi uu snapshot/read-model tren dataset lon

### OPS - Production Readiness / Backup / Observability

- Conclusion: Khong con high-confidence finding mo trong baseline OPS.
- Residual risks:
  - can duy tri cadence readiness/signoff dinh ky de tranh drift van hanh
- Testing gaps:
  - khong con regression gap o code baseline
  - con thieu signoff cadence va observability threshold tuning dinh ky
- Follow-up opportunities:
  - dashboard hoa control-plane operations
  - chot RPO/RTO va release signoff cadence thanh SOP on dinh

## Top Risks Remaining

1. Workflow va audit timeline van chua hoan tat tren moi lane uu tien
   - shared scope/search contracts da co primitive chung, nhung workflow/read-model van dang duoc hop nhat tiep.
2. Structural convergence giua TRT/INV/FIN/SUP van con mo
   - immutable adjustment/void/reversal strategy van la bai toan lon nhat con lai.
3. Reporting va integration control-plane van co du dia tiep tuc don gian hoa
   - KPI/report snapshot va integration runtime health da on dinh hon, nhung chua dat muc contract chung.

## Quick Wins

1. RRB-009 va RRB-010: workflow + audit convergence, voi lane `PAT/MPI`, patient workspace aggregates, tab counters, treatment progress, exam-status/media readers cho `PatientExamForm`, `lab-materials`, latest printable forms, `PatientTreatmentPlanSection`, `CustomerCare` SLA summary, outbox/event noi bo, finance OPS watchlist, va provider-health/control-plane readers da vao contract; trong do `PatientTreatmentPlanSection` da co them draft workflow service rieng cho `prepare draft / sync diagnosis / resolve plan / persist draft items`; con lai chu yeu la cac lane delivery/read-model bo sung va phan doc timeline hop nhat.
2. Mo rong read-model timeline ngoai patient activity neu nghiep vu can.
3. Chot lane observer/service con lai truoc khi vao structural refactor lon.

## Structural Risks

1. Treatment, inventory, finance, supplier van can mot chien luoc immutable ledger/adjustment thong nhat hon.
2. Reporting va snapshot platform can tiep tuc tach read-model khoi page logic khi dataset tang.
3. Integration control-plane can tiep tuc duoc tach ro health/runtime/secret lanes de giam drift van hanh; lane dau tien da bat dau bang fail-fast runtime readiness cho `emr:sync-events`, da tiep tuc sang `zns:run-campaigns` voi readiness check dung chung tu `ZnsProviderClient`, da bo sung them `IntegrationProviderRuntimeGate` cho command/publisher/runner lanes va inbound `Web Lead API` / `EMR internal API` / `Zalo webhook` ingress gates, va da mo rong them provider-health/readiness cho `DICOM / PACS` va `Web Lead API`.

## Suggested Implementation Order

1. Chot `RRB-009` va `RRB-010` cho workflow/audit lanes uu tien.
2. Tiep tuc dong `RRB-013` theo tung lane control-plane readiness nho, bat dau tu nhung provider/command da co release-gate coupling.
3. Sau do moi vao `RRB-012` va `RRB-013` theo tung lane read-model/control-plane bo sung.
4. Cuoi cung moi vao `RRB-011`.

## Canonical Next Documents

- [Review Master Index](00-master-index.md)
- [Refactor/Review Master Backlog](../roadmap/refactor-review-master-backlog.md)
- [Refactor/Review Execution Plan](../roadmap/refactor-review-execution-plan.md)
