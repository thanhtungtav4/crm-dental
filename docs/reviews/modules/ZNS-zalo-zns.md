# Metadata

- Module code: `ZNS`
- Module name: `Zalo / ZNS`
- Current status: `In Fix`
- Current verdict: `D`
- Review file: `docs/reviews/modules/ZNS-zalo-zns.md`
- Issue file: `docs/issues/ZNS-issues.md`
- Plan file: `docs/planning/ZNS-plan.md`
- Issue ID prefix: `ZNS-`
- Task ID prefix: `TASK-ZNS-`
- Dependencies: `PAT, APPT, CARE, INT, OPS`
- Last updated: `2026-03-07`

# Scope

- Review module `ZNS` theo 4 lop: architecture, database, domain logic, UI/UX.
- Trong pham vi review:
  - inbound webhook `ZaloWebhookController`
  - automation outbox `ZnsAutomationEvent` + `SyncZnsAutomationEvents`
  - outbound campaign flow `ZnsCampaign`, `ZnsCampaignDelivery`, `ZnsCampaignRunnerService`
  - Filament page/resource `ZaloZns`, `ZnsCampaignResource`
  - provider boundary `ZnsProviderClient`, readiness audit `ZaloIntegrationService`

# Context

- `ZNS` la outbound communication boundary cho lead welcome, appointment reminder, birthday greeting va campaign broadcast.
- Module nay noi truc tiep vao PII cua patient/customer, outbound provider retry, dead-letter backlog va audit/observability.
- Evidence chinh den tu:
  - `app/Http/Controllers/Api/ZaloWebhookController.php`
  - `app/Services/ZnsAutomationEventPublisher.php`
  - `app/Console/Commands/SyncZnsAutomationEvents.php`
  - `app/Services/ZnsCampaignRunnerService.php`
  - `app/Services/ZnsProviderClient.php`
  - `app/Filament/Resources/ZnsCampaigns/*`
  - `app/Filament/Pages/ZaloZns.php`
  - `database/migrations/2026_03_01_190649_create_zns_campaigns_table.php`
  - `database/migrations/2026_03_01_190652_create_zns_campaign_deliveries_table.php`
  - `database/migrations/2026_03_04_141133_create_zns_automation_events_table.php`
  - `tests/Feature/ZaloIntegrationTest.php`
  - `tests/Feature/ZnsAutomationPipelineTest.php`
  - `tests/Feature/ZnsCampaignRunnerReliabilityTest.php`
- Thong tin con thieu lam giam do chinh xac review:
  - Chua co ma tran role/page chinh thuc cho `ZaloZns` va `ZnsCampaignResource`.
  - Chua co retention policy ro rang cho `zns_automation_events`, `zns_automation_logs`, `zns_campaign_deliveries`.
  - Chua co hop dong ro rang voi provider ve idempotency cua `tracking_id`.

# Executive Summary

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`
- Cac canh bao nghiem trong:
  - Runtime check xac nhan `Doctor` hien co `canViewAny()` va `canCreate()` tren `ZnsCampaignResource`, va `ZaloZns::canAccess()` cung `true`.
  - Workflow campaign dang cho sua `status` truc tiep tren form/table/page, khong co workflow service + audit boundary.
  - `cancelAppointmentReminder()` co the danh dau event la `dead` trong khi worker da claim va van co the gui ra provider truoc khi finalize.
  - `zns_automation_events`, `zns_campaign_deliveries`, `zns_automation_logs` dang luu so dien thoai va payload provider dang plaintext.

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - Runtime tinker: `ZnsCampaignResource::canViewAny() === true`, `ZnsCampaignResource::canCreate() === true`, `ZaloZns::canAccess() === true` voi role `Doctor`.
  - `app/Filament/Resources/ZnsCampaigns/ZnsCampaignResource.php`
  - `app/Filament/Pages/ZaloZns.php`
  - `app/Filament/Resources/ZnsCampaigns/Pages/EditZnsCampaign.php`
- Findings:
  - Module outbound messaging nhay cam nhung chua co policy/page gate rieng. Query scope theo branch khong du thay the authorization.
  - `EditZnsCampaign` mo `DeleteAction`, table mo `DeleteBulkAction`, va page `ZaloZns` placeholder hien khong co guard rieng.
  - `RunZnsCampaigns` khong co `ActionGate::authorize()`, trong khi `SyncZnsAutomationEvents` co.
- Suggested direction:
  - Them `ZnsCampaignPolicy` va `canAccess()` cho `ZaloZns`.
  - Tach permission cho page/resource/command: `ViewAny:ZnsCampaign`, `Create:ZnsCampaign`, `Run:ZnsCampaign`, `View:ZaloZnsPage`.
  - Loai destructive surfaces hoac khoa bang workflow/archive policy.

## Data Integrity & Database

- Danh gia: `Kem`
- Evidence:
  - `database/migrations/2026_03_04_141133_create_zns_automation_events_table.php`
  - `database/migrations/2026_03_01_190652_create_zns_campaign_deliveries_table.php`
  - `database/migrations/2026_03_04_141133_create_zns_automation_logs_table.php`
  - `app/Models/ZnsAutomationEvent.php`
- Findings:
  - Tables dang luu `phone`, `normalized_phone`, `payload`, `request_payload`, `response_payload`, `provider_response` dang plaintext.
  - Chua co retention boundary hay archival boundary cho log/event payload nhay cam.
  - `ZnsCampaignForm` cho edit truc tiep `status`, `template_id`, `message_payload`, de tao invalid state va drift audit.
- Suggested direction:
  - Giam PII trong outbox/logs: encrypt/redact phone/payload nhay cam, chi giu du keys can truy vet.
  - Them retention policy/cleanup command ro rang cho log va dead-letter history.
  - Tach workflow-controlled fields khoi editable form.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - `app/Services/ZnsAutomationEventPublisher.php`
  - `app/Console/Commands/SyncZnsAutomationEvents.php`
  - `app/Services/ZnsCampaignRunnerService.php`
- Findings:
  - `cancelAppointmentReminder()` update ca event dang `processing`, nhung `processEvent()` goi provider sau khi claim va truoc khi re-check status trong transaction finalize. Event co the bi cancel nhung tin van gui di.
  - `ZnsCampaignRunnerService::runCampaign()` khong co campaign-level lock. Hai worker co the cung scan audience, cung mark running va cung refresh summary.
  - Workflow campaign schedule/run/cancel dang mutate truc tiep bang `save()`, khong co transaction boundary hay reason/audit metadata.
- Suggested direction:
  - Dua campaign transitions vao `ZnsCampaignWorkflowService` co lock + audit.
  - Cancellation cua automation event phai ton trong processing token/claim, hoac dua provider send vao boundary co final pre-send check.
  - Them campaign claim token neu muon tranh duplicate audience scan.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Services/ZnsCampaignRunnerService.php::prepareDeliveriesForAudience()`
  - `app/Services/ZnsCampaignRunnerService.php::audienceQuery()`
  - `tests/Feature/ZnsCampaignRunnerReliabilityTest.php`
- Findings:
  - Moi lan run campaign deu scan lai toan audience query va `firstOrCreate()` tung patient.
  - Khi campaign lon hoac bi rerun song song, chi phi audience scan va summary recount se tang nhanh.
  - Resource/Deliveries relation manager chua co filter/search theo `normalized_phone`, `provider_status_code`, `next_retry_at` cho van hanh production.
- Suggested direction:
  - Them campaign-level claim lock.
  - Xem xet snapshot audience sau khi schedule de giam repeated scan.
  - Bo sung van hanh filters cho deliveries/dead-letter triage.

## Maintainability

- Danh gia: `Kem`
- Evidence:
  - `app/Filament/Resources/ZnsCampaigns/Tables/ZnsCampaignsTable.php`
  - `app/Services/ZnsCampaignRunnerService.php`
  - `app/Console/Commands/RunZnsCampaigns.php`
  - `app/Services/ZnsAutomationEventPublisher.php`
- Findings:
  - Workflow dang bi tach giua model transition, table action, edit page action, command va runner service.
  - Chua co source of truth duy nhat cho campaign lifecycle.
  - Test reliability tot cho provider failure/retry, nhung thieu auth matrix va cancel-vs-processing race.
- Suggested direction:
  - Gom campaign lifecycle vao service duy nhat.
  - Resource/page chi goi workflow actions, khong mutate status truc tiep.
  - Bo sung regression suite cho auth, payload redaction va race edge case.

## Better Architecture Proposal

- Tao `ZnsCampaignWorkflowService`:
  - `schedule()`
  - `runNow()`
  - `cancel()`
  - `archive()` hoac `softDelete()` guard
  - transaction + audit metadata + branch auth
- Tach `ZnsOutboundPolicyBoundary`:
  - auth cho page/resource/command
  - branch-aware record access
- Tach `ZnsDataRetentionService`:
  - redact/encrypt payload nhay cam
  - prune logs/event theo retention policy

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Kem`
- Workflow hien tai:
  - inbound webhook Zalo OA ghi vao `zalo_webhook_events`
  - automation event publisher tao outbox cho lead welcome / appointment reminder / birthday
  - command `zns:sync-automation-events` day event ra provider
  - campaign resource tao/schedule/run/cancel campaign broadcast
- Van de:
  - workflow appointment/birthday automation va workflow campaign dang tach roi, khong chung mot governance boundary.
  - UI va command deu co the doi status campaign ma khong co reason/audit service.

## State transitions

- Danh gia: `Trung binh`
- `ZnsCampaign` co `STATUS_TRANSITIONS`, nhung transition dang enforce o model `saving()` va cho edit truc tiep tren form/action.
- `ZnsAutomationEvent` co status `pending -> processing -> sent/failed/dead`, nhung cancellation appointment reminder dang ghi thang `dead` vao event `processing`.
- `ZnsCampaignDelivery` co `queued -> sent/failed/skipped`, nhung campaign khong co workflow lock khi rerun/cancel.

## Missing business rules

- Chua co rule role nao duoc tao/chay/huy campaign.
- Chua co rule page `ZaloZns` chi role nao xem duoc.
- Chua co rule retention/redaction cho phone/payload/provider response.
- Chua co rule xu ly cancel appointment reminder khi event dang processing.
- Chua co rule campaign delete/archive sau khi da gui delivery.

## Invalid states / forbidden transitions

- `Doctor` co the vao page/resource ZNS va tao/chay campaign.
- Campaign co the bi chuyen `status` truc tiep tu form ma khong qua workflow service.
- Appointment reminder co the bi cancel trong DB nhung worker van gui ra provider.
- Campaign bi delete/cancel khi dang co delivery processing ma khong co coordination boundary.

## Service / action / state machine / transaction boundary de xuat

- `ZnsCampaignWorkflowService`
  - enforce auth
  - enforce allowed transitions
  - ghi audit ly do
  - khoa campaign-level processing
- `ZnsAutomationCancellationService`
  - cancel by source voi pre-send coordination theo processing token
- `ZnsRetentionRedactionService`
  - scrub phone/payload sau khi khong con can full forensic data

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Diem tot:
  - co campaign resource rieng
  - co delivery log relation manager
  - co readiness audit o integration settings
- Diem yeu:
  - `ZaloZns` page chi la placeholder, khong giup operator triage dead-letter/retry.
  - campaign form expose raw JSON + editable status, de user thao tac sai.
  - delivery log relation manager chua co filters triage theo retry/dead/provider code.

## Filament UX

- Danh gia: `Trung binh`
- `ZnsCampaignForm` cho sua `status` truc tiep.
- `EditZnsCampaign` co `DeleteAction` va `runNow` action khong hien helper text ve side effect.
- `DeliveriesRelationManager` read-only la dung, nhung van thieu filter van hanh.

## Edge cases quan trong

- Hai worker chay cung campaign cung luc.
- Appointment reminder bi cancel sau khi worker da claim event.
- Provider 4xx terminal vs 5xx retryable phai giu semantics on dinh.
- Campaign co audience > 500 nguoi va bi rerun khi dang failed/running.
- Webhook payload duplicate va replay attack.
- Event/log tables tang nhanh va giu PII qua lau.

## Diem de thao tac sai

- Form cho set `status` truc tiep.
- `runNow`/`cancel` khong hien ly do va khong bat ghi chu van hanh.
- Placeholder page `ZaloZns` tao ky vong co dashboard van hanh nhung thuc te khong co control nao.

## De xuat cai thien UX

- Bien `status` thanh read-only badge; chi doi qua workflow actions.
- Them reason modal cho `cancel` va `runNow` neu override schedule.
- Them filters `status`, `provider_status_code`, `next_retry_at`, `attempt_count` cho deliveries.
- Bien `ZaloZns` thanh dashboard triage dead-letter/retry thay vi placeholder page rong.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| ZNS-001 | Critical | Security | ZNS page/resource khong co auth boundary dung | Resolved | `ZnsCampaignPolicy` + `ZaloZns::canAccess()` da khoa `Doctor` khoi page/resource |
| ZNS-002 | Critical | Domain Logic | Campaign workflow dang mutate status truc tiep, khong qua service canonical | Resolved | `ZnsCampaignWorkflowService` da khoa form/page/table va bo delete surface |
| ZNS-003 | Critical | Concurrency | Appointment reminder cancel co the race voi worker dang processing va van gui tin | Resolved | Claim da co `processing_token`, cancel/supersede xoa token va worker co pre-send guard truoc khi goi provider |
| ZNS-004 | High | Security | Outbox/delivery/log dang luu phone va payload ZNS plaintext | Resolved | Phone/payload da duoc ma hoa, request-response chi con ban rut gon va co prune command |
| ZNS-005 | High | Concurrency | Campaign runner chua co campaign-level lock | Resolved | Runner da claim campaign bang `processing_token + locked_at`, stale lock duoc reclaim va command bo qua campaign dang duoc worker khac xu ly |
| ZNS-006 | High | Security | `RunZnsCampaigns` khong duoc bao ve bang action permission | Resolved | Command da duoc khoa bang `ActionGate::authorize(ActionPermission::AUTOMATION_RUN)` |
| ZNS-007 | Medium | UX | Filament UX cho ZNS de thao tac sai va thieu triage view | Open | `status` editable raw, `ZaloZns` chi la placeholder, deliveries thieu filters |
| ZNS-008 | Medium | Maintainability | Coverage thieu auth matrix, cancel-processing race va payload governance | Resolved | Module da co suite rieng cho auth surface, cancel-processing, payload governance, runner lock va scheduler wiring |

# Dependencies

- `PAT`: phone/patient PII, branch ownership
- `APPT`: appointment reminder source event va cancel semantics
- `CARE`: birthday/automation source event, ticket dedupe
- `INT`: provider integration settings, webhook/runtime secrets
- `OPS`: dead-letter alert, observability, retention/runbook

# Open Questions

- Co can cho `Manager` chay/cancel campaign hay chi `Admin/CSKH supervisor`?
- Payload/provider response can giu full bao lau truoc khi redact?
- Provider co support idempotency theo `tracking_id` o muc contract hay chi best effort?

# Recommended Next Steps

1. Sinh issue file canonical cho module.
2. Tiep tuc `TASK-ZNS-006` de them triage UX va filters van hanh.
3. Sau do re-audit module.

# Current Status

- In Fix
