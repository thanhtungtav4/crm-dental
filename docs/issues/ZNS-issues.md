# Metadata

- Module code: `ZNS`
- Module name: `Zalo / ZNS`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Issue ID prefix: `ZNS-`
- Task ID prefix: `TASK-ZNS-`
- Review file: `docs/reviews/modules/ZNS-zalo-zns.md`
- Plan file: `docs/planning/ZNS-plan.md`
- Dependencies: `PAT, APPT, CARE, INT, OPS`
- Last updated: `2026-03-07`

# Issue Backlog

## [ZNS-001] ZNS page/resource khong co auth boundary dung
- Severity: Critical
- Category: Security
- Module: ZNS
- Description:
  - `ZnsCampaignResource` va page `ZaloZns` hien khong co policy/page gate rieng. Runtime check xac nhan `Doctor` dang `canViewAny()`, `canCreate()` va `canAccess()` duoc.
- Why it matters:
  - Messaging surface nhay cam co the bi role nghiep vu khong dung tao/chay campaign, xem PII va trigger outbound side-effects.
- Evidence:
  - `app/Filament/Resources/ZnsCampaigns/ZnsCampaignResource.php`
  - `app/Filament/Pages/ZaloZns.php`
  - runtime tinker voi role `Doctor`
- Suggested fix:
  - Them `ZnsCampaignPolicy`, `canAccess()` cho `ZaloZns`, va permission/action boundary ro rang cho page/resource.
- Affected areas:
  - Filament page/resource/pages
  - permission matrix
- Tests needed:
  - feature test auth matrix cho admin/manager/doctor/cskh
  - feature test route/page access cho `ZaloZns`
- Dependencies:
  - GOV
- Suggested order: 1
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-001`

## [ZNS-002] Campaign workflow dang mutate status truc tiep, khong qua service canonical
- Severity: Critical
- Category: Domain Logic
- Module: ZNS
- Description:
  - `status` editable tren form, table actions schedule/run/cancel mutate thang bang `save()`, edit page co `DeleteAction`, trong khi khong co workflow service + audit boundary.
- Why it matters:
  - De tao invalid state, kho truy vet ly do van hanh, va rat de drift khi them business rule moi.
- Evidence:
  - `app/Filament/Resources/ZnsCampaigns/Schemas/ZnsCampaignForm.php`
  - `app/Filament/Resources/ZnsCampaigns/Tables/ZnsCampaignsTable.php`
  - `app/Filament/Resources/ZnsCampaigns/Pages/EditZnsCampaign.php`
- Suggested fix:
  - Tao `ZnsCampaignWorkflowService`; khoa `status` field tren form va route tat ca transition qua workflow action co audit metadata.
- Affected areas:
  - resource/pages/table
  - campaign model/service
- Tests needed:
  - feature test workflow transitions
  - feature test status field read-only
- Dependencies:
  - ZNS-001
- Suggested order: 2
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-002`

## [ZNS-003] Appointment reminder cancel co the race voi worker dang processing va van gui tin
- Severity: Critical
- Category: Concurrency
- Module: ZNS
- Description:
  - `cancelAppointmentReminder()` cap nhat event `processing` thanh `dead`, nhung worker da claim event van goi provider truoc khi finalize va chi re-check status sau khi send.
- Why it matters:
  - Lich hen da doi/huy van co the gui nhac hen sai cho benh nhan.
- Evidence:
  - `app/Services/ZnsAutomationEventPublisher.php`
  - `app/Console/Commands/SyncZnsAutomationEvents.php`
- Suggested fix:
  - Them cancellation coordination theo processing token/pre-send check, hoac dua send vao boundary co final lock va cancel token.
- Affected areas:
  - automation event publisher
  - sync command
  - appointment reminder flow
- Tests needed:
  - race regression test cho cancel-vs-processing
  - feature test appointment ineligible khong gui outbound sau claim
- Dependencies:
  - APPT, CARE
- Suggested order: 3
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-003`

## [ZNS-004] Outbox/delivery/log dang luu phone va payload ZNS plaintext
- Severity: High
- Category: Security
- Module: ZNS
- Description:
  - `zns_automation_events`, `zns_campaign_deliveries`, `zns_automation_logs` dang luu phone, normalized_phone, payload provider request/response dang plaintext.
- Why it matters:
  - Vi pham boundary PII/PHI operational, tang blast radius khi log/event tables bi lo.
- Evidence:
  - `database/migrations/2026_03_04_141133_create_zns_automation_events_table.php`
  - `database/migrations/2026_03_01_190652_create_zns_campaign_deliveries_table.php`
  - `database/migrations/2026_03_04_141133_create_zns_automation_logs_table.php`
- Suggested fix:
  - Encrypt/redact phone va payload nhay cam, giu lai chi telemetry can cho retry/forensic, va them retention policy.
- Affected areas:
  - schema
  - provider client/logging
  - observability/export
- Tests needed:
  - feature test redaction/encryption
  - retention command tests
- Dependencies:
  - PAT, OPS
- Suggested order: 4
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-004`

## [ZNS-005] Campaign runner chua co campaign-level lock
- Severity: High
- Category: Concurrency
- Module: ZNS
- Description:
  - `runCampaign()` khong claim campaign-level processing token/lock, nen hai worker co the cung scan audience va cung refresh summary.
- Why it matters:
  - Tang chi phi, flap status, va mo duong race summary trong campaign lon/retry.
- Evidence:
  - `app/Services/ZnsCampaignRunnerService.php`
- Suggested fix:
  - Them campaign claim lock hoac processing token + TTL; chi 1 runner duoc active tren 1 campaign tai 1 thoi diem.
- Affected areas:
  - runner service
  - campaign schema/model
- Tests needed:
  - concurrency test cho duplicate runner claim
- Dependencies:
  - ZNS-002
- Suggested order: 5
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-005`

## [ZNS-006] RunZnsCampaigns khong duoc bao ve bang action permission
- Severity: High
- Category: Security
- Module: ZNS
- Description:
  - Command `zns:run-campaigns` khong goi `ActionGate::authorize()` trong khi command `zns:sync-automation-events` da co.
- Why it matters:
  - Boundary command khong dong nhat, de mo duong execute sai actor khi duoc goi thu cong.
- Evidence:
  - `app/Console/Commands/RunZnsCampaigns.php`
  - `app/Console/Commands/SyncZnsAutomationEvents.php`
- Suggested fix:
  - Them `ActionGate::authorize(ActionPermission::AUTOMATION_RUN, ...)` va test command auth.
- Affected areas:
  - console command
  - automation RBAC matrix
- Tests needed:
  - feature test command auth
- Dependencies:
  - GOV
- Suggested order: 6
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-006`

## [ZNS-007] Filament UX cho ZNS de thao tac sai va thieu triage view
- Severity: Medium
- Category: UX
- Module: ZNS
- Description:
  - Form cho edit raw `status`, `message_payload`; `ZaloZns` chi la placeholder; deliveries relation manager thieu filters retry/dead/provider code.
- Why it matters:
  - Operator de doi status sai, kho triage dead-letter/retry va kho support van hanh production.
- Evidence:
  - `app/Filament/Pages/ZaloZns.php`
  - `app/Filament/Resources/ZnsCampaigns/Schemas/ZnsCampaignForm.php`
  - `app/Filament/Resources/ZnsCampaigns/RelationManagers/DeliveriesRelationManager.php`
- Suggested fix:
  - Status read-only, them workflow actions/reason modal, them dead-letter triage filters, bien `ZaloZns` thanh operational dashboard.
- Affected areas:
  - page/resource/relation manager
- Tests needed:
  - feature tests cho filter/schema wiring
- Dependencies:
  - ZNS-002
- Suggested order: 7
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-007`

## [ZNS-008] Coverage thieu auth matrix, cancel-processing race va payload governance
- Severity: Medium
- Category: Maintainability
- Module: ZNS
- Description:
  - Test hien cover retry reliability kha tot, nhung chua co suite rieng cho auth page/resource, cancel-vs-processing race, command auth va redaction/retention.
- Why it matters:
  - Day la module outbound side-effect; regression se rat de quay lai neu khong co suite dung tam.
- Evidence:
  - `tests/Feature/ZnsAutomationPipelineTest.php`
  - `tests/Feature/ZnsCampaignRunnerReliabilityTest.php`
  - `tests/Feature/RunZnsCampaignsCommandTest.php`
- Suggested fix:
  - Bo sung feature tests cho auth matrix, race case, command auth va payload governance.
- Affected areas:
  - `tests/Feature/*Zns*`
- Tests needed:
  - chinh issue nay la regression backlog
- Dependencies:
  - ZNS-001 -> ZNS-007
- Suggested order: 8
- Current status: Resolved
- Linked task IDs: `TASK-ZNS-008`

# Summary

- Open critical count: 0
- Open high count: 0
- Open medium count: 0
- Open low count: 0
- Next recommended action: Khong con issue baseline mo. Tiep theo la rollout migration ZNS moi va smoke test page `ZaloZns` tren du lieu that.
