# Metadata

- Module code: `PAT`
- Module name: `Customers / Patients / MPI`
- Current status: `In Fix`
- Current verdict: `D`
- Task ID prefix: `TASK-PAT-`
- Source review: `docs/reviews/modules/PAT-customers-patients.md`
- Source issues: `docs/issues/PAT-issues.md`
- Dependencies: `GOV, APPT, CLIN, FIN, CARE, ZNS`
- Last updated: `2026-03-06`

# Objective

- Dua module `PAT` tu `D` len it nhat `B` baseline bang cach dong 4 khe hong chinh: customer PII plaintext, customer->patient conversion race-condition, branch-scoped staff assignment, va identity strategy customer/patient.
- Tao patient identity boundary on dinh de `APPT`, `CLIN`, `FIN`, `CARE`, `ZNS` khong bi troi ownership sau nay.

# Foundation fixes

## [TASK-PAT-001] Hardening customer PII storage, hash search va identity integrity
- Based on issue(s): `PAT-001`, `PAT-004`
- Priority: Foundation
- Objective:
  - Dua `customers` len cung muc privacy baseline voi `patients`.
- Scope:
  - `Customer` model
  - customer schema/migration
  - shared form schema
  - web lead ingestion path
- Why now:
  - Day la blocker privacy lon nhat cua PAT va la nen cho moi fix dedupe sau do.
- Suggested implementation:
  - Them encrypted cast cho `phone`, `email`, `address` neu phu hop voi conventions hien tai.
  - Them search hash/normalized hash columns co index cho lookup.
  - Refactor form validation/search sang hash strategy.
  - Backfill du lieu cu an toan.
- Affected files or layers:
  - `app/Models/Customer.php`
  - `app/Filament/Schemas/SharedSchemas.php`
  - `database/migrations/*customers*`
  - `app/Services/WebLeadIngestionService.php`
- Tests required:
  - Feature test customer PII encrypted + searchable via hash
  - Migration/backfill test
- Estimated effort: `L`
- Dependencies:
  - None
- Exit criteria:
  - Customer PII khong con phu thuoc vao raw searchable columns
  - Existing lookup/create/edit flow van chay qua hash strategy

# Critical fixes

## [TASK-PAT-002] Hardening customer->patient conversion voi transaction, locking va idempotency
- Based on issue(s): `PAT-002`
- Priority: Critical
- Objective:
  - Dam bao convert lead -> patient on dinh duoi concurrent traffic va auto-convert collision.
- Scope:
  - `PatientConversionService`
  - appointment auto-convert flow
  - identity candidate lookup
- Why now:
  - Day la hot path nghiep vu cho le tan va bac si; duplicate patient se lan sang MPI, FIN, CLIN.
- Suggested implementation:
  - Lock `customers` row va candidate patient rows trong transaction.
  - Canonicalize conversion theo `customer_id` truoc, sau do theo identity hash/branch.
  - Doi status/link appointment sau khi da xac dinh canonical patient.
  - Xu ly duplicate/unique collision thanh ket qua nghiep vu ro rang, khong de exception database tro ra UI.
- Affected files or layers:
  - `app/Services/PatientConversionService.php`
  - `app/Observers/AppointmentObserver.php`
  - migration/index neu can
- Tests required:
  - Concurrency tests same-customer and same-phone-same-branch
  - Feature test auto-convert va manual convert collision
- Estimated effort: `L`
- Dependencies:
  - `TASK-PAT-001`
- Exit criteria:
  - Conversion idempotent duoi concurrent requests
  - Khong tao duplicate patient do race window

## [TASK-PAT-003] Scope staff/doctor selectors theo branch va harden server-side validation
- Based on issue(s): `PAT-003`
- Priority: Critical
- Objective:
  - Chan cross-branch assignment sai scope tren customer/patient flows.
- Scope:
  - `PatientForm`
  - `CustomerForm`
  - related save guards
- Why now:
  - Sau GOV baseline, day la lo hong governance tiep theo trong PAT.
- Suggested implementation:
  - Loc `assigned_to`, `owner_staff_id`, `primary_doctor_id` theo branch accessible va doctor assignment.
  - Re-validate on save de chan forged payload.
  - Them helper text UI neu branch thay doi khien option dang chon khong hop le.
- Affected files or layers:
  - `app/Filament/Resources/Patients/Schemas/PatientForm.php`
  - `app/Filament/Resources/Customers/Schemas/CustomerForm.php`
  - service/support layer cho option provider neu can
- Tests required:
  - Feature test branch-scoped options
  - Feature test forged request bi reject
- Estimated effort: `M`
- Dependencies:
  - `TASK-PAT-001`
- Exit criteria:
  - Khong gan duoc doctor/assignee ngoai branch scope
  - UI va server-side behavior thong nhat

# High priority fixes

## [TASK-PAT-004] Chuan hoa customer/patient identity strategy va loai bo full-scan dedupe fallback
- Based on issue(s): `PAT-004`, `PAT-005`
- Priority: High
- Objective:
  - Dinh nghia 1 source of truth cho identity lookup va bo scan branch-level tren hot path.
- Scope:
  - identity normalization/hash service
  - conversion lookup logic
  - MPI sync input strategy
- Why now:
  - Neu khong dong nhat identity strategy, moi hardening o `TASK-PAT-001` va `TASK-PAT-002` se de drift tro lai.
- Suggested implementation:
  - Tao service chung normalize/hash `phone`, `email`, `cccd` cho customer/patient.
  - Chuyen conversion lookup sang hash/normalized index.
  - Dua fuzzy duplicate detection ra MPI queue thay vi scan trong request path.
- Affected files or layers:
  - `app/Services/PatientConversionService.php`
  - `app/Services/MasterPatientIndexService.php`
  - `app/Models/Customer.php`
  - `app/Models/Patient.php`
- Tests required:
  - Feature test same identity duoc resolve qua hash strategy
  - Performance-oriented regression check cho hot path neu co harness
- Estimated effort: `L`
- Dependencies:
  - `TASK-PAT-001`
  - `TASK-PAT-002`
- Exit criteria:
  - Khong con full branch scan trong conversion hot path
  - Customer va patient dung chung identity normalization contract

## [TASK-PAT-005] Dua patient onboarding side effects ve service boundary ro rang
- Based on issue(s): `PAT-006`
- Priority: High
- Objective:
  - Loai side effect tao customer khoi model event va dat lai transaction boundary cho onboarding.
- Scope:
  - `Patient` model
  - create patient flow
  - onboarding service moi hoac service hien co
- Why now:
  - Can boundary ro rang truoc khi mo rong PAT sang CLIN/FIN.
- Suggested implementation:
  - Tao `PatientOnboardingService` xu ly create patient + optional create/link customer.
  - Giam `Patient::creating()` ve muc validate/invariant thuan.
  - Dam bao rollback khong de lai aggregate mo coi.
- Affected files or layers:
  - `app/Models/Patient.php`
  - `app/Filament/Resources/Patients/Pages/CreatePatient.php`
  - service moi trong `app/Services`
- Tests required:
  - Feature test patient create with/without customer link
  - Feature test rollback/orphan prevention
- Estimated effort: `M`
- Dependencies:
  - `TASK-PAT-002`
- Exit criteria:
  - Onboarding side effects duoc quan ly boi service transaction-safe
  - Model event khong con tao aggregate khac

# Medium priority fixes

## [TASK-PAT-006] Bo sung MPI review workflow phu hop cho operator
- Based on issue(s): `PAT-007`
- Priority: Medium
- Objective:
  - Bien duplicate review thanh workflow co the van hanh duoc boi user duoc cap quyen.
- Scope:
  - duplicate case read model/UI
  - merge/ignore/rollback actions
  - audit trail
- Why now:
  - Sau khi identity strategy on dinh, can cong cu review duplicate thuc te de queue khong ton dong.
- Suggested implementation:
  - Tao Filament page/resource cho open duplicate cases.
  - Map actions sang service `MasterPatientMergeService` va `MasterPatientDuplicate` methods.
  - Hien ro patient/branch/confidence/context cho reviewer.
- Affected files or layers:
  - Filament resource/page moi hoac custom page trong PAT scope
  - MPI services/models
- Tests required:
  - Feature test authorized review actions
  - Feature test UI route auth/scope
- Estimated effort: `M`
- Dependencies:
  - `TASK-PAT-004`
  - `TASK-PAT-005`
- Exit criteria:
  - Duplicate cases co workflow review khong phu thuoc vao artisan command

# Low priority fixes

- Chua co low-priority task rieng. Toan bo backlog PAT hien tai deu anh huong truc tiep den patient identity boundary va can xu ly truoc khi module dat baseline.

# Testing & regression protection

## [TASK-PAT-007] Khoa regression cho PII, conversion race, selector scope va onboarding
- Based on issue(s): `PAT-008`, `PAT-001`, `PAT-002`, `PAT-003`, `PAT-006`, `PAT-007`
- Priority: Medium
- Objective:
  - Dam bao nhung fix PAT khong bi quay lai sau khi refactor schema/service.
- Scope:
  - Feature tests, va browser tests neu can cho UI selectors / duplicate review flow
- Why now:
  - PAT la module goc cho APPT/CLIN/FIN; regression tai day co hieu ung day chuyen rat lon.
- Suggested implementation:
  - Them tests cho customer PII encryption/hash lookup.
  - Them concurrency tests cho conversion.
  - Them tests cho branch-scoped doctor/assignee selectors va forged payload rejection.
  - Them tests cho onboarding rollback/orphan prevention.
  - Them tests cho MPI review UI neu duoc them.
- Affected files or layers:
  - `tests/Feature/*`
  - `tests/Browser/*` neu can
- Tests required:
  - Chinh task nay la backlog test regression
- Estimated effort: `M`
- Dependencies:
  - `TASK-PAT-001`
  - `TASK-PAT-002`
  - `TASK-PAT-003`
  - `TASK-PAT-005`
  - `TASK-PAT-006`
- Exit criteria:
  - Regression suite PAT moi pass o local va khi rerun sau refactor

# Re-audit checklist

- Xac nhan customer PII khong con luu/search theo raw plaintext strategy.
- Xac nhan conversion customer->patient idempotent duoi concurrent traffic.
- Xac nhan doctor/owner/assignee options va save path deu branch-scoped.
- Xac nhan khong con full branch scan trong conversion hot path.
- Xac nhan patient onboarding khong de side effect mo coi ngoai service boundary.
- Xac nhan duplicate review workflow co the van hanh boi role duoc cap phep.
- Xac nhan regression tests PAT moi deu pass.
- Danh gia lai verdict va clean baseline status.

# Execution order

1. `TASK-PAT-001`
2. `TASK-PAT-002`
3. `TASK-PAT-003`
4. `TASK-PAT-004`
5. `TASK-PAT-005`
6. `TASK-PAT-006`
7. `TASK-PAT-007`

# What can be done in parallel

- `TASK-PAT-003` co the chay song song mot phan voi `TASK-PAT-002` neu da chot contract branch-scoped option provider.
- `TASK-PAT-007` co the bat dau viet test skeleton song song voi `TASK-PAT-001` va `TASK-PAT-002`.

# What must be done first

- `TASK-PAT-001` phai di truoc de chot strategy identity/privacy cho customer.
- `TASK-PAT-002` phai xong truoc khi mo rong review/fix APPT auto-convert behavior.

# Suggested milestone breakdown

- Milestone 1:
  - `TASK-PAT-001`
  - `TASK-PAT-002`
- Milestone 2:
  - `TASK-PAT-003`
  - `TASK-PAT-004`
  - `TASK-PAT-005`
- Milestone 3:
  - `TASK-PAT-006`
  - `TASK-PAT-007`
  - Re-audit PAT
