# Metadata

- Module code: `KPI`
- Module name: `Reports / KPI`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Review file: `docs/reviews/modules/KPI-reports-kpi.md`
- Issue file: `docs/issues/KPI-issues.md`
- Plan file: `docs/planning/KPI-plan.md`
- Issue ID prefix: `KPI-`
- Task ID prefix: `TASK-KPI-`
- Dependencies: `FIN, INV, CARE, OPS`
- Last updated: `2026-03-07`

# Scope

- Filament report pages trong `app/Filament/Pages/Reports/*`
- Snapshot pipeline KPI: `reports:snapshot-operational-kpis`, `reports:check-snapshot-sla`, `reports:compare-snapshots`
- Read models cho báo cáo nóng: `report_snapshots`, `report_revenue_daily_aggregates`, `report_care_queue_daily_aggregates`, `operational_kpi_alerts`
- KPI service / alert service / lineage service và phần scheduling liên quan

# Context

- Module KPI đang tổng hợp dữ liệu từ `APPT`, `PAT`, `TRT`, `FIN`, `CARE`, `INV`
- Đây là module có rủi ro cao vì vừa hiển thị dữ liệu vận hành nhạy cảm, vừa có command tạo snapshot và alert ảnh hưởng toàn hệ thống
- Thong tin con thieu lam giam do chinh xac review: chua co ma tran role chinh thuc cho tung report page, chua co quy uoc owner chuan cho alert KPI trong he thong multi-branch, chua co SLO tai lieu hoa cho freshness cua aggregate/read model

# Executive Summary

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`
- Cac canh bao nghiem trong:
  - Report pages dang mo qua rong; runtime check xac nhan `Doctor` co the vao `AppointmentStatistical`, `RevenueStatistical`, `OperationalKpiPack`, `RiskScoringDashboard`
  - Query report hien tai khong branch-aware theo actor; non-admin co the thay du lieu toan he thong neu khong chon filter
  - `reports:snapshot-operational-kpis` va `reports:check-snapshot-sla` chi guard bang `Action:AutomationRun`; `Manager` hien co the chay snapshot cho toan bo chi nhanh

# Re-audit Update

- Verdict hien tai: `B`
- Clean baseline status: `Yes`
- Issue da dong:
  - `KPI-001` auth boundary cho report pages
  - `KPI-002` branch-aware query/export scope
  - `KPI-003` automation command scope cho snapshot/SLA/hot aggregate
  - `KPI-004` canonical alert owner resolver theo branch
  - `KPI-005` freshness gate + raw fallback cho hot aggregates
  - `KPI-006` giam hydrate collection lon trong snapshot hot path
  - `KPI-007` contract auth/scope chung o `BaseReportPage`
  - `KPI-008` regression suite cho auth/scope/freshness/automation
- Follow-up con lai la rollout:
  - smoke test report filters/export tren production dataset lon
  - theo doi runtime `reports:snapshot-operational-kpis` va `reports:snapshot-hot-aggregates` sau deploy
  - neu so branch tang manh, xem xet queue/batch per branch cho snapshot nightly

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - [AppointmentStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/AppointmentStatistical.php#L27)
  - [RevenueStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/RevenueStatistical.php#L33)
  - [OperationalKpiPack.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/OperationalKpiPack.php#L29)
  - Runtime check: `Doctor` => `AppointmentStatistical::canAccess()`, `RevenueStatistical::canAccess()`, `OperationalKpiPack::canAccess()`, `RiskScoringDashboard::canAccess()` deu `true`
- Findings:
  - Hầu hết report pages khong co `canAccess()` rieng, trong khi `BaseReportPage` cung khong ep buoc authorization contract.
  - `AppointmentStatistical` va export CSV expose truc tiep `patient_code`, `full_name`, `phone` ma khong co scope/auth gate bo sung.
  - `OperationalKpiPack` expose snapshot KPI lien chi nhanh, alert count, benchmark doctor ma khong branch-scope theo actor.
- Suggested direction:
  - Tao base authorization contract cho toan bo report page.
  - Chot role matrix toi thieu cho KPI baseline: `Admin` / `Manager`; page can quan CSKH thi opt-in ro rang.
  - Export phai di qua cung authorization va branch scope nhu table query.

## Data Integrity & Database

- Danh gia: `Trung binh`
- Evidence:
  - [2026_02_27_111131_add_branch_scope_key_to_report_snapshots_table.php](/Users/macbook/Herd/crm/database/migrations/2026_02_27_111131_add_branch_scope_key_to_report_snapshots_table.php#L14)
  - [2026_03_01_180250_create_report_revenue_daily_aggregates_table.php](/Users/macbook/Herd/crm/database/migrations/2026_03_01_180250_create_report_revenue_daily_aggregates_table.php#L9)
  - [RevenueStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/RevenueStatistical.php#L151)
  - [CustomsCareStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/CustomsCareStatistical.php#L212)
- Findings:
  - `report_snapshots` va aggregate tables da co unique/composite index hop ly, day la diem tot.
  - Tuy nhien, logic chon aggregate/read model dang dua tren `exists()` toan bang, khong co freshness gate theo ngay/scope. Mot aggregate cu van co the ep report dung read model stale va bo qua raw data moi.
  - `OperationalKpiAlert` co unique `(snapshot_id, metric_key)` tot, nhung `owner_user_id` khong duoc xac dinh boi branch ownership contract ro rang.
- Suggested direction:
  - Them freshness policy cho aggregate snapshot va fallback ve raw query neu snapshot thieu/stale.
  - Chuan hoa owner assignment cho alert theo branch-access/owner registry thay vi `users.branch_id` thuần.

## Concurrency / Race-condition

- Danh gia: `Trung binh`
- Evidence:
  - [SnapshotOperationalKpis.php](/Users/macbook/Herd/crm/app/Console/Commands/SnapshotOperationalKpis.php#L186)
  - [OperationalKpiAlertService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiAlertService.php#L47)
- Findings:
  - `upsertSnapshot()` da co transaction + `lockForUpdate()` + retry unique violation, day la diem tot.
  - Rui ro lon hon nam o mutation scope: command hien co the duoc `Manager` chay cho tat ca branch neu khong truyen `--branch_id`, tao drift va alert tren toan he thong.
  - Alert evaluation khong duoc bao boc boi service boundary transaction voi snapshot creation; hien tai chua thay bug du lieu, nhung can boundary ro rang hon neu runner song song tang len.
- Suggested direction:
  - Hard-scope command theo actor branch access neu khong phai automation service/admin.
  - Gom snapshot persist + alert evaluation vao canonical orchestration boundary co authorization ro rang.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - [OperationalKpiService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiService.php#L30)
  - [OperationalKpiService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiService.php#L90)
  - [OperationalKpiService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiService.php#L193)
  - [SnapshotOperationalKpis.php](/Users/macbook/Herd/crm/app/Console/Commands/SnapshotOperationalKpis.php#L68)
- Findings:
  - Snapshot command loop qua tat ca chi nhanh + toan he thong; moi branch lai chay nhieu query aggregate rieng.
  - `receiptsQuery` va `lifetimeReceiptsQuery` deu `get()->pluck()->unique()` de dem patient, de ton memory khi record payment lon.
  - `buildDoctorBenchmark()` load tat ca appointment rows cua branch/day vao memory roi group trong PHP.
- Suggested direction:
  - Dua KPI sang read-model/pre-aggregate day du hon, hoac toi uu query-level distinct/group thay vi hydrate collection lon.
  - Batch snapshot theo branch queue-safe neu so chi nhanh tang.

## Maintainability

- Danh gia: `Kem`
- Evidence:
  - [BaseReportPage.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/BaseReportPage.php#L17)
  - [CustomsCareStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/CustomsCareStatistical.php#L29)
  - Cac page khac trong `app/Filament/Pages/Reports/*` khong co contract auth/scope thong nhat
- Findings:
  - Chi `CustomsCareStatistical` co auth + branch scope tuong doi dung; cac report khac tu implement theo kieu rieng hoac bo trong.
  - Module thieu shared abstraction cho `canAccess`, `branch filter options`, `scope raw query`, `scope aggregate query`, `export safety`.
- Suggested direction:
  - Tao base report authorizer / branch-scope helper trong `BaseReportPage`.
  - Mọi report page phai khai bao ro required permission va branch column.

## Better Architecture Proposal

- Tao `ReportPageAuthorizer` + helper trong `BaseReportPage` de enforce:
  - `canAccess()`
  - branch option scope
  - raw query scope
  - aggregate query scope
  - export scope
- Tach `OperationalKpiSnapshotRunner` thanh orchestration service, command chi la adapter.
- Chot `ReportFreshnessPolicy` de quyet dinh khi nao duoc doc aggregate, khi nao fallback raw.
- Tao `KpiAlertOwnerResolver` doc tu branch access / role matrix thay vi lookup manager dau tien.

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Trung binh`
- Workflow hien tai:
  - schedule command tao snapshot KPI hang ngay
  - command SLA check cap nhat `sla_status`
  - Filament pages doc snapshot/read-model va export CSV
  - alert duoc tao/auto-resolve theo threshold runtime settings
- Van de:
  - workflow tao snapshot, alert, va doc report chua co ownership/authorization boundary nhat quan cho multi-branch

## State transitions

- Danh gia: `Trung binh`
- `ReportSnapshot` co cac state `success/failed` + `on_time/late/stale/missing`
- `OperationalKpiAlert` co `new/ack/resolved`
- Van de:
  - khong co service/state machine chuan cho ack/resolve alert theo owner branch
  - `markAcknowledged()` va `markResolved()` dang chi guard bang `Action:AutomationRun`

## Missing business rules

- Thieu quy tac ai duoc xem report nao
- Thieu quy tac non-admin duoc xem mot branch hay tong hop cac branch accessible
- Thieu freshness rule cho aggregate reports
- Thieu owner resolution rule cho KPI alert multi-branch
- Thieu boundary cho ai duoc regenerate snapshot toan he thong

## Invalid states / forbidden transitions

- `Doctor` co the vao page KPI va report operational nhay cam
- `Manager` co the chay dry-run snapshot cho tat ca branch thay vi chi branch accessible
- Non-admin co the roi vao `branch_scope_id = 0` o report aggregate va xem snapshot global
- Alert co the assign sai owner neu manager branch dung assignment thay vi `users.branch_id`

## Service / action / state machine / transaction boundary de xuat

- `ReportPageAuthorizer`
  - map report page -> permission/role
  - map actor -> accessible branch scopes
- `KpiSnapshotExecutionService`
  - resolve branch scopes theo actor
  - persist snapshot + evaluate alerts trong mot boundary
- `ReportFreshnessPolicy`
  - aggregate only when snapshot date/scope fresh
  - fallback raw query neu stale/missing
- `KpiAlertOwnerResolver`
  - resolve owner theo branch access hoac config owner registry

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Flow page report hien tai de dung o muc co bang/filter/export co san.
- Tuy nhien, UX hien dang day responsibility cho nguoi dung:
  - neu khong chon branch, report co the roi vao global scope
  - nguoi dung khong duoc canh bao du lieu dang stale aggregate hay snapshot missing

## Filament UX

- Danh gia: `Trung binh`
- Diem tot:
  - table-based reports de scan nhanh
  - co filter date / branch / export CSV
  - `OperationalKpiPack` co stat cards va SLA badge
- Diem yeu:
  - branch filter options o nhieu page khong scope theo actor
  - khong co indicator “du lieu dang doc tu aggregate” vs “du lieu live”
  - export CSV khong canh bao branch scope hay data freshness

## Edge cases quan trong

- Snapshot job fail, aggregate table con du lieu cu, report tiep tuc doc stale aggregate
- Manager nham tay chay snapshot toan he thong tu local shell vi command khong scope actor
- Doctor vao report appointment/revenue va export CSV co PII ngoai vai tro
- Chi nhanh inactive/khong accessible van xuat hien trong filter branch
- KPI alert assign cho sai owner khi manager lam viec qua assigned branch thay vi primary branch
- Snapshot benchmark so sanh voi cac branch ma actor khong co quyen xem

## Diem de thao tac sai

- Default branch filter rong => nguoi dung tuong dang xem branch minh, thuc te dang xem global
- KPI stat cards khong hien freshness/source mode
- Command output khong canh bao khi actor dang snapshot nhieu branch ngoai branch mac dinh cua minh

## De xuat cai thien UX

- Default branch filter ve branch accessible cua actor; chi `Admin` moi co tuy chon global
- Hien banner freshness: `Live`, `Aggregate fresh`, `Aggregate stale`
- Export filename nen kem branch scope + date range
- `OperationalKpiPack` nen co filter/section rieng cho alert owner va drift status
- Neu actor khong co quyen global benchmark, an benchmark cross-branch khoi stats card

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| KPI-001 | Critical | Security | Report pages mo qua rong cho role nghiep vu | Resolved | `BaseReportPage` da khoa access matrix cho report pages nhay cam |
| KPI-002 | Critical | Security | Report queries va export khong branch-aware | Resolved | Branch filter/options/query scope da dong nhat theo actor |
| KPI-003 | Critical | Security | Snapshot/SLA commands cho phep manager mutate toan bo branch | Resolved | Automation commands da scope theo branch accessible va ghi audit scope |
| KPI-004 | High | Domain Logic | KPI alert owner assignment sai trong multi-branch | Resolved | `KpiAlertOwnerResolver` da bo fallback sai branch |
| KPI-005 | High | Data Integrity | Aggregate reports thieu freshness gate va fallback raw | Resolved | Readiness service da gate aggregate theo range/freshness |
| KPI-006 | High | Performance | Snapshot service scale kem theo so chi nhanh va payment | Resolved | Distinct patient count va doctor benchmark da dua ve aggregate query |
| KPI-007 | Medium | Maintainability | Thieu contract auth/scope chung cho report module | Resolved | `BaseReportPage` da thanh contract auth/scope dung chung |
| KPI-008 | Medium | Maintainability | Test coverage thieu auth va branch isolation cho reports | Resolved | Da co suite regression cho auth/scope/freshness/automation |

# Dependencies

- FIN: doanh thu, receipts, receivable metrics
- INV: hot aggregate/material/factory report datasource
- CARE: care queue aggregate, risk follow-up tickets
- OPS: scheduler hardening, observability health, snapshot SLA

# Open Questions

- Chua co blocker mo cho baseline hien tai.
- Neu sau nay can mo report cho `CSKH` hoac role van hanh khac, can tai lieu hoa role matrix truoc khi mo lai page access.

# Recommended Next Steps

- Chay smoke test production cho `OperationalKpiPack`, `RevenueStatistical`, `CustomsCareStatistical`
- Theo doi runtime va error budget cua snapshot commands trong 1-2 chu ky scheduler sau deploy
- Khi can mo them report roles, cap nhat role matrix + regression suite truoc khi thay doi baseline

# Current Status

- Clean Baseline Reached
