# Metadata

- Module code: `KPI`
- Module name: `Reports / KPI`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-KPI-`
- Source review: `docs/reviews/modules/KPI-reports-kpi.md`
- Source issues: `docs/reviews/issues/KPI-issues.md`
- Dependencies: `FIN, INV, CARE, OPS`
- Last updated: `2026-03-07`

# Objective

- Khoa report module theo branch isolation va role matrix production-safe
- Chot authorization/mutation boundary cho snapshot + SLA commands
- Chuan hoa read-model freshness va alert ownership truoc khi toi uu performance

# Foundation fixes

## [TASK-KPI-001] [Tao report authorization va branch-scope contract chung]
- Based on issue(s): `KPI-001`, `KPI-002`, `KPI-007`
- Priority: Foundation
- Objective: Khoa toàn bộ report pages vao mot contract auth/scope thong nhat
- Scope: `BaseReportPage`, cac Filament report pages co expose PII/KPI, branch filter options, export scope
- Why now: Day la blocker security lon nhat; neu chua khoa access thi moi report moi deu tiep tuc leak scope
- Suggested implementation:
  - Them helper chung cho `canAccess`, `accessibleBranchIds`, `branch filter options`, `scope raw query`, `scope aggregate query`
  - Khoa baseline page access cho `Admin` / `Manager`, page nao can CSKH thi opt-in ro rang
  - Default non-admin ve accessible branches, khong cho roi vao global scope `0/null`
- Affected files or layers: `app/Filament/Pages/Reports/*`, `app/Support/BranchAccess.php`, co the can seeder/policy contract cho page permissions
- Tests required: access matrix theo role, branch isolation page query, export scope tests
- Estimated effort: L
- Dependencies: `GOV`
- Exit criteria: Doctor khong vao duoc report page nhay cam; non-admin chi xem duoc branch accessible; export CSV cung obey scope

# Critical fixes

## [TASK-KPI-002] [Khoa snapshot va SLA commands theo actor va scope]
- Based on issue(s): `KPI-003`
- Priority: Critical
- Objective: Chan mutation report snapshots toan he thong boi actor nghiep vu chung
- Scope: `reports:snapshot-operational-kpis`, `reports:check-snapshot-sla`, co the ca `reports:compare-snapshots` neu can auth read scope ro hon
- Why now: Command dang co the tao snapshot/alert cross-branch sai boundary
- Suggested implementation:
  - Tach permission rieng cho KPI automation hoac chi allow `Admin` / `AutomationService`
  - Neu cho manager run thi ep branch scope = accessible branches cua actor
  - Audit metadata phai ghi ro actor scope
- Affected files or layers: console commands, role baseline, scheduler hardening tests
- Tests required: command auth matrix, manager branch-scoped execution, forbidden global run
- Estimated effort: M
- Dependencies: `TASK-KPI-001`, `OPS`
- Exit criteria: manager/cskh khong the mutate snapshot toan he thong trai scope

# High priority fixes

## [TASK-KPI-003] [Them freshness policy cho aggregate reports va fallback raw]
- Based on issue(s): `KPI-005`
- Priority: High
- Objective: Dam bao report khong doc aggregate stale khi snapshot job fail
- Scope: `RevenueStatistical`, `CustomsCareStatistical`, hot aggregate service/read model freshness contract
- Why now: Sau khi auth/scope khoa xong, du lieu stale la nguon sai lech nghiep vu lon nhat
- Suggested implementation:
  - Them freshness checker theo `snapshot_date`, `generated_at`, branch scope
  - Fallback raw query khi aggregate missing/stale
  - Hien indicator freshness trong UI neu can
- Affected files or layers: report pages, aggregate service, runtime settings neu can freshness threshold
- Tests required: stale aggregate fallback, fresh aggregate preferred, scope-preserving fallback
- Estimated effort: M
- Dependencies: `TASK-KPI-001`, `CARE`, `FIN`
- Exit criteria: report tra du lieu dung khi aggregate co, va van dung khi aggregate stale/missing

## [TASK-KPI-004] [Chuan hoa owner resolver cho KPI alerts]
- Based on issue(s): `KPI-004`
- Priority: High
- Objective: Dam bao moi KPI alert duoc gan owner dung branch và dung vai tro
- Scope: `OperationalKpiAlertService`, owner resolver service, co the runtime settings cho owner fallback
- Why now: Alert khong co owner dung thi triage va SLA follow-up mat y nghia
- Suggested implementation:
  - Tao `KpiAlertOwnerResolver` dua tren branch access / manager assignment / explicit config
  - Loai fallback “first manager globally” khi co scope branch
- Affected files or layers: service layer, alert model workflow, notifications neu co
- Tests required: owner resolution theo branch, assignment-aware fallback, no-owner safe behavior
- Estimated effort: M
- Dependencies: `GOV`, `CARE`
- Exit criteria: alert tao ra co owner dung hoac explicit no-owner state duoc ghi ro

## [TASK-KPI-005] [Toi uu snapshot hot path va distinct counting]
- Based on issue(s): `KPI-006`
- Priority: High
- Objective: Giam chi phi snapshot theo so chi nhanh va volume payment/appointment
- Scope: `OperationalKpiService`, `SnapshotOperationalKpis`, query distinct patient/doctor benchmark
- Why now: Sau khi correctness/security khoa xong, efficiency la risk chinh de giu SLA
- Suggested implementation:
  - Day distinct/group/count ve DB, giam hydrate collection lon
  - Can nhac pre-aggregate them cho doctor benchmark hoac queue per branch
- Affected files or layers: KPI service, commands, maybe aggregate tables
- Tests required: regression tests cho query behavior, snapshot branch batching
- Estimated effort: L
- Dependencies: `FIN`, `APPT`, `OPS`
- Exit criteria: snapshot service khong con can full collection hydrate de tinh chi so chinh

# Medium priority fixes

## [TASK-KPI-006] [Dong goi regression suite cho auth, scope va automation]
- Based on issue(s): `KPI-008`, `KPI-007`
- Priority: Medium
- Objective: Khoa regression cho report auth/scope va command authorization
- Scope: feature tests cho report pages, exports, commands, stale aggregate fallback
- Why now: KPI module co nguy co drift cao neu khong co regression suite sau khi refactor base page
- Suggested implementation:
  - Them test matrix role/page
  - Them test branch isolation cho page query/export
  - Them test stale aggregate fallback va command scope
- Affected files or layers: `tests/Feature`
- Tests required: Chinh task nay la test backlog
- Estimated effort: M
- Dependencies: `TASK-KPI-001`, `TASK-KPI-002`, `TASK-KPI-003`
- Exit criteria: KPI module co test gate cho security + data freshness + automation scope

# Low priority fixes

- Chua de xuat task low priority truoc khi dong duoc baseline security/data correctness

# Testing & regression protection

- Feature test: `Doctor` / `CSKH` / `Manager` access matrix cho tung report page
- Feature test: non-admin chi thay branch filter options accessible cua minh
- Feature test: non-admin export CSV khong bao gom du lieu branch ngoai scope
- Feature test: manager khong the run global snapshot/SLA command neu khong duoc phep
- Feature test: aggregate stale => fallback raw query
- Feature test: KPI alert owner resolution theo branch assignment

# Re-audit checklist

- Report pages da co auth contract chung chua
- Non-admin con duong nao de roi vao global scope khong
- Snapshot/SLA commands con mutate cross-branch trai scope khong
- Aggregate report co freshness gate va fallback raw chua
- KPI alert owner co dung branch va vai tro khong
- Regression suite da cover auth, branch isolation, command scope, stale aggregate chua

# Completion note

- KPI baseline da duoc dong sau khi khoa page auth + branch scope, command automation scope, aggregate freshness policy, alert owner resolver va hot-path optimization.
- Follow-up con lai thuoc rollout/monitoring: smoke test export/report tren production dataset va theo doi runtime snapshot commands sau deploy.

# Execution order

1. `TASK-KPI-001`
2. `TASK-KPI-002`
3. `TASK-KPI-003`
4. `TASK-KPI-004`
5. `TASK-KPI-005`
6. `TASK-KPI-006`

# What can be done in parallel

- `TASK-KPI-004` co the lam song song cuoi `TASK-KPI-003` neu branch auth contract da ro
- `TASK-KPI-006` co the duoc viet dan song song voi moi task sau khi API/page contract on dinh

# What must be done first

- `TASK-KPI-001` bat buoc phai lam truoc, vi no quyet dinh auth/scope contract cho toan module
- `TASK-KPI-002` phai xong truoc khi tin vao snapshot/alert data trong moi task khac

# Suggested milestone breakdown

- Milestone 1: Khoa access + branch scope cho report surfaces
- Milestone 2: Khoa command mutation boundary cho snapshot/SLA
- Milestone 3: Chuan hoa freshness/read model + alert ownership
- Milestone 4: Toi uu hot path + regression sweep + re-audit
