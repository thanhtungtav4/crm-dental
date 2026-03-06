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
| GOV | Governance / Branches / RBAC / Audit | Clean Baseline Reached | [Review](modules/GOV-branches-rbac-audit.md) | [Issues](../issues/GOV-issues.md) | [Plan](../planning/GOV-plan.md) | B | Khong con open blocker baseline; follow-up van hanh la sync permission baseline tren DB da seed truoc day va formalize governance delegation matrix neu can | PAT, APPT, CLIN, TRT, FIN, INV, CARE, ZNS, OPS |
| PAT | Customers / Patients / MPI | Clean Baseline Reached | [Review](modules/PAT-customers-patients.md) | [Issues](../issues/PAT-issues.md) | [Plan](../planning/PAT-plan.md) | B | Khong con open blocker baseline; follow-up sau baseline la SOP/aging dashboard cho MPI queue neu van hanh can | GOV, APPT, CLIN, FIN, CARE, ZNS |
| APPT | Appointments / Calendar | Clean Baseline Reached | [Review](modules/APPT-appointments-calendar.md) | [Issues](../issues/APPT-issues.md) | [Plan](../planning/APPT-plan.md) | B | Khong con open blocker baseline; residual risk la theo doi queue worker health cho appointment side-effects after-commit | GOV, PAT, CLIN, TRT, CARE, ZNS, INT |
| CLIN | Clinical Records / Consent | Clean Baseline Reached | [Review](modules/CLIN-clinical-records.md) | [Issues](../issues/CLIN-issues.md) | [Plan](../planning/CLIN-plan.md) | B | Khong con open blocker baseline; follow-up sau baseline la UX consent production-grade va imaging upload guidance | GOV, PAT, APPT, TRT, FIN, INT |
| TRT | Treatment Plans / Sessions / Materials usage | Clean Baseline Reached | [Review](modules/TRT-treatment.md) | [Issues](../issues/TRT-issues.md) | [Plan](../planning/TRT-plan.md) | B | Khong con open blocker baseline; theo doi tiep drift giua treatment, inventory va finance bang full-suite regression | PAT, APPT, CLIN, INV, FIN |
| FIN | Finance / Payments / Wallet / Installments | Clean Baseline Reached | [Review](modules/FIN-finance.md) | [Issues](../issues/FIN-issues.md) | [Plan](../planning/FIN-plan.md) | B | Khong con open blocker baseline; tiep tuc theo doi drift giua finance, inventory va KPI bang full-suite regression | GOV, PAT, APPT, TRT, INV, KPI |
| INV | Inventory / Batches / Stock | Pending Review | [Review](modules/INV-inventory.md) | [Issues](../issues/INV-issues.md) | [Plan](../planning/INV-plan.md) | TBD | TODO | GOV, TRT, FIN, SUP, KPI |
| SUP | Suppliers / Factory Orders | Pending Review | [Review](modules/SUP-suppliers-factory.md) | [Issues](../issues/SUP-issues.md) | [Plan](../planning/SUP-plan.md) | TBD | TODO | INV, FIN, GOV |
| CARE | Customer Care / Automation | Pending Review | [Review](modules/CARE-customer-care-automation.md) | [Issues](../issues/CARE-issues.md) | [Plan](../planning/CARE-plan.md) | TBD | TODO | PAT, APPT, FIN, ZNS, KPI |
| ZNS | Zalo / ZNS | Pending Review | [Review](modules/ZNS-zalo-zns.md) | [Issues](../issues/ZNS-issues.md) | [Plan](../planning/ZNS-plan.md) | TBD | TODO | PAT, APPT, CARE, INT, OPS |
| INT | Integrations | Pending Review | [Review](modules/INT-integrations.md) | [Issues](../issues/INT-issues.md) | [Plan](../planning/INT-plan.md) | TBD | TODO | GOV, APPT, CLIN, ZNS, OPS |
| KPI | Reports / KPI | Pending Review | [Review](modules/KPI-reports-kpi.md) | [Issues](../issues/KPI-issues.md) | [Plan](../planning/KPI-plan.md) | TBD | TODO | FIN, INV, CARE, OPS |
| OPS | Production readiness / backup / observability | Pending Review | [Review](modules/OPS-production-readiness.md) | [Issues](../issues/OPS-issues.md) | [Plan](../planning/OPS-plan.md) | TBD | TODO | GOV, INT, KPI, ZNS, FIN, INV |

# 3. Cross-module risks

- GOV da dat clean baseline va tiep tuc la nen cho governance/RBAC toan he thong.
- PAT da dat clean baseline, khoa patient identity boundary, customer->patient conversion idempotency va MPI operator workflow.
- `APPT` da dat clean baseline; scheduling, overbooking auth, reschedule audit, encrypted search va observer side-effects da duoc khoa bang regression test.
- `CLIN` da dat clean baseline; EMR PHI, consent lifecycle, session idempotency, branch-scoped doctor assignment va audit timeline reader da duoc khoa bang regression test.
- `TRT` da dat clean baseline; batch-safe material usage, workflow state machine, branch-scoped assignment va destructive guard da duoc khoa bang regression test.
- `FIN` da dat clean baseline; wallet authorization, invoice workflow, refund idempotency va canonical payment boundary da duoc khoa bang regression test.
- `INV` la module phu thuoc gan nhat sau khi `FIN` khoa xong payment/invoice boundary.

# 4. Priority overview

- Critical modules: Chua co module critical dang mo sau khi `FIN` dat baseline
- High priority modules: `INV`
- Medium priority modules: `CARE`, `ZNS`, `INT`, `OPS`, `SUP`, `KPI`
- Low priority modules: Chua xac dinh cho den khi co review chi tiet.

# 5. Modules ready for deep fix

- `INV` - inventory batch/stock la module phu thuoc gan nhat sau khi `FIN` khoa xong payment/invoice boundary.

# 6. Modules needing re-audit

- Chua co module nao can re-audit ngay luc nay.

# 7. Suggested next module to review

- `INV` - module tiep theo nen duoc review sau khi `FIN` da dat clean baseline.

# 8. Suggested next module to fix

- `INV` - bat dau review inventory batch/stock boundary sau khi finance baseline da on dinh.
