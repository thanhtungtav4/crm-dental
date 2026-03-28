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
- Last updated: `2026-03-28`

## Executive Summary

- `13/13` module da dat `Clean Baseline Reached`.
- `100/100` issue baseline trong issue register da duoc danh dau `Resolved`.
- Khong con open code blocker co do tin cay cao trong baseline hien tai.
- Production rollout va real-infrastructure smoke-test pack da pass tren environment that ngay `2026-03-28`.
- Governance delegation matrix da duoc formalize thanh tai lieu van hanh.
- Trung tam rui ro da chuyen tu `rollout safety` sang `shared contracts`, `workflow convergence`, va `structural convergence`.
- Chuong trinh hien tai nen di tiep theo batch nho, uu tien rollout/smoke test truoc, refactor sau.
- `RRB-007` va `RRB-008` da duoc dong qua shared identity/search contract va actor/branch scope contract.
- `RRB-009` va `RRB-010` dang duoc trien khai theo batch nho tren branch lam viec; cac batch moi nhat da dua `Appointment`, `VisitEpisode`, `BranchTransferRequest`, `MasterPatientDuplicate / MasterPatientMerge`, `PopupAnnouncement`, `PlanItem`, `ReceiptExpense`, `MaterialIssueNote`, `InsuranceClaim`, `ClinicalOrder`, `ClinicalResult`, `CareTicket/Note`, `Consent`, `ExamSession / TreatmentProgress`, `WebLeadEmailDelivery`, `ZnsCampaignDelivery`, cac lane outbox `ZnsAutomationEvent / GoogleCalendarSyncEvent / EmrSyncEvent`, `OperationalKpiSnapshotReadModelService`, `OperationalKpiAlertReadModelService`, `OperationalAutomationAuditReadModelService`, `ReportSnapshotReadModelService`, `PatientActivityTimelineReadModelService`, `GovernanceAuditReadModelService`, `ZnsOperationalReadModelService`, `IntegrationOperationalReadModelService`, `IntegrationSettingsAuditReadModelService`, va finance payment audit metadata vao workflow + audit contract; trong do `ExamSession / TreatmentProgress` da vao managed mutation path den cap `TreatmentProgressDay / TreatmentProgressItem`, `MasterPatientDuplicate` da co canonical workflow service cho ignore / merge-resolution / rollback-restore / auto-ignore tu MPI index, `BranchTransferRequest` da co them reader surface tren patient operational timeline, `WebLeadEmailDelivery` va `ZnsCampaignDelivery` da chuyen sang managed delivery mutation path, `PatientActivityTimelineWidget` da duoc doi sang render read-model hop nhat thay vi tu query du lieu truc tiep, `OpsControlCenterService` da tiep tuc mat raw governance audit query, raw ZNS state-count/automation-log retention/prune query, raw integration retention/backlog/prune query, raw recent setting-log query, va grace-state read query de chuyen sang reader rieng, `sync:emr` / `sync:google-calendar` / `sync:zns` da bat dau dung reader cho scoped dead-letter backlog counts, `ZaloZns` da bat dau dung reader cho branch-scoped operational summary cards, va `OperationalKpiPack` / `OpsControlCenter` / `ops:check-observability-health` / `integrations:prune-operational-data` / `zns:prune-operational-data` / `OperationalKpiAlertService` / `CheckSnapshotSla` / `CompareReportSnapshots` / `IntegrationSettings` da bat dau dung chung reader convention cho KPI snapshot, KPI alert summary, control-plane automation audit, active-alert counting, snapshot SLA violation counts, ZNS backlog/retention counts, va integration retention/backlog/grace-state counts, chua push runtime len `main`.

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

1. RRB-009 va RRB-010: workflow + audit convergence, voi lane `PAT/MPI` va outbox/event noi bo da vao contract, con lai chu yeu la cac lane delivery/read-model bo sung va phan doc timeline hop nhat.
2. Mo rong read-model timeline ngoai patient activity neu nghiep vu can.
3. Chot lane observer/service con lai truoc khi vao structural refactor lon.

## Structural Risks

1. Treatment, inventory, finance, supplier van can mot chien luoc immutable ledger/adjustment thong nhat hon.
2. Reporting va snapshot platform can tiep tuc tach read-model khoi page logic khi dataset tang.
3. Integration control-plane can tiep tuc duoc tach ro health/runtime/secret lanes de giam drift van hanh; lane dau tien da bat dau bang fail-fast runtime readiness cho `emr:sync-events`, va da tiep tuc sang `zns:run-campaigns` voi readiness check dung chung tu `ZnsProviderClient`.

## Suggested Implementation Order

1. Chot `RRB-009` va `RRB-010` cho workflow/audit lanes uu tien.
2. Sau do moi vao `RRB-011`, `RRB-012`, `RRB-013`.

## Canonical Next Documents

- [Review Master Index](00-master-index.md)
- [Refactor/Review Master Backlog](../roadmap/refactor-review-master-backlog.md)
- [Refactor/Review Execution Plan](../roadmap/refactor-review-execution-plan.md)
