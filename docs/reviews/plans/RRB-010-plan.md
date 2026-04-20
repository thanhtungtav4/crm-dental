# RRB-010 Plan: Unified Audit Timeline and Read-Model Conventions

## Metadata

- status: Completed
- last_updated: 2026-04-16
- scope: `PAT`, `GOV`, `CLIN`, `FIN`, `INT`, `OPS`, `ZNS`
- dependency: RRB-003 (Completed), RRB-009 (Completed)

## Objective

Hop nhat cach doc audit timeline, operational event, va provenance read-model de de trace incident va nghiep vu. Tach event writer voi event reader de khong rang buoc UI vao table raw.

## Completed Tasks

### Read-model services da duoc tao

| Service | Module | Test File |
|---|---|---|
| `PatientOperationalTimelineService` | PAT | `PatientOperationalTimelineServiceTest.php` |
| `PatientActivityTimelineReadModelService` | PAT | `PatientActivityTimelineReadModelServiceTest.php` |
| `ClinicalAuditTimelineService` | CLIN | `ClinicalAuditTimelineServiceTest.php` |
| `PatientOverviewReadModelService` | PAT | `PatientOverviewReadModelServiceTest.php` |
| `PatientAppointmentActionReadModelService` | PAT | `PatientAppointmentActionReadModelServiceTest.php` |
| `PatientExamStatusReadModelService` | PAT/CLIN | `PatientExamStatusReadModelServiceTest.php` |
| `PatientExamMediaReadModelService` | PAT/CLIN | `PatientExamMediaReadModelServiceTest.php` |
| `PatientExamReferenceReadModelService` | PAT/CLIN | `PatientExamReferenceReadModelServiceTest.php` |
| `PatientExamSessionReadModelService` | PAT/CLIN | `PatientExamSessionReadModelServiceTest.php` |
| `PatientExamDoctorReadModelService` | PAT/CLIN | `PatientExamDoctorReadModelServiceTest.php` |
| `PatientTreatmentPlanReadModelService` | PAT/TRT | `PatientTreatmentPlanReadModelServiceTest.php` |
| `PatientInsightReportReadModelService` | PAT | `PatientInsightReportReadModelServiceTest.php` |
| `CustomerCareSlaReadModelService` | CARE | `CustomerCareSlaReadModelServiceTest.php` ✅ |
| `ConversationInboxReadModelService` | INT/CARE | `ConversationInboxReadModelServiceTest.php` ✅ |
| `GovernanceAuditReadModelService` | GOV | `GovernanceAuditReadModelServiceTest.php` |
| `OperationalKpiSnapshotReadModelService` | OPS/KPI | `OperationalKpiSnapshotReadModelServiceTest.php` |
| `OperationalKpiAlertReadModelService` | OPS/KPI | `OperationalKpiAlertReadModelServiceTest.php` |
| `OperationalAutomationAuditReadModelService` | OPS | `OperationalAutomationAuditReadModelServiceTest.php` |
| `OperationalStatsReadModelService` | OPS | `OperationalStatsReadModelServiceTest.php` |
| `ReportSnapshotReadModelService` | OPS/KPI | `ReportSnapshotReadModelServiceTest.php` |
| `ZnsOperationalReadModelService` | ZNS | `ZnsOperationalReadModelServiceTest.php` |
| `IntegrationOperationalReadModelService` | INT | `IntegrationOperationalReadModelServiceTest.php` |
| `IntegrationSettingsAuditReadModelService` | INT | `IntegrationSettingsAuditReadModelServiceTest.php` |
| `IntegrationProviderHealthReadModelService` | INT | `IntegrationProviderHealthReadModelServiceTest.php` |
| `FinanceOperationalReadModelService` | FIN | `FinanceOperationalReadModelServiceTest.php` |
| `PopupAnnouncementCenterReadModelService` | INT | `PopupAnnouncementCenterReadModelServiceTest.php` |

### Catalog contracts da duoc tao va test

| Catalog | Location | Test File |
|---|---|---|
| `OpsAutomationCatalog` | `app/Support/OpsAutomationCatalog.php` | `OpsAutomationCatalogContractTest.php` ✅ |
| `OpsReleaseGateCatalog` | `app/Support/OpsReleaseGateCatalog.php` | `OpsAutomationCatalogContractTest.php` ✅ |

## Test Summary

| Batch | Tests |
|---|---|
| Timeline tests (PatientOperationalTimeline, PatientActivityTimeline, ClinicalAuditTimeline, AuditLogTest) | 15/15 |
| CustomerCareSlaReadModelServiceTest | 11/11 |
| ConversationInboxReadModelServiceTest | 14/14 |
| OpsAutomationCatalogContractTest | 20/20 |
| **New batch total** | **45/45, 363 assertions** |

## Key Patterns Established

- Event writer vs event reader separation: `PatientOperationalTimelineService` / `PatientActivityTimelineReadModelService` / `ClinicalAuditTimelineService`
- Branch-scoped read-model với `BranchAccess::accessibleBranchIds()`
- Authorization contract: `scopeVisibleTo(User)` trên Conversation, `GovernanceAuditReadModelService::recentAudits()` respect branch visibility
- Ops catalog single source of truth: `OpsAutomationCatalog::trackedCommands()` / `scheduledAutomationTargets()` / `smokeCommands()` / `scheduledAutomationDefinitions()`
- Release gate single source of truth: `OpsReleaseGateCatalog::steps(profile, withFinance)` / `requiredProductionCommands()`
