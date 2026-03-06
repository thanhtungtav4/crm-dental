# 1. Tong quan he thong

He thong duoc review theo chien luoc `module nao sach module do`.

## Muc tieu

- Theo doi phase hien tai cua tung module
- Giu source of truth cho review, issue, plan, va verdict
- Tong hop top open risks tren toan bo chuong trinh review
- Huong dan AI sau doc lai nhanh va biet module nao review tiep, module nao fix tiep

## Phase enum

| Status | Nghia |
| --- | --- |
| Pending Review | Module chua duoc review |
| Reviewing | Dang review va findings co the thay doi |
| Reviewed | Da co review va issue list ban dau |
| Planning | Dang lap implementation plan |
| In Fix | Dang fix task cua module |
| Re-audit Needed | Da sua code va can audit lai |
| Clean Baseline Reached | Da review, fix, test, va re-audit xong cho baseline hien tai |

## Quy uoc dinh danh

- Issue ID: `APPT-001`, `FIN-004`, `CLIN-002`
- Task ID: `TASK-APPT-001`, `TASK-FIN-003`

# 2. Danh sach module

| Module code | Module name | Current status | Review file | Issue file | Plan file | Current verdict | Top open risks | Dependencies |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GOV | Governance / Branches / RBAC / Audit | In Fix | [Review](modules/GOV-branches-rbac-audit.md) | [Issues](../issues/GOV-issues.md) | [Plan](../planning/GOV-plan.md) | D | Manager privilege escalation dang duoc rollout sync; user provisioning actor/branch scope dang duoc khoa; audit log overexposure | PAT, APPT, CLIN, TRT, FIN, INV, CARE, ZNS, OPS |
| PAT | Customers / Patients / MPI | Pending Review | [Review](modules/PAT-customers-patients.md) | [Issues](../issues/PAT-issues.md) | [Plan](../planning/PAT-plan.md) | TBD | TODO | GOV, APPT, CLIN, FIN, CARE, ZNS |
| APPT | Appointments / Calendar | Pending Review | [Review](modules/APPT-appointments-calendar.md) | [Issues](../issues/APPT-issues.md) | [Plan](../planning/APPT-plan.md) | TBD | TODO | GOV, PAT, CLIN, TRT, INT, ZNS |
| CLIN | Clinical Records / Consent | Pending Review | [Review](modules/CLIN-clinical-records.md) | [Issues](../issues/CLIN-issues.md) | [Plan](../planning/CLIN-plan.md) | TBD | TODO | GOV, PAT, APPT, TRT, FIN, INT |
| TRT | Treatment Plans / Sessions / Materials usage | Pending Review | [Review](modules/TRT-treatment.md) | [Issues](../issues/TRT-issues.md) | [Plan](../planning/TRT-plan.md) | TBD | TODO | PAT, APPT, CLIN, INV, FIN |
| FIN | Finance / Payments / Wallet / Installments | Pending Review | [Review](modules/FIN-finance.md) | [Issues](../issues/FIN-issues.md) | [Plan](../planning/FIN-plan.md) | TBD | TODO | GOV, PAT, APPT, TRT, INV, KPI |
| INV | Inventory / Batches / Stock | Pending Review | [Review](modules/INV-inventory.md) | [Issues](../issues/INV-issues.md) | [Plan](../planning/INV-plan.md) | TBD | TODO | GOV, TRT, FIN, SUP, KPI |
| SUP | Suppliers / Factory Orders | Pending Review | [Review](modules/SUP-suppliers-factory.md) | [Issues](../issues/SUP-issues.md) | [Plan](../planning/SUP-plan.md) | TBD | TODO | INV, FIN, GOV |
| CARE | Customer Care / Automation | Pending Review | [Review](modules/CARE-customer-care-automation.md) | [Issues](../issues/CARE-issues.md) | [Plan](../planning/CARE-plan.md) | TBD | TODO | PAT, APPT, FIN, ZNS, KPI |
| ZNS | Zalo / ZNS | Pending Review | [Review](modules/ZNS-zalo-zns.md) | [Issues](../issues/ZNS-issues.md) | [Plan](../planning/ZNS-plan.md) | TBD | TODO | PAT, APPT, CARE, INT, OPS |
| INT | Integrations | Pending Review | [Review](modules/INT-integrations.md) | [Issues](../issues/INT-issues.md) | [Plan](../planning/INT-plan.md) | TBD | TODO | GOV, APPT, CLIN, ZNS, OPS |
| KPI | Reports / KPI | Pending Review | [Review](modules/KPI-reports-kpi.md) | [Issues](../issues/KPI-issues.md) | [Plan](../planning/KPI-plan.md) | TBD | TODO | FIN, INV, CARE, OPS |
| OPS | Production readiness / backup / observability | Pending Review | [Review](modules/OPS-production-readiness.md) | [Issues](../issues/OPS-issues.md) | [Plan](../planning/OPS-plan.md) | TBD | TODO | GOV, INT, KPI, ZNS, FIN, INV |

# 3. Cross-module risks

- GOV dang la module blocker cho `PAT`, `APPT`, `CLIN`, `FIN`, `INV` vi branch scoping va RBAC chua dat baseline an toan.
- Audit log overexposure cua GOV co the lan sang workflow clinical, finance, automation va observability.
- Branch transfer concurrency gap cua GOV co the gay invalid ownership state cho patient, appointment va treatment data.

# 4. Priority overview

- Critical modules: `GOV`
- High priority modules: `PAT`, `APPT`, `CLIN`, `FIN`, `INV`
- Medium priority modules: `CARE`, `ZNS`, `INT`, `OPS`, `TRT`, `SUP`, `KPI`
- Low priority modules: Chua xac dinh cho den khi co review chi tiet.

# 5. Modules ready for deep fix

- `GOV` - dang fix `TASK-GOV-001`, `TASK-GOV-002`, `TASK-GOV-003`; cac task con lai se theo sau khi khoa xong audit visibility.

# 6. Modules needing re-audit

- Chua co module nao dat moc `Re-audit Needed`.

# 7. Suggested next module to review

- `PAT` - sau GOV, day la module tiep theo co muc do phu thuoc cao nhat vao branch ownership, MPI va patient access scope.

# 8. Suggested next module to fix

- `GOV` - tiep tuc `TASK-GOV-003` sau khi da chot commit rieng cho `TASK-GOV-001` va `TASK-GOV-002`.
