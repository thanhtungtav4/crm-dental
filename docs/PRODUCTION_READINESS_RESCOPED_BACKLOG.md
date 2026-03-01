# Production Readiness Rescoped Backlog (From External AI Review)

Cap nhat: 2026-03-01  
Nguon input:
- `/Users/macbook/Desktop/crm_dental_analysis_report.md.resolved`
- `/Users/macbook/Desktop/implementation_plan.md.resolved`
- `docs/PM_DENTAL_FLOW_BACKLOG.md` (PM-01..PM-46 da Done)

## 1) Muc tieu

- Chuyen review ben ngoai thanh backlog kha thi, tranh duplicate voi phan da lam.
- Tap trung vao khoang trong thuc te con mo de dat production-ready theo mo hinh da chi nhanh.
- Giu pham vi ngat wave de co the release theo gate thay vi om toan bo 36-40 tuan.

## 2) Nguyen tac danh scope

- `Adopt`: Lam ngay, tac dong truc tiep toi quality/go-live.
- `Re-scope`: Lam theo MVP hieu qua, khong full-scope ngay.
- `Defer`: Hoan sau go-live wave hien tai.
- Khong mo lai cac ticket da done trong PM-01..PM-46.

## 3) Reality check voi codebase hien tai

Da co san (khong lap lai):
- Multi-branch hardening, action-level RBAC, audit log mo rong.
- Finance branch attribution + reconciliation command + gate.
- Consent clinical gate, insurance flow, installment/dunning, care automation.
- KPI lineage/versioning, scheduler hardening, release gates command.

Khoang trong con mo (co y nghia production):
- Clinical aggregate tach ro hon (`exam_sessions`, progress theo ngay).
- Calendar dieu phoi van hanh theo tuan/ngay (UI thao tac nhanh).
- Labo/xuong flow (nha khoa co nhu cau phuc hinh/lab tracking).
- API layer mo rong neu co mobile/SPA.
- Performance hardening co load baseline va pre-aggregation ro hon.
- Security/compliance operational (MFA, DR drill, PHI access-log completeness).

## 4) Backlog chot de trien khai

### Wave A - Core Clinical + Reliability (P0, 4-6 tuan)

#### PM-47 (P0) - Exam Session Separation
- **Decision**: Adopt
- **Estimate**: 13 SP
- **Scope**:
  - Tao `exam_sessions` va migrate du lieu tu `clinical_notes`.
  - Chuan hoa relation voi Patient/TreatmentPlan/PlanItem/Prescription/ClinicalOrder.
  - Enforce lifecycle `draft -> planned -> in_progress -> completed -> locked`.
- **Dependencies**: none
- **Acceptance**:
  1. Du lieu clinical cu migrate khong mat.
  2. Luong tao/sua/xoa session khong vo patient workspace.
  3. Session co du lieu thuc hien thi khong xoa duoc.
- **Test gate**:
  - Migration integrity test
  - Exam session lifecycle feature test
  - Regression test `patients/{id}?tab=exam-treatment`
- **Status**: Todo

#### PM-48 (P0) - Treatment Progress Days & Items
- **Decision**: Adopt
- **Estimate**: 8 SP
- **Scope**:
  - Tao `treatment_progress_days`, `treatment_progress_items`.
  - Dong bo trang thai rang/plan item theo tien trinh thuc hien.
  - Tong hop chi phi tien trinh theo ngay + theo session.
- **Dependencies**: PM-47
- **Acceptance**:
  1. Them ngay dieu tri + item hoat dong on dinh.
  2. Tong tien khop voi line items.
  3. Table tien trinh load du lieu dung context benh nhan.
- **Test gate**:
  - Progress day CRUD tests
  - Financial sum assertions
  - UI layout snapshot tests
- **Status**: Todo

#### PM-49 (P0) - Performance Baseline for Operational Hot Paths
- **Decision**: Re-scope (MVP truoc)
- **Estimate**: 8 SP
- **Scope (MVP)**:
  - Pre-aggregation cho 3 report nong nhat (dashboard ops, revenue branch, care queue).
  - Chot SLA p95 cho list/workspace/report.
  - Baseline EXPLAIN + query latency artifact theo ngay.
- **Dependencies**: none
- **Acceptance**:
  1. Report chinh dat SLA chot.
  2. Khong full-scan query hot-path trong strict gate.
  3. Co artifact so sanh truoc/sau.
- **Test gate**:
  - Command tests cho snapshot/pre-aggregation
  - Strict explain gate
  - Perf smoke baseline script
- **Status**: Todo

#### PM-50 (P0) - Security Ops Hardening (MFA + Session + PHI Access Log)
- **Decision**: Re-scope (bat buoc cho role nhay cam truoc)
- **Estimate**: 8 SP
- **Scope (MVP)**:
  - MFA bat buoc cho `Admin`/`Manager`.
  - Session timeout policy + lockout co audit.
  - Log truy cap PHI read cho resource nhay cam (EMR/clinical note/prescription).
- **Dependencies**: none
- **Acceptance**:
  1. Admin/Manager khong MFA thi khong vao panel.
  2. Session timeout/lockout co log day du.
  3. PHI read events truy vet duoc theo actor + entity.
- **Test gate**:
  - MFA flow tests
  - Session expiry tests
  - PHI audit trail tests
- **Status**: Todo

#### PM-51 (P0) - Go-live Reliability Pack (Backup + DR + Monitoring)
- **Decision**: Adopt
- **Estimate**: 5 SP
- **Scope**:
  - Backup automation (DB + file storage) + restore drill.
  - Monitoring/alert runbook (error rate, queue lag, scheduler miss).
  - Go-live checklist command profile production cap nhat theo gate moi.
- **Dependencies**: none
- **Acceptance**:
  1. Restore drill pass tren staging.
  2. Co alert policy ro rang cho su co production.
  3. Production profile fail fast neu thieu gate.
- **Test gate**:
  - Command tests cho release profile
  - Health check command tests
- **Status**: Todo

### Wave B - Ops UX + Growth Enablers (P1, 4-5 tuan)

#### PM-52 (P1) - Calendar Operational View (Day/Week MVP)
- **Decision**: Re-scope
- **Estimate**: 8 SP
- **Scope (MVP)**:
  - Day/Week view (khong full month drag-drop ngay wave dau).
  - Nhanh tao/reschedule lich hen + conflict warning.
  - Metrics bar theo status operational.
- **Dependencies**: PM-47
- **Acceptance**:
  1. Le tan thao tac tao doi lich nhanh hon list table.
  2. Conflict branch/doctor hien thi ro.
  3. Khong vo state machine appointment.
- **Status**: Todo

#### PM-53 (P1) - Photo Library Completion (Clinical-first)
- **Decision**: Re-scope
- **Estimate**: 5 SP
- **Scope (MVP)**:
  - Chuan hoa type image clinical (`normal`, `ext`, `int`, `xray`).
  - Upload + paste clipboard on dinh trong phieu kham.
  - Retention policy theo clinic setting.
- **Dependencies**: PM-47
- **Acceptance**:
  1. Upload/paste chay on cho clinical.
  2. Filter theo loai anh dung.
  3. Khong mat lien ket voi note/session.
- **Status**: Todo

#### PM-54 (P1) - Patient Contacts Separation
- **Decision**: Adopt
- **Estimate**: 3 SP
- **Scope**:
  - Tao `patient_contacts` de ho tro nhieu nguoi lien he.
  - Migrate du lieu emergency_contact cu neu co.
- **Dependencies**: none
- **Acceptance**:
  1. Ho tro >1 contact/benh nhan.
  2. Form va relation manager de su dung cho le tan.
- **Status**: Todo

#### PM-55 (P1) - CSKH SLA Dashboard v2
- **Decision**: Adopt
- **Estimate**: 5 SP
- **Scope**:
  - SLA board theo nhan vien/branch/channel.
  - Queue no-show/recall/follow-up co filter priority.
- **Dependencies**: none
- **Acceptance**:
  1. SLA overdue hien canh bao ro.
  2. Co export cho quan ly.
- **Status**: Todo

### Wave C - Expansion Tracks (P1/P2, 4-6 tuan)

#### PM-56 (P1) - Labo Module Foundation
- **Decision**: Adopt
- **Estimate**: 13 SP
- **Scope**:
  - `factory_orders`, `factory_order_items`.
  - Tab Xuong/Vat tu tai patient workspace.
  - State machine `ordered -> in_progress -> delivered`.
- **Dependencies**: PM-47, PM-48
- **Acceptance**:
  1. CRUD dat xuong on dinh.
  2. Link duoc voi patient + session.
- **Status**: Todo

#### PM-57 (P1) - Material Issue Notes Integration
- **Decision**: Adopt
- **Estimate**: 5 SP
- **Scope**:
  - `material_issue_notes`, `material_issue_items`.
  - Dong bo ton kho + transaction log.
- **Dependencies**: PM-56
- **Acceptance**:
  1. Xuat vat tu tru ton kho dung.
  2. Co canh bao ton thap.
- **Status**: Todo

#### PM-58 (P1) - ZNS Campaign Lifecycle MVP
- **Decision**: Re-scope
- **Estimate**: 8 SP
- **Scope (MVP)**:
  - Campaign draft/schedule/run/complete.
  - Retry co idempotency + delivery log.
  - Audience filter co ban (branch, source, last_visit).
- **Dependencies**: none
- **Acceptance**:
  1. Campaign khong gui trung khi retry.
  2. Tracking sent/failed ro rang.
- **Status**: Todo

#### PM-59 (P2) - API v1 Expansion for Mobile/SPA
- **Decision**: Re-scope
- **Estimate**: 13 SP
- **Scope (MVP)**:
  - Uu tien API theo use-case: Auth, Appointment, Patient summary, Invoice summary.
  - Sanctum + rate-limit + API resource envelope.
  - OpenAPI docs cho nhom endpoint MVP.
- **Dependencies**: PM-47
- **Acceptance**:
  1. Co the dung mobile cho luong kham co ban.
  2. Auth/rate-limit/permission pass test.
- **Status**: Todo

#### PM-60 (P2) - Wallet/Deposit Full Ledger
- **Decision**: Defer (sau Wave A/B)
- **Estimate**: 5 SP
- **Status**: Todo

## 5) Defer list (khong dua vao release wave gan nhat)

- Full month calendar + advanced drag-drop scheduling.
- Full CRUD API cho tat ca modules ngay dot dau.
- Patient portal self-booking.
- Multi-tenant SaaS refactor.
- Tong luc 150+ test files target (giu theo risk-based testing thay vi chasing file count).

## 6) Ke hoach trien khai de xuat

1. `Sprint 1-2`: PM-47, PM-48  
2. `Sprint 3`: PM-49, PM-50  
3. `Sprint 4`: PM-51 + stabilization  
4. `Sprint 5-6`: PM-52, PM-53, PM-54, PM-55  
5. `Sprint 7-8`: PM-56, PM-57, PM-58  
6. `Sprint 9`: PM-59 (MVP API), quyet dinh PM-60

## 7) Gate bat buoc truoc mo moi wave

- `php artisan migrate:status` (khong pending)
- `php artisan schema:assert-no-pending-migrations`
- `php artisan ops:run-release-gates --profile=production --dry-run`
- `php artisan test` (full suite)
- `vendor/bin/pint --dirty`
- Neu dong vao query/index: `php artisan reports:explain-ops-hotpaths --strict`
- Neu dong vao finance attribution: `php artisan finance:reconcile-branch-attribution --from=... --to=...`

## 8) KPI de danh gia tien do backlog

- Clinical completion rate (exam->progress->invoice) >= 95%.
- Appointment operational latency p95 < 3s.
- Report SLA p95 < 5s.
- Security incidents critical = 0.
- UAT pass rate theo role >= 95%.

