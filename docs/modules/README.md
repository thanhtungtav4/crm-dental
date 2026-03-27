# CRM Module Inventory

Danh sach nay la ban do module de dung cho onboarding, review, va lap backlog.

| Code | Module | Trach nhiem chinh | Surface chinh | Core model/service | Review |
| --- | --- | --- | --- | --- | --- |
| `GOV` | Governance / Branches / RBAC / Audit | branch scope, role/permission, auditability, governance safety | Branches, Users, Audit Logs, Branch Logs | `Branch`, `User`, `AuditLog`, `GovernanceResourcePermissionBaselineService` | [GOV review](../reviews/modules/GOV-branches-rbac-audit.md) |
| `PAT` | Customers / Patients / MPI | lead-customer-patient lifecycle, MPI, patient workspace | Customers, Patients, Master Patient Duplicates | `Customer`, `Patient`, `MasterPatientDuplicate`, `PatientConversionService` | [PAT review](../reviews/modules/PAT-customers-patients.md) |
| `APPT` | Appointments / Calendar | scheduling, overbooking, visit flow, calendar | Appointment resource, calendar, mobile appointment API | `Appointment`, `VisitEpisode`, `AppointmentSchedulingService` | [APPT review](../reviews/modules/APPT-appointments-calendar.md) |
| `CARE` | Customer Care / Automation | care ticket lifecycle, recall, follow-up, frontdesk control | CustomerCare, FrontdeskControlCenter, Notes | `Note`, `CareTicketService`, `CareTicketWorkflowService` | [CARE review](../reviews/modules/CARE-customer-care-automation.md) |
| `CLIN` | Clinical Records / Consent | EMR baseline, clinical note, consent, media, encounter | Patient medical records, clinical notes, consent-related flow | `PatientMedicalRecord`, `ClinicalNote`, `Consent`, `ClinicalNoteVersioningService` | [CLIN review](../reviews/modules/CLIN-clinical-records.md) |
| `TRT` | Treatment Plans / Sessions | treatment lifecycle, session execution, progress, prescription linkage | Treatment Plans, Plan Items, Treatment Sessions | `TreatmentPlan`, `PlanItem`, `TreatmentSession`, `TreatmentPlanWorkflowService` | [TRT review](../reviews/modules/TRT-treatment.md) |
| `FIN` | Finance / Payments / Wallet | invoicing, payments, wallet, installment, finance controls | Invoices, Payments, Patient Wallets, Financial Dashboard | `Invoice`, `Payment`, `PatientWallet`, `PaymentRecordingService` | [FIN review](../reviews/modules/FIN-finance.md) |
| `INV` | Inventory / Batches / Stock | material master, batch tracking, stock mutation, issue notes | Materials, Material Batches, Material Issue Notes | `Material`, `MaterialBatch`, `InventoryTransaction`, `InventoryMutationService` | [INV review](../reviews/modules/INV-inventory.md) |
| `SUP` | Suppliers / Factory Orders | supplier master, labo/factory workflow | Suppliers, Factory Orders, Factory Statistical | `Supplier`, `FactoryOrder`, `FactoryOrderWorkflowService` | [SUP review](../reviews/modules/SUP-suppliers-factory.md) |
| `INT` | Integrations | runtime settings, EMR bridge, Google Calendar, web lead mail | IntegrationSettings, web lead API, internal EMR mutation | `ClinicSetting`, `WebLeadEmailDelivery`, `GoogleCalendarIntegrationService`, `RuntimeMailerFactory` | [INT review](../reviews/modules/INT-integrations.md) |
| `ZNS` | Zalo / ZNS | webhook inbound, automation, campaigns, delivery | ZaloZns, ZnsCampaignResource | `ZnsCampaign`, `ZnsCampaignDelivery`, `ZnsCampaignRunnerService` | [ZNS review](../reviews/modules/ZNS-zalo-zns.md) |
| `KPI` | Reports / KPI | reports, aggregates, alerts, snapshot lineage | Report pages, OperationalKpiPack, dashboards | `ReportSnapshot`, `OperationalKpiAlert`, `OperationalKpiService` | [KPI review](../reviews/modules/KPI-reports-kpi.md) |
| `OPS` | Ops / Production Readiness | backup, restore, release gates, observability, readiness | OpsControlCenter, DeliveryOpsCenter, readiness commands | `ReportSnapshot`, `BackupArtifactService`, `OpsControlCenterService` | [OPS review](../reviews/modules/OPS-production-readiness.md) |

## Ghi chu su dung

- Review theo module de tranh sua tran lan tren he thong dang auto-deploy.
- Moi module co 3 artifact chinh: review narrative, issue register, implementation plan.
- Backlog refactor/review tong hop se duoc build tren nen module map nay.
