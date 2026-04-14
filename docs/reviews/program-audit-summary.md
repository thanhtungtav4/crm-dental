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
- Last updated: `2026-04-14`

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
- Batch moi nhat tren nhanh `codex/wip-popup-risk-followups` da tiep tuc don gian hoa `patient workspace / popup center / risk dashboard` o lop presentation contract: `ViewPatient` dang dung render adapters cho tabs, payments, forms, basic-info cards, lab-material sections, va header actions; `PopupAnnouncementCenter` dang dung structured action payload + polling getter; `PatientRiskProfile` dang giu badge/stat formatting helper de page/service/view khong lap lai mapping.
- Checkpoint `2026-04-01` da keo them `ViewPatient` ra khoi vai tro assemble render-state: `rendered tabs`, `payments`, `forms`, `treatment progress`, `lab-material sections`, va `overview card` da di qua `PatientOverviewReadModelService`; moi nhat da co them `workspaceViewState()` de page class va Blade doc chung mot patient-workspace state thay vi moi getter tu goi reader rieng. O lane popup, `PopupAnnouncementCenterReadModelService` da co them `centerViewState()` de wrapper view cua popup center chi con render state hop nhat thay vi tu cham vao polling/payload roi rac; dong thoi popup dialog da tach thanh partial rieng va backlog docs da duoc dong bo lai voi lane `patient workspace / popup / risk`.
- Checkpoint `2026-04-02` da tiep tuc dong bo lane `ViewPatient lean page-state`: page nay da bo them mot lop proxy getter chi doc lai tung key tu `workspaceViewState` (`overview card`, `basic-info panels`, `rendered tabs`, `active panel/button ids`, `payments`, `forms`, `treatment progress`, `lab-material sections`), va test da chuyen sang khoa contract read-model thuc te thay vi adapter page-state trung gian.
- Checkpoint `2026-04-02` da tiep tuc dong bo lane `ViewPatient tabs/counters`: page nay da bo them proxy `tabs` va `tabCounters`, de visible-tab logic doc thang `workspaceViewState['tabs']` va regression khoa `PatientOverviewReadModelService::tabCounters()` thay vi mot page adapter khong con consumer thuc te.
- Checkpoint `2026-04-02` da tiep tuc dong bo lane `PopupAnnouncementCenter lean view-state`: `centerViewState()` nay da bo key `has_active_announcement` va giu shell `announcement + polling_interval`, de view-state popup khong con duy tri mot bool adapter trung lap chi de kiem tra `announcement !== null`.
- Checkpoint `2026-04-03` da tiep tuc dong bo lane `IntegrationSettings shell contract`: `pageViewState` nay da tiep tuc tro thanh shell contract duy nhat cho page settings, khi `noticePanels`, `pre/postFormSections`, `providerActionGroups`, `providerSupportPanels`, `providerPanels`, `renderedRecentLogs`, `renderedActiveSecretRotations`, `renderedProviderHealthCards`, va cac panel `secretRotation / providerHealth / auditLog` da duoc ha xuong helper noi bo; regression nay da chuyen sang khoa `pageViewState['provider_sections']` thay vi neo vao computed adapters cua page.
- Checkpoint `2026-04-03` da tiep tuc dong bo lane `OpsControlCenter shell contract`: `dashboardViewState` nay da tiep tuc giu vai tro shell duy nhat, trong khi cac adapter `renderedAutomationActorPanel`, `renderedRuntimeBackupPanel`, `renderedBackupFixturesPanel`, `renderedReadinessArtifactsPanel`, `renderedIntegrationsPanel`, `renderedKpiPanel`, `renderedFinancePanel`, `renderedZnsPanel`, `renderedObservabilityPanel`, `renderedGovernancePanel`, `renderedSmokePackPanel`, va `renderedRecentRunsPanel` da duoc ha xuong helper noi bo; regression nay da chuyen sang khoa `primary_sections / secondary_sections` va include-data payload thuc te thay vi tiep tuc goi computed getters cua page.
- Checkpoint `2026-04-03` da tiep tuc dong bo lane `OpsControlCenter raw state adapters`: cac getter raw-state con lai (`overview cards`, `automation actor`, `runtime backup`, `backup fixtures`, `integrations`, `KPI`, `finance`, `ZNS`, `governance`, `observability`, `recent runs`, `smoke commands`) da duoc ha xuong helper noi bo, de regression doi chieu truc tiep voi `state` seeded tu service va page shell thuc te chi con `dashboardViewState`.
- Checkpoint `2026-04-04` da chot them lane patient-workspace / popup shell: `ViewPatient` da dung `activeWorkspaceTabView()` va tab partials cho `basic-info`, `exam-treatment`, `lab-materials`, `payments`, va `forms`; `PopupAnnouncementCenter` da tach shell wrapper rieng va dung them payload `has_announcement` / `aria_live`, de page/component shell chi con dieu phoi state presentation-ready.
- Checkpoint `2026-04-04` da mo rong them page-shell convergence sang `ConversationInbox` va `CustomerCare`: `ConversationInbox` da dung `inboxViewState()` cho `queue_panel`, `detail_panel`, `selected_conversation_view`, `lead_modal_view`, va `schema_notice`; `CustomerCare` da dung `careViewState()` cho `overview_panel`, `tabs_panel`, va `active_tab_view`, de cat them adapter state va markup lap tren hai page operator nay.
- Checkpoint `2026-04-04` da mo rong them page-shell convergence sang `CalendarAppointments`, `SystemSettings`, va `PlaceholderPage`: `CalendarAppointments` da tach shell `header / metric cards / filters / reschedule modal` thanh partials va doc presentation payload cho `google_calendar_panel`, `metric_cards`, va `filters_panel`; `SystemSettings` va `PlaceholderPage` da dung `pageViewState()` + shell partials thay vi render truc tiep section/bullet state trong Blade.
- Checkpoint `2026-04-07` da tiep tuc mo rong them lane `clinical presentation`: `ToothChart` da duoc nang thanh custom Filament field cho `TreatmentPlanForm` va `ClinicalNotesRelationManager`, `ToothChartModalViewState` da gom presenter contract cho `tooth-chart-modal`, va `InstallmentPlan` da giu model-backed presentation methods cho `installment-schedule` modal de loai bo `@php` va contract attribute cu khong con ton tai trong schema.
- Checkpoint `2026-04-07` da tiep tuc mo rong them lane `report/base shell`: `BaseReportPage` da dung `pageViewState()` + partials `report-page-shell` / `report-stat-card`, de report shell chung khong con giu `@php($stats = $this->getStats())` va markup stats inline trong `base-report.blade.php`.
- Checkpoint `2026-04-07` da chot mot moc hygiene cho presentation layer: `resources/views/filament` va `resources/views/livewire` tren branch checkpoint hien khong con `@php` inline, va regression da khoa lai `tooth-chart`, `tooth-chart-modal`, `installment schedule`, `calendar`, `system settings`, `placeholder`, va `base-report` theo shell/read-model contract.
- Checkpoint `2026-04-09` da tiep tuc mo rong them lane shell/read-model nho: `PatientActivityTimelineWidget` da dung `timelineViewState()` thay cho `getViewData()`, `PatientTreatmentPlanSection` da hop nhat `list_panel / plan_modal / procedure_modal` qua `sectionViewState()`, `PatientExamForm` da dua `indications` + `evidence checklist` + `media timeline preview` ve payload presentation-ready, va `PasskeysComponent` da co `viewState()` + shell partial cho profile surface.
- Checkpoint `2026-04-09` da bat dau siet `RRB-011` o lane `TRT`: destructive surface cua `TreatmentMaterial` da duoc go khoi table/policy mac dinh, va regression da khoa model delete guard de vat tu dieu tri di theo huong append-only/reversal-only ro hon.
- Checkpoint `2026-04-09` da tiep tuc mo rong `RRB-011` o lane `TRT/FIN/SUP`: `TreatmentMaterialUsageService` da ghi audit co cau truc cho usage/reversal va `PatientOperationalTimelineService` da doc duoc `Ghi nhận vật tư điều trị`, `Hoàn tác vật tư điều trị`, va `Điều chỉnh ví bệnh nhân`; dong thoi `FactoryOrder` da duoc ha ve cancel-only khi delete surfaces tren UI/policy/model bi go bo. Checkpoint `2026-04-13` bo sung them `FactoryOrder::cancel()` canonical boundary noi ve `FactoryOrderWorkflowService` de lane labo dung cung mot workflow entry point tu model layer.
- Checkpoint `2026-04-10` da tiep tuc mo rong `RRB-011` o lane `INV`: `MaterialIssueNote` da duoc ha ve cancel-only, khi delete surfaces tren page/table bi go bo, `MaterialIssueNotePolicy` hard-deny `delete/restore/forceDelete`, va model delete guard buoc phieu xuat vat tu di qua workflow `cancel()` thay vi xoa truc tiep.
- Checkpoint `2026-04-10` da tiep tuc mo rong `RRB-011` o lane `TRT`: `TreatmentPlan` va `PlanItem` da duoc ha ve cancel-only, khi delete surfaces tren edit pages/relation manager bi go bo, policy hard-deny `delete/restore/forceDelete`, va model delete guard ep domain dieu tri di qua workflow `cancel()` thay vi xoa truc tiep.
- Checkpoint `2026-04-10` da tiep tuc mo rong `RRB-011` o lane `FIN`: `Payment` da dong bo policy/edit surface voi immutable ledger contract, khong con delete/restore/force-delete surface o UI va policy, va regression da khoa direct delete guard cua phieu thu/hoan.
- Checkpoint `2026-04-10` da tiep tuc mo rong `RRB-011` o lane `FIN`: `ReceiptExpense` da hard-deny `delete/restore/forceDelete` o policy va model delete guard da buoc phieu thu/chi di qua workflow state boundary thay vi xoa truc tiep.
- Checkpoint `2026-04-14` da tiep tuc chuan hoa lane `FIN`: `ReceiptExpense` da co them model boundaries `approve()` / `post()` noi ve `ReceiptExpenseWorkflowService`, va `Payment` da co them `reverse()` canonical noi ve `PaymentReversalService`, de workflow entry points nhat quan hon giua model/caller/service.
- Checkpoint `2026-04-14` da tiep tuc chuan hoa lane `FIN` o `Invoice`: model da co them `cancel()` canonical noi ve `InvoiceWorkflowService`, va regression da khoa lai cancel audit/status contract cho ca service path lan model boundary path.
- Checkpoint `2026-04-14` da tiep tuc chuan hoa lane `TRT`: `TreatmentPlan` va `PlanItem` da co them `cancel()` canonical noi ve `TreatmentPlanWorkflowService` / `PlanItemWorkflowService`, de workflow entry points nhat quan hon giua model/caller/service.
- Checkpoint `2026-04-14` da bo sung them `TreatmentPlan::approve()` / `start()` / `complete()` canonical noi ve `TreatmentPlanWorkflowService`, de entry points workflow duoc goi tu model layer thay vi service call truc tiep.
- Checkpoint `2026-04-14` da bo sung them `PlanItem::startTreatment()` / `completeTreatment()` canonical noi ve `PlanItemWorkflowService`, de entry points workflow hang muc dieu tri di qua model layer thay vi service call truc tiep.
- Checkpoint `2026-04-14` da bo sung `Note::updateCareTicket()` / `transitionCareTicket()` canonical noi ve `CareTicketWorkflowService`, de entry points care ticket di qua model layer thay vi caller tu goi service.
- Checkpoint `2026-04-14` da bo sung `PopupAnnouncementDelivery` model boundaries `markSeenViaWorkflow()` / `acknowledgeViaWorkflow()` / `dismissViaWorkflow()` noi ve workflow service, de delivery status transitions di qua model layer thay vi call service truc tiep.
- Checkpoint `2026-04-14` da bo sung `PopupAnnouncement::publish()` canonical noi ve `PopupAnnouncementWorkflowService`, de entry point publish di qua model layer thay vi goi service truc tiep.
- Checkpoint `2026-04-14` da bo sung `ClinicalOrder::markInProgress()` boundary co `reason` / `actor` / `trigger` va chuan hoa `markCompleted()` / `cancel()` tra ve model da transition, de entry points workflow chi dinh di qua model layer nhat quan hon thay vi service call truc tiep.
- Checkpoint `2026-04-14` da bo sung `InsuranceClaim::submit()` / `approve()` / `deny()` / `resubmit()` / `markPaid()` canonical noi ve `InsuranceClaimWorkflowService`, de entry points ho so bao hiem di qua model layer thay vi service call truc tiep.
- Checkpoint `2026-04-14` da chuan hoa `MasterPatientDuplicate::markResolved()` / `markIgnored()` tra ve model da transition, de MPI duplicate queue co model boundary nhat quan hon voi cac workflow lane khac.
- Checkpoint `2026-04-14` da bo sung `FactoryOrder::markOrdered()` / `markInProgress()` / `markDelivered()` canonical noi ve `FactoryOrderWorkflowService`, de entry points workflow labo di qua model layer thay vi service call truc tiep.
- Checkpoint `2026-04-14` da tiep tuc chuan hoa lane `ZNS`: `ZnsCampaign` da co them `cancel()` canonical noi ve `ZnsCampaignWorkflowService`, de entry point huy campaign di qua workflow service thay vi caller tu doi status.
- Checkpoint `2026-04-14` da bo sung `ZnsCampaign::schedule()` / `runNow()` canonical noi ve `ZnsCampaignWorkflowService`, de entry points len lich / chay ngay di qua model layer thay vi service call truc tiep.
- Checkpoint `2026-04-10` da tiep tuc lam gon destructive path adjacent voi `RRB-009` o lane `CLIN`: `ClinicalOrder` da co model delete guard moi, de chi dinh lam sang co `cancel()` canonical khong con bi xoa truc tiep ngoai workflow.
- Checkpoint `2026-04-13` da tiep tuc mo rong `RRB-011` sang lane workflow-backed adjunct surface: `PopupAnnouncement` da duoc ha ve cancel-only, khi page/table khong con expose delete/restore/force-delete surfaces, policy hard-deny `delete/restore/forceDelete`, model delete guard chan xoa truc tiep, va boundary `cancel()` canonical da noi thang vao `PopupAnnouncementWorkflowService`.
- Checkpoint `2026-04-13` da tiep tuc khoi sau lane `RRB-011` o `TRT`: `TreatmentMaterialUsageService::delete()` gio idempotent cho retry path, de reverse cung mot usage nhieu lan khong con double-restore batch ton kho, double-write `InventoryTransaction` adjust, hay double-write `ACTION_REVERSAL` audit.
- Checkpoint `2026-04-13` da tiep tuc mo rong `RRB-011` sang lane insurance adjunct: `InsuranceClaim` da co `cancel()` canonical boundary noi ve `InsuranceClaimWorkflowService`, model delete guard hard-deny direct delete, va regression da khoa structured cancel audit metadata cho workflow nay.
- Checkpoint `2026-04-04` da mo rong them page-shell convergence sang `DeliveryOpsCenter` va `FrontdeskControlCenter`: hai page nay da dung chung `BuildsControlCenterPageViewState` va shell partials cho overview cards, quick links, va section panels thay vi giu presentation builder lap lai trong tung page.
- Checkpoint `2026-04-04` da tiep tuc dong bo lane control-plane shell contracts: `IntegrationSettings`, `OpsControlCenter`, va `ZaloZns` da dung them partial/state chung cho `provider health`, `dashboard summary`, `control-plane sections`, `grace rotations`, `field renderers`, va `OPS detail cards`; regression tiep tuc khoa `pageViewState()` / `dashboardViewState()` la public contract chinh thay vi cac adapter nho le.
- Checkpoint `2026-04-01` tiep tuc mo rong `RRB-013` trong `IntegrationSettings`: `IntegrationProviderHealthReadModelService` nay da tra them presentation payload cho provider-health snapshot (`status badge`, `summary badge`, `issue badge`, `meta preview`, `status message`), de page khong con giu tone-class mapping va issue/meta fallback trong Blade; song song do `IntegrationProviderActionService` da co them notification payload contract (`status/title/body`) cho `EMR`, `Google Calendar`, va readiness actions, va `IntegrationSettings` page nay chi con dispatch payload da chuan hoa, dong thoi da co page-action tests khoa `google_calendar_account_email` + readiness notifications.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `secret rotation / OPS cockpit`: `IntegrationOperationalReadModelService` nay da tra them rendered payload cho `expired grace rotations`, de `OpsControlCenterService` va `IntegrationSettings` dung chung mot presentation contract cho ca active/expired grace tokens, bo remap `grace_expires_at` / `expired_minutes` khoi OPS service va Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `provider readiness / OPS cockpit`: `OpsControlCenterService` nay da dung `snapshotCards()` cua `IntegrationProviderHealthReadModelService`, de section `Provider readiness` tren OPS cockpit doc cung `status badge`, `summary badge`, `issue badge`, `meta preview`, va `status message` contract voi `IntegrationSettings`, bo raw provider-card rendering khoi OPS Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `prune backlog / OPS cockpit`: `IntegrationOperationalReadModelService` va `ZnsOperationalReadModelService` nay da co `retentionCandidates()`, de `OpsControlCenterService` doc thang prune backlog presentation cho `integration` va `ZNS` thay vi tu giu label/retention/tone matrix rieng trong cockpit service.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings shell helpers`: page nay da ha `noticePanels`, `preFormSections`, `providerSections`, `postFormSections`, va cac notice/submit adapters lien quan xuong helper noi bo, de `pageViewState` giu vai tro shell contract duy nhat ma Blade va regression can dung.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings panel/read-model adapters`: `renderedRecentLogs`, `renderedActiveSecretRotations`, `renderedProviderHealthCards`, va cac panel `secretRotation / providerHealth / auditLog` da duoc ha xuong helper noi bo, de regression khoa truc tiep panel payload trong `pageViewState` thay vi tiep tuc neo vao computed adapters cua page.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings provider support adapters`: `providerActionGroups` va `providerSupportPanels` da duoc ha xuong helper noi bo, de regression khoa `providerPanels/support_sections` la render contract thuc te thay vi computed adapters trung gian.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns page`: `ZaloZns` nay da dung `snapshotCards()` cua `IntegrationProviderHealthReadModelService` cho `Provider readiness` va `dashboardSummaryCards()` cua `ZnsOperationalReadModelService` cho 7 summary cards dau trang, de page triage ZNS doc cung presentation contract voi `IntegrationSettings` / `OPS` thay vi giu hardcode card tone/score/message trong Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `shared control-plane presentation`: card `provider health` da duoc gom vao partial chung cho `IntegrationSettings`, `ZaloZns`, va `OpsControlCenter`; dong thoi OPS `ZNS triage` da chuyen sang `dashboardSummaryCards()` cua `ZnsOperationalReadModelService`, bo `number_format()` va badge formatting lap lai khoi Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns dashboard builder`: builder cho `summary`, `provider readiness`, `note panels`, va `dashboard section` da duoc tach thanh helper noi bo, de `dashboardViewState` giu shell gon va page khong con giu mot computed method qua day dac.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings page-state`: page nay da co `secretRotationPanel`, `providerHealthPanel`, va `auditLogPanel`, de Blade doc mot lop control-plane view-state ro nghia thay vi cham truc tiep vao nhieu getter `rendered*` rieng le.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings provider actions`: page nay da co `providerActionGroups`, de cac nut `readiness / test / open config / generate token` cua `Zalo OA`, `ZNS`, `Google Calendar`, `EMR`, va `Web Lead API` doc tu mot action contract chung thay vi giu `wire:click` hardcode theo tung provider trong Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings shared partials`: `secret rotation` cards va `audit log` table da duoc tach thanh partial chung, de view nay khong con giu markup lặp cho grace tokens va setting-change audit rows.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings provider support state`: page nay da co them `providerSupportPanels`, de action buttons va guide partials cua tung provider (`Zalo OA`, `Web Lead API`, `Popup`) duoc doc qua mot support-state chung thay vi tiep tuc giu `if(provider group)` rieng trong Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings provider action rendering`: khung action buttons cua tung provider da duoc tach them qua partial chung `provider-action-buttons`, de page settings khong con giu markup button lặp trong vong lap provider.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings field partials`: renderer field theo `boolean / select / roles / textarea / json / input` da duoc tach thanh partial chung, va page da co `resolveFieldPartialView()` de Blade khong con giu nhanh `if/elseif` lon cho tung field type.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings provider panels`: page nay da co `providerPanels`, de provider definition + support state (`actions`, `guide_partial`) duoc hop nhat truoc khi render thay vi Blade tu ghep `getProviders()` voi `providerSupportPanels`.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings rendered fields`: `providerPanels` nay da co them `rendered_fields`, de provider partial khong con tu lap `statePath` hay goi `resolveFieldPartialView()` ngay trong Blade ma chi render field payload da duoc page state chuan hoa.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings control-plane sections`: `pageViewState` nay da co them `pre_form_sections` va `post_form_sections`, de shell page loop section payload cho `secret rotation`, `provider health`, va `audit log` thay vi giu cac block `x-filament::section` lap tay quanh form.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings provider sections`: `pageViewState` nay da co them `provider_sections`, de shell page loop section payload cho tung provider thay vi goi truc tiep raw `providers` trong form shell; dong thoi provider partial khong con tu wrap `x-filament::section`.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `Control-plane shared section shell`: `IntegrationSettings` va `OpsControlCenter` nay da dung partial chung `control-plane-section`, de shell page khong con lap lai renderer `x-filament::section + include_data` cho cac lane section-state.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `IntegrationSettings lean page-state`: `pageViewState` nay da bo cac key adapter khong con consumer thuc te (`read_only_notice`, `secret_rotation_notice`, `control_plane`, `providers`), va `providerPanels` cung da bo `support` legacy de page-state phan anh dung contract dang render.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS KPI cockpit`: `OperationalKpiSnapshotReadModelService` da co `renderedSnapshotCountCards()`, va `OpsControlCenterService` da tra them `aggregate_readiness_cards`, de section `KPI freshness` khong con tu format `snapshot count` / `aggregate readiness` badge ngay trong Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns page-state`: `ZaloZns` da co `dashboardViewState`, de Blade doc mot state hop nhat cho `summary cards`, `provider health`, `triage notes`, va `guidance notes` thay vi tu cham vao nhieu getter presentation rieng le.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns panel-state`: `dashboardViewState` cua page nay da chuyen sang `summary_panel`, `provider_health_panel`, `triage_panel`, va `guidance_panel`, de page triage ZNS dung cung pattern panel-state voi `IntegrationSettings` / `OpsControlCenter`.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns shared notes`: hai khung `Triage nhanh` va `Gợi ý xử lý` da duoc dua qua partial chung `control-plane-note-panel`, de page ZNS triage khong con giu hai block note markup tach biet trong Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns note panels`: `dashboardViewState` nay da co them `note_panels`, de dashboard partial co the loop note payload theo cung contract thay vi van phai giu adapter rieng cho `triage_panel` va `guidance_panel`.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns dashboard sections`: `dashboardViewState` nay da co them `dashboard_sections`, de dashboard partial loop section payload cho `summary`, `provider health`, `note panels`, va `table` thay vi giu knowledge truc tiep ve cac khung nay trong mot Blade duy nhat.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns dashboard shell`: `dashboardViewState` nay da co them `dashboard_section`, de shell page `ZaloZns` dung cung partial `control-plane-section` voi `IntegrationSettings` / `OpsControlCenter` thay vi giu wrapper `x-filament::section` rieng.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `ZaloZns lean page-state`: `dashboardViewState` nay da bo cac key top-level trung lap (`summary_panel`, `provider_health_panel`, `triage_panel`, `guidance_panel`, `note_panels`, `dashboard_sections`), giu lai `dashboard_section` la contract duy nhat ma page shell can dung.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OpsControlCenter page-state`: page nay da co `renderedIntegrationsPanel`, `renderedKpiPanel`, `renderedFinancePanel`, `renderedZnsPanel`, `renderedObservabilityPanel`, va `renderedGovernancePanel`, de Blade khong con cham truc tiep vao raw state arrays cua cac section cockpit chinh.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OpsControlCenter primary sections`: `dashboardViewState` nay da co them `primary_sections`, de cot trai cua cockpit loop section payload (`Automation actor`, `Backup & restore`, `Readiness artifacts`) thay vi giu 3 block `x-filament::section` lap tay trong page shell; dong thoi `Backup` va `Readiness` da duoc tach them qua partial rieng.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OpsControlCenter secondary sections`: `dashboardViewState` nay da co them `secondary_sections`, de cot phai cua cockpit loop section payload (`Integrations`, `KPI`, `Finance`, `ZNS`, `Observability`, `Governance`, `Smoke pack`, `Recent runs`) thay vi giu 8 block `x-filament::section` lap tay trong page shell.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS shared presentation`: cac partial `section-summary-banner`, `signal-badge-card`, va `retention-candidate-card` da duoc dua vao cockpit, de `Integrations`, `KPI`, `Finance`, `ZNS`, va `Governance` dung chung renderer cho summary/signal/retention thay vi copy-paste badge markup trong Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OpsControlCenter dashboard state`: page nay da co `dashboardViewState`, de `overview cards`, `automation actor`, `backup/readiness artifacts`, va cac panel control-plane chinh doc tu mot state hop nhat thay vi moi khuc Blade goi getter rieng.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OpsControlCenter lean page-state`: `dashboardViewState` nay da bo cac panel top-level trung lap (`integrations`, `kpi`, `finance`, `zns`, `observability`, `governance`, `automation_actor_panel`, `runtime_backup_panel`, `backup_fixtures_panel`, `readiness_artifacts_panel`, `smoke_pack_panel`, `recent_runs_panel`), giu lai `overview_cards`, `primary_sections`, va `secondary_sections` la shell contract thuc te.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OpsControlCenter column-section adapters`: `primaryColumnSections` va `secondaryColumnSections` nay da duoc ha xuong helper noi bo, de page chi con expose `dashboardViewState` la shell contract can thiet cho cockpit shell va regression.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS grace rotation panels`: `active_grace` va `expired_grace` tren `OpsControlCenter` nay da duoc chuan hoa thanh item payload `display_name / detail_text / card_classes`, va Blade da dung partial chung `grace-rotation-panel` thay vi giu hai block markup gan nhu copy-paste.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS automation/runtime panels`: `OpsControlCenter` nay da co `renderedAutomationActorPanel` va `renderedRuntimeBackupPanel`, dong thoi `Automation actor` va `Backup & restore` da duoc tach sang partial rieng de page/view khong con xu ly badge/meta/issues/path inline.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS readiness/run history panels`: `OpsControlCenter` nay da co `renderedReadinessArtifactsPanel`, `renderedSmokePackPanel`, va `renderedRecentRunsPanel`, dong thoi `Readiness artifacts`, `Smoke pack`, va `Recent operator runs` da duoc tach sang partial chung `ops-artifact-list`, `ops-command-list`, va `ops-recent-runs-table`.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS observability/governance panels`: `renderedObservabilityPanel` va `renderedGovernancePanel` nay da duoc nang cap voi `metric_cards`, `breach_cards`, `missing_runbook_panel`, `scenario_user_panel`, va `recent_audit_panel`, de `Observability` va `Governance & audit scope` dung partial rieng thay vi giu card markup inline trong Blade.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS KPI/finance panels`: `renderedKpiPanel` va `renderedFinancePanel` nay da duoc mo rong voi `open_alert_panel` va `watchlist_panel`, dong thoi `KPI freshness & alerts` va `Finance & collections` da duoc tach sang partial rieng de page/view khong con giu alert/watchlist card markup inline.
- Checkpoint `2026-04-02` tiep tuc dong bo lane `OPS integrations/zns panels`: `Integrations & secret rotation` va `ZNS triage cockpit` da duoc tach sang partial rieng `ops-integrations-panel` va `ops-zns-panel`, de `OpsControlCenter` page shell khong con giu provider-health/grace/retention/link loops va ZNS summary/retention/link loops inline.

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
