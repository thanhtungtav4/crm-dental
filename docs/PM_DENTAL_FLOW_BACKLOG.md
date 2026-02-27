# PM Backlog - Dental CRM Flow Hardening

Cap nhat: 2026-02-27
Nguon tong hop: `docs/DENTAL_CRM_SPECIFICATION.md`, `docs/GAP_ANALYSIS.md`, `docs/IMPLEMENTATION_SPRINT_BACKLOG.md`

## 1) Muc tieu backlog

- Dong cac logic gap van hanh trong flow nha khoa.
- Chuan hoa state machine va data model de tranh that thoat doanh thu.
- San sang scale da chi nhanh va tu dong hoa CSKH.

---

## 2) Backlog P0 (Critical)

### TICKET PM-01 (P0)
- **Title**: Chuan hoa state machine `Appointment` giua spec va runtime
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-25`)
- **Scope**:
  - Chot 1 taxonomy trang thai duy nhat.
  - Mapping/convert du lieu trang thai cu.
  - Chuan hoa quick-action va filter/report theo taxonomy moi.
- **Acceptance Criteria (QA)**:
  1. Khong con mismatch giua trang thai UI, API, report.
  2. Chuyen trang thai sai luong bi chan voi error ro rang.

### TICKET PM-02 (P0)
- **Title**: Chuan hoa state machine `Care Ticket` va SLA handling
- **Type**: Story (BE + FE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-25`)
- **Scope**:
  - Chot enum care status dung 1 bo.
  - Map du lieu lich su va cap nhat dashboard CSKH.
- **Acceptance Criteria (QA)**:
  1. Care tabs, export, report dung cung 1 enum.
  2. Khong con row "trang thai ao" khong map duoc.

### TICKET PM-03 (P0)
- **Title**: Visit Episode model + chair-level operational flow
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-25`)
- **Scope**:
  - Tao `visit_episode` (check_in, arrived, in_chair, check_out).
  - Gan doctor, chair, duration ke hoach/thuc te.
- **Acceptance Criteria (QA)**:
  1. Moi lan den kham co 1 episode ro rang.
  2. Bao cao co duoc waiting time, chair time, overrun time.

### TICKET PM-04 (P0)
- **Title**: Xu ly edge flow lich hen (late arrival, emergency, walk-in)
- **Type**: Story (Ops + Product + BE/FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-25`)
- **Scope**:
  - Rule cho tre gio, cap cuu, khach walk-in.
  - Cho phep override co audit va ly do bat buoc.
- **Acceptance Criteria (QA)**:
  1. Ca tre gio va cap cuu khong vo lich toan bo.
  2. Co log day du actor, ly do, timestamp.

### TICKET PM-05 (P0)
- **Title**: Treatment approval lifecycle + multi-visit phase gating
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-25`)
- **Scope**:
  - Nang `patient_agreed` thanh workflow (`draft/proposed/approved/declined`).
  - Gating phase dieu tri truoc/sau.
- **Acceptance Criteria (QA)**:
  1. Plan chua duyet khong duoc day sang phase tiep theo.
  2. Co ly do decline va follow-up queue cho tu van.

### TICKET PM-06 (P0)
- **Title**: Financial hardening (refund/reversal/deposit/prepay-overpay)
- **Type**: Story (BE + FE + Finance)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-26`)
- **Scope**:
  - Chuan hoa ledger thu/hoan.
  - Ho tro so du dat coc, prepay/overpay theo policy.
- **Acceptance Criteria (QA)**:
  1. So lieu tab Thanh toan khop 100% voi report tai chinh.
  2. Phieu da posted chi duoc reversal, khong edit truc tiep.

### TICKET PM-07 (P0)
- **Title**: Installment/payment plan lifecycle + dunning
- **Type**: Story (BE + FE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-26`)
- **Scope**:
  - Lich ky thanh toan, trang thai qua han, nhac no tu dong.
- **Acceptance Criteria (QA)**:
  1. Moi ky tra gop co due state ro rang.
  2. Co nhac no theo aging bucket va log ket qua gui.

### TICKET PM-08 (P0)
- **Title**: Insurance claim workflow
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-26`)
- **Scope**:
  - Pre-auth, submit claim, paid/denied, resubmit.
  - Gan claim vao invoice/receipt lien quan.
- **Acceptance Criteria (QA)**:
  1. Theo doi duoc claim lifecycle end-to-end.
  2. Denial co reason code va queue xu ly lai.

### TICKET PM-09 (P0)
- **Title**: Consent forms as clinical gate
- **Type**: Story (BE + FE + Legal)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-26`)
- **Scope**:
  - Hoan thien module consent + e-sign/audit.
  - Gate bat buoc truoc thu thuat rui ro cao.
- **Acceptance Criteria (QA)**:
  1. Thu thuat high-risk khong duoc bat dau neu thieu consent hop le.
  2. Consent co version, signer, timestamp truy vet duoc.

### TICKET PM-10 (P0)
- **Title**: Overbooking policy freeze + clinic-level config
- **Type**: Task (Product + Ops + BE)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-26`)
- **Scope**:
  - Chot policy overbooking theo chi nhanh.
  - Audit bat buoc khi override.
- **Acceptance Criteria (QA)**:
  1. Policy overbooking duoc enforce o API va UI.
  2. Bao cao co phan tach slot thuong vs slot override.

---

## 3) Backlog P1 (High)

### TICKET PM-11 (P1)
- **Title**: Recall/re-care rules engine theo thu thuat
- **Type**: Story (BE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-26`)

### TICKET PM-12 (P1)
- **Title**: No-show recovery automation playbook
- **Type**: Story (BE + CSKH)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-26`)

### TICKET PM-13 (P1)
- **Title**: Follow-up pipeline cho plan chua chot
- **Type**: Story (BE + FE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-26`)

### TICKET PM-14 (P1)
- **Title**: Payment reminder automation theo aging
- **Type**: Story (BE)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-26`)

### TICKET PM-15 (P1)
- **Title**: KPI pack van hanh nha khoa (booking->visit, no-show, acceptance, chair, recall, LTV)
- **Type**: Story (BE + Data + FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)

### TICKET PM-16 (P1)
- **Title**: Report data lineage + snapshot SLA hardening
- **Type**: Story (BE + Data)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-26`)

### TICKET PM-17 (P1)
- **Title**: Multi-branch master data sync + conflict resolution
- **Type**: Story (BE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)

### TICKET PM-18 (P1)
- **Title**: MPI + dedupe policy lien chi nhanh
- **Type**: Story (BE + Ops)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)

### TICKET PM-19 (P1)
- **Title**: RBAC action-level freeze va enforce backend
- **Type**: Story (BE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)

### TICKET PM-20 (P1)
- **Title**: Audit log mo rong cho clinical/finance/care critical events
- **Type**: Story (BE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)

---

## 4) Backlog bo sung (Scale hardening sau PM-20)

### TICKET PM-23 (P1)
- **Title**: Mo rong master data sync da chi nhanh (service catalog, price book, consent template, recall rule)
- **Type**: Story (BE + FE + Ops)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)
- **Acceptance Criteria (QA)**:
  1. Dong bo duoc toi thieu 4 loai master data ngoai `materials`.
  2. Co conflict policy ro rang (`skip/overwrite/manual`) va log actor + timestamp.
  3. Chay lai command idempotent, khong tao duplicate.

### TICKET PM-24 (P1)
- **Title**: MPI merge workflow + golden record + review queue lien chi nhanh
- **Type**: Story (BE + FE + Ops)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)
- **Acceptance Criteria (QA)**:
  1. Co queue duplicate de review/merge thu cong.
  2. Merge giu day du mapping lich su patient_id cu -> patient_id chinh.
  3. Toan bo merge action co audit log va rollback policy.

### TICKET PM-25 (P1)
- **Title**: KPI benchmarking + anomaly alert (chi nhanh/bac si/khung gio)
- **Type**: Story (Data + BE + FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)
- **Acceptance Criteria (QA)**:
  1. KPI view co benchmark theo branch va doctor (ngay/tuan/thang).
  2. Co alert khi vuot nguong no-show, chair utilization, treatment acceptance.
  3. Alert co owner va trang thai xu ly (`new/ack/resolved`).

### TICKET PM-26 (P1)
- **Title**: Snapshot lineage versioning + checksum + drift detection
- **Type**: Story (Data + BE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)
- **Acceptance Criteria (QA)**:
  1. Moi snapshot co `schema_version` + checksum payload.
  2. Detect duoc drift khi cong thuc/khoi nguon thay doi.
  3. Co bao cao so sanh snapshot truoc/sau de audit.

### TICKET PM-27 (P1)
- **Title**: RBAC defense-in-depth: anti-bypass review + policy test matrix
- **Type**: Story (BE + QA)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)
- **Acceptance Criteria (QA)**:
  1. Co checklist endpoint/action nhay cam khong duoc bypass gate/policy.
  2. Co test matrix role-action cho cac action-level permission quan trong.
  3. CI fail neu action nhay cam moi khong co authorization test.

---

## 5) Backlog P2 (Medium)

### TICKET PM-21 (P2)
- **Title**: Loyalty + referral + reactivation flow
- **Type**: Story (Product + BE/FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)

### TICKET PM-22 (P2)
- **Title**: Predictive model cho no-show/churn risk
- **Type**: Discovery + Story (Data)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)

---

## 6) De xuat thu tu trien khai

1. `Wave 1 (on dinh van hanh)`: PM-01..PM-10
2. `Wave 2 (tu dong hoa + do luong)`: PM-11..PM-20
3. `Wave 3 (scale hardening)`: PM-23..PM-27
4. `Wave 4 (tang truong)`: PM-21..PM-22

---

## 7) Backlog Production-Ready cho Multi-Branch (Re-open)

Ghi chu:
- Backlog PM-01..PM-27 duoc danh dau Done theo pham vi code/ticket.
- Audit production cho mo hinh da chi nhanh cho thay con khoang trong ve security, tenancy, data attribution, migration drift.
- Cac ticket duoi day la backlog bo sung bat buoc truoc go-live da chi nhanh.

### TICKET PM-28 (P0)
- **Title**: Security gate hardening cho Admin panel + automation command
- **Type**: Story (BE + Security)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Bo `canAccessPanel(): true` vo dieu kien, enforce theo role + status user.
  - Khong cho bypass `ActionGate` o console khi khong co actor hop le.
  - Chuan hoa co che service-account/automation-actor cho scheduler.
- **Acceptance Criteria (QA)**:
  1. User khong du role khong vao duoc Filament admin.
  2. Command nhay cam fail neu khong co actor hop le.
  3. Co test role matrix cho login panel + command authorization.

### TICKET PM-29 (P0)
- **Title**: Branch isolation enforcement backend (tenancy by policy + query)
- **Type**: Story (BE + Architecture)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Enforce branch-scope o policy/query cho Patient, Appointment, Invoice, Payment, Prescription, Note, print endpoints.
  - Chan truy cap cheo chi nhanh khi chi co role van hanh tai 1 chi nhanh.
  - Dinh nghia ro role co quyen xem lien chi nhanh (vd Admin HQ).
- **Acceptance Criteria (QA)**:
  1. User chi nhanh A khong xem/sua du lieu chi nhanh B (tru role HQ).
  2. Tat ca API/page/print route nhay cam deu qua branch-aware authorization.
  3. Co test integration cho cross-branch access denied.

### TICKET PM-30 (P0)
- **Title**: Finance branch attribution model (invoice/payment branch_id) + KPI dong bo
- **Type**: Story (BE + Data)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Bo sung/hoan tat `branch_id` tren finance entities can thiet.
  - Backfill du lieu lich su theo quy tac nghiep vu.
  - Sua KPI doanh thu/collection khong con phu thuoc `patient.first_branch_id`.
- **Status Note**:
  - Da xong schema + backfill + test attribution (`branch_id`) tren invoice/payment.
  - KPI da uu tien du lieu transactional branch, co fallback cho du lieu cu chua co `branch_id`.
  - Da bo sung command doi soat `finance:reconcile-branch-attribution` + export JSON + test regression.
- **Acceptance Criteria (QA)**:
  1. Revenue theo chi nhanh khop so lieu van hanh va ledger.
  2. Chuyen chi nhanh benh nhan khong lam sai lich su doanh thu.
  3. Co reconciliation report truoc/sau migration.

### TICKET PM-31 (P0)
- **Title**: Migration drift gate truoc go-live
- **Type**: Task (DevEx + BE)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Dong bo schema voi code (xu ly toan bo migration pending tren env deploy).
  - Them pre-deploy check fail neu con migration pending.
  - Khoa quy trinh release: khong cho push release neu schema drift.
- **Status Note**:
  - Da co command `schema:assert-no-pending-migrations` de fail fast khi con migration pending.
  - Da them test command gate + regression test `MigrationDriftGuardTest`.
  - Da them workflow CI (`.github/workflows/ci.yml`) chay migrate + schema drift gate truoc test suite.
- **Acceptance Criteria (QA)**:
  1. `migrate:status` tren staging/prod = khong pending.
  2. CI/CD fail neu phat hien schema drift.
  3. Trang report/feature moi khong loi do thieu bang/cot.

### TICKET PM-32 (P0)
- **Title**: Concurrency hardening (invoice no, plan code, claim no, overbooking/overpay)
- **Type**: Story (BE + DB)
- **Estimate**: 8 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Chuyen sinh so chung tu sang co che an toan concurrency (lock/sequence).
  - Hardening transaction cho overbooking check va overpay gate.
  - Bo sung test race condition cho cac luong tai chinh/lich hen.
- **Status Note**:
  - Da xong lock/transaction cho luong chinh (`invoice/installment/claim/overbooking/overpay`).
  - Da bo sung stress suite cho idempotency thanh toan + uniqueness ma chung tu + guard overpay/overbooking.
  - Da chay regression test lien quan finance/insurance/appointment sau hardening.
- **Acceptance Criteria (QA)**:
  1. Khong tao trung ma hoa don/claim/installment khi request dong thoi.
  2. Khong vuot policy overpay/overbooking duoi tai cao.
  3. Co test stress/parallel cho cac case quan trong.

### TICKET PM-33 (P1)
- **Title**: Print/export authorization + side-effect separation
- **Type**: Story (BE + Security)
- **Estimate**: 5 SP
- **Status**: In Progress (`2026-02-27`)
- **Scope**:
  - Them authorize day du cho invoice/payment/prescription print.
  - Tach side-effect `invoice_exported` khoi GET route (chuyen sang explicit action).
  - Log audit ro actor + branch + loai export.
- **Status Note**:
  - Da xong authorize print route + tach side-effect GET sang POST export.
  - Con thieu audit log rieng cho hanh dong export/print (actor/branch/type).
- **Acceptance Criteria (QA)**:
  1. Print route cheo chi nhanh bi chan dung policy.
  2. GET print khong mutating data.
  3. Audit export day du va truy vet duoc.

### TICKET PM-34 (P1)
- **Title**: KPI formula hardening cho multi-branch operations
- **Type**: Story (Data + Product)
- **Estimate**: 5 SP
- **Status**: In Progress (`2026-02-27`)
- **Scope**:
  - Rasoat lai cong thuc booking->visit, chair utilization, recall, no-show.
  - Chot definition event-level (booked, arrived, in-chair, completed).
  - Align dashboard + export + snapshot lineage voi metric definition moi.
- **Status Note**:
  - Da co lineage versioning/checksum + alerting + regression test cho 1 so KPI chinh.
  - Con mismatch event-level definition (`booking->visit` van dang tinh theo status appointment, chua khoi tao day du theo `arrived/in-chair`).
  - Con thieu KPI dictionary artifact va bo doi soat dashboard-vs-audit query.
- **Acceptance Criteria (QA)**:
  1. KPI dictionary duoc version hoa va ap dung thong nhat.
  2. So lieu dashboard khop query kiem toan.
  3. Co regression test cho cong thuc KPI chinh.

### TICKET PM-35 (P1)
- **Title**: DB index pack cho scale (care/appointment/finance)
- **Type**: Task (DB + BE)
- **Estimate**: 5 SP
- **Status**: In Progress (`2026-02-27`)
- **Scope**:
  - Bo sung composite index theo query thuc te cho notes care queue, appointment capacity, finance aging.
  - Do EXPLAIN truoc/sau.
  - Chot playbook index rollout an toan.
- **Status Note**:
  - Da bo sung index cho notes/appointments/finance branch hot-path.
  - Con thieu baseline EXPLAIN truoc/sau va tai lieu ket qua de dong ticket.
- **Acceptance Criteria (QA)**:
  1. Query dashboard/list chinh dat SLA de ra.
  2. Khong full scan tren table lon o filter pho bien.
  3. Co tai lieu EXPLAIN baseline truoc/sau.

### TICKET PM-36 (P1)
- **Title**: Scheduler hardening cho environment nhieu node
- **Type**: Task (BE + Ops)
- **Estimate**: 3 SP
- **Status**: In Progress (`2026-02-27`)
- **Scope**:
  - Them lock `withoutOverlapping`/`onOneServer` cho command quan trong.
  - Chot timeout/retry/alert khi command fail.
  - Tranh duplicate run gay duplicate ticket/snapshot.
- **Status Note**:
  - Da xong `withoutOverlapping` + `onOneServer` cho command chinh.
  - Con thieu timeout/retry policy va alerting khi command qua SLA tren scheduler command.
- **Acceptance Criteria (QA)**:
  1. Command khong bi chay trung khi scale app nodes.
  2. Co canh bao khi job qua SLA.
  3. Khong tao duplicate care/snapshot do scheduler.

### TICKET PM-37 (P2)
- **Title**: Tech debt cleanup (legacy Doctor artifact + dead code path)
- **Type**: Task (BE Refactor)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Xoa/phan tach ro cac artifact legacy `Doctor` khong con dung.
  - Dung factory/policy/resource khong con schema backing.
  - Don dep code va test lien quan de giam nhieu maintenance.
- **Acceptance Criteria (QA)**:
  1. Khong con model/resource/factory orphan khong co table.
  2. Khong anh huong flow user doctor qua `users` resource.
  3. Test suite pass sau cleanup.

---

## 8) Thu tu go-live de xuat cho da chi nhanh

1. `Go-live Gate A (bat buoc)`: PM-28, PM-29, PM-30, PM-31, PM-32
2. `Go-live Gate B (on dinh van hanh)`: PM-33, PM-34, PM-35, PM-36
3. `Go-live Gate C (debt cleanup)`: PM-37
