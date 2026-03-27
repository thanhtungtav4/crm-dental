# Metadata

- Module code: `KPI`
- Module name: `Reports / KPI`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Issue ID prefix: `KPI-`
- Task ID prefix: `TASK-KPI-`
- Review file: `docs/reviews/modules/KPI-reports-kpi.md`
- Plan file: `docs/reviews/plans/KPI-plan.md`
- Dependencies: `FIN, INV, CARE, OPS`
- Last updated: `2026-03-07`

# Issue Backlog

## [KPI-001] [Report pages mo qua rong cho role nghiep vu]
- Severity: Critical
- Category: Security
- Module: KPI
- Description: Nhieu Filament report pages khong co `canAccess()` rieng. Runtime check xac nhan `Doctor` hien truy cap duoc `AppointmentStatistical`, `RevenueStatistical`, `OperationalKpiPack`, `RiskScoringDashboard`.
- Why it matters: Bao cao nay expose PII, metric van hanh va export CSV vuot khoi vai tro nghiep vu toi thieu.
- Evidence: [BaseReportPage.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/BaseReportPage.php#L17), [AppointmentStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/AppointmentStatistical.php#L27), [RevenueStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/RevenueStatistical.php#L33), [OperationalKpiPack.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/OperationalKpiPack.php#L29)
- Suggested fix: Them auth contract chung cho report pages va page-specific permission matrix; khoa baseline cho `Admin`/`Manager`, chi opt-in role khac neu co business rule ro rang.
- Affected areas: Filament pages, page navigation, export flow
- Tests needed: Feature tests cho `canAccess()` va HTTP access matrix theo role
- Dependencies: GOV
- Suggested order: 1
- Current status: Resolved
- Linked task IDs: `TASK-KPI-001`

## [KPI-002] [Report queries va export khong branch-aware theo actor]
- Severity: Critical
- Category: Security
- Module: KPI
- Description: Nhiều query report khong scope theo branch accessible. `AppointmentStatistical` query all appointments, `OperationalKpiPack` query all snapshots, `RevenueStatistical` default `branch_scope_id = 0` neu khong chon filter.
- Why it matters: Non-admin co the xem du lieu lien chi nhanh/toan he thong, vi pham branch isolation.
- Evidence: [AppointmentStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/AppointmentStatistical.php#L27), [AppointmentStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/AppointmentStatistical.php#L98), [RevenueStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/RevenueStatistical.php#L35), [RevenueStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/RevenueStatistical.php#L162), [OperationalKpiPack.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/OperationalKpiPack.php#L31), [OperationalKpiPack.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/OperationalKpiPack.php#L212)
- Suggested fix: Dua branch scope vao base report layer; filter options chi show branch accessible; non-admin khong duoc roi vao global scope `0/null`.
- Affected areas: report queries, stat cards, CSV export, branch filters
- Tests needed: Feature tests cho branch isolation va export scope
- Dependencies: GOV, FIN, CARE
- Suggested order: 2
- Current status: Resolved
- Linked task IDs: `TASK-KPI-001`

## [KPI-003] [Snapshot va SLA commands cho phep manager mutate toan he thong]
- Severity: Critical
- Category: Security
- Module: KPI
- Description: `reports:snapshot-operational-kpis`, `reports:check-snapshot-sla` va `reports:snapshot-hot-aggregates` chi guard bang `Action:AutomationRun`. Baseline hien tai cap permission nay cho `Manager` va `CSKH`; manager co the chay mutation/report automation cho tat ca branch khi khong truyen `--branch_id`.
- Why it matters: Command nay tao/ghi de report snapshots, alert va audit log toan he thong. Day la mutation cross-branch khong nen mo cho actor nghiep vu chung.
- Evidence: [SnapshotOperationalKpis.php](/Users/macbook/Herd/crm/app/Console/Commands/SnapshotOperationalKpis.php#L36), [CheckSnapshotSla.php](/Users/macbook/Herd/crm/app/Console/Commands/CheckSnapshotSla.php#L22), [RolesAndPermissionsSeeder.php](/Users/macbook/Herd/crm/database/seeders/RolesAndPermissionsSeeder.php#L116), runtime dry-run voi manager tra ve `success=13`
- Suggested fix: Tach action permission rieng cho report automation, hoac scope command theo branch accessible khi actor khong phai automation service/admin.
- Affected areas: console commands, scheduler security, audit trail
- Tests needed: Feature tests cho command authorization matrix va branch-scoped execution
- Dependencies: GOV, OPS
- Suggested order: 3
- Current status: Resolved
- Linked task IDs: `TASK-KPI-002`

## [KPI-004] [KPI alert owner assignment sai trong multi-branch]
- Severity: High
- Category: Domain Logic
- Module: KPI
- Description: `OperationalKpiAlertService::resolveOwnerUserId()` lookup `Manager` bang `users.branch_id` roi fallback manager/global admin dau tien. Logic nay bo qua assigned branch va khong co explicit owner registry.
- Why it matters: Alert de nham owner, lam triage sai branch va kho audit accountability.
- Evidence: [OperationalKpiAlertService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiAlertService.php#L159)
- Suggested fix: Tao `KpiAlertOwnerResolver` dua tren branch access/assignment hoac runtime configuration ro rang.
- Affected areas: alert ownership, operations follow-up, notifications
- Tests needed: Feature tests cho owner resolution theo branch assignment
- Dependencies: GOV, CARE
- Suggested order: 4
- Current status: Resolved
- Linked task IDs: `TASK-KPI-004`

## [KPI-005] [Aggregate reports thieu freshness gate va fallback raw]
- Severity: High
- Category: Data Integrity
- Module: KPI
- Description: `RevenueStatistical` va `CustomsCareStatistical` chi can aggregate table `exists()` la se dung read model aggregate, khong kiem freshness theo date/scope. Neu job aggregate fail, report co the blank/stale du raw data van co.
- Why it matters: Bao cao sai hoac cu se dan den quyet dinh van hanh sai.
- Evidence: [RevenueStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/RevenueStatistical.php#L151), [CustomsCareStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/CustomsCareStatistical.php#L212)
- Suggested fix: Them freshness policy theo date/scope va fallback raw query neu aggregate missing/stale.
- Affected areas: revenue report, care report, hot aggregate pipeline
- Tests needed: Feature tests cho stale aggregate fallback
- Dependencies: CARE, FIN, OPS
- Suggested order: 5
- Current status: Resolved
- Linked task IDs: `TASK-KPI-003`

## [KPI-006] [Snapshot service scale kem khi branch va payment tang]
- Severity: High
- Category: Performance
- Module: KPI
- Description: `OperationalKpiService` chay nhieu count/sum query rieng cho moi branch va hydrate collections lon de distinct patient/doctor benchmark. Command snapshot loop qua tat ca branch + global trong mot run.
- Why it matters: Khi he thong co nhieu chi nhanh va volume payment/appointment lon, snapshot nightly de tre, day chain SLA va alert false positive.
- Evidence: [OperationalKpiService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiService.php#L30), [OperationalKpiService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiService.php#L90), [OperationalKpiService.php](/Users/macbook/Herd/crm/app/Services/OperationalKpiService.php#L193), [SnapshotOperationalKpis.php](/Users/macbook/Herd/crm/app/Console/Commands/SnapshotOperationalKpis.php#L68)
- Suggested fix: Tối ưu distinct/count tai DB, pre-aggregate them cho KPI, va can nhac queue/batch theo branch.
- Affected areas: snapshot command, KPI service, scheduler SLA
- Tests needed: Regression tests cho query shape / branch batch behavior
- Dependencies: OPS, FIN, APPT
- Suggested order: 6
- Current status: Resolved
- Linked task IDs: `TASK-KPI-005`

## [KPI-007] [Module report thieu contract chung cho auth va scope]
- Severity: Medium
- Category: Maintainability
- Module: KPI
- Description: Chi `CustomsCareStatistical` co implementation branch-aware tuong doi day du; cac report page khac drift manh trong auth, filter options, branch scope va export behavior.
- Why it matters: Moi page moi lai tu che, regression se quay lai lien tuc khi them report moi.
- Evidence: [BaseReportPage.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/BaseReportPage.php#L17), [CustomsCareStatistical.php](/Users/macbook/Herd/crm/app/Filament/Pages/Reports/CustomsCareStatistical.php#L29)
- Suggested fix: Nâng `BaseReportPage` thanh contract chung cho authorization + branch scope + export scope.
- Affected areas: report pages, future report development
- Tests needed: Feature tests cho shared contract base page
- Dependencies: KPI-001, KPI-002
- Suggested order: 7
- Current status: Resolved
- Linked task IDs: `TASK-KPI-001`, `TASK-KPI-006`

## [KPI-008] [Coverage thieu auth va branch isolation regression]
- Severity: Medium
- Category: Maintainability
- Module: KPI
- Description: Test hien cover snapshot correctness, alert behavior va aggregate switch, nhung chua cover page access matrix, branch filter isolation, hay command authorization scope.
- Why it matters: KPI module co nguy co regression security cao vi page/report drift dang lon.
- Evidence: [KpiBenchmarkAndAlertTest.php](/Users/macbook/Herd/crm/tests/Feature/KpiBenchmarkAndAlertTest.php#L15), [HotReportAggregateReportQueryTest.php](/Users/macbook/Herd/crm/tests/Feature/HotReportAggregateReportQueryTest.php#L9), [P1OpsPlatformAndRbacTest.php](/Users/macbook/Herd/crm/tests/Feature/P1OpsPlatformAndRbacTest.php#L27)
- Suggested fix: Them feature tests cho report page auth, branch isolation, export scope, command auth matrix, stale aggregate fallback.
- Affected areas: `tests/Feature`
- Tests needed: Chinh issue nay la regression backlog
- Dependencies: KPI-001, KPI-002, KPI-003, KPI-005
- Suggested order: 8
- Current status: Resolved
- Linked task IDs: `TASK-KPI-006`

# Summary

- Open critical count: 0
- Open high count: 0
- Open medium count: 0
- Open low count: 0
- Next recommended action: Rollout KPI batch, smoke test report/export tren production dataset, va theo doi runtime snapshot commands sau deploy.
