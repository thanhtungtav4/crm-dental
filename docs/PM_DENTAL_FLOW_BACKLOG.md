# PM Backlog - Dental CRM Flow Hardening

Cap nhat: 2026-03-01
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
- **Status Note**:
  - Da bo sung helper trung tam `BranchAccess` va enforce branch guard o model-level write path (`Customer/Patient/TreatmentPlan/Appointment/Invoice/Payment`).
  - Da harden branch-scope o Filament create-flow (`Customer/Patient/Appointment/TreatmentPlan/Invoice/Payment`) va action nhanh tren list.
  - Da bo sung regression test `BranchWriteIsolationGuardTest` + cap nhat test multi-branch transfer/realtime lead theo rule branch isolation.
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
  - Da bo sung `--apply` backfill transaction branch attribution va bao cao `before/after` trong cung 1 lan chay.
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
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Them authorize day du cho invoice/payment/prescription print.
  - Tach side-effect `invoice_exported` khoi GET route (chuyen sang explicit action).
  - Log audit ro actor + branch + loai export.
- **Status Note**:
  - Da xong authorize print route + tach side-effect GET sang POST export.
  - Da bo sung audit log rieng cho print/export (invoice/payment/prescription) voi actor + branch + channel + output.
  - Da bo sung test xac nhan GET khong mutate va co audit log truy vet.
- **Acceptance Criteria (QA)**:
  1. Print route cheo chi nhanh bi chan dung policy.
  2. GET print khong mutating data.
  3. Audit export day du va truy vet duoc.

### TICKET PM-34 (P1)
- **Title**: KPI formula hardening cho multi-branch operations
- **Type**: Story (Data + Product)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Rasoat lai cong thuc booking->visit, chair utilization, recall, no-show.
  - Chot definition event-level (booked, arrived, in-chair, completed).
  - Align dashboard + export + snapshot lineage voi metric definition moi.
- **Status Note**:
  - Da chuyen `visit_count` sang event-level theo `visit_episodes` (`arrived/in_chair/completed`) thay vi dua tren status appointment.
  - Da bo sung KPI dictionary versioned (`operational_kpi_formula.v2`) vao lineage payload + formula signature.
  - Da bo sung regression test cho event definition KPI va snapshot lineage.
- **Acceptance Criteria (QA)**:
  1. KPI dictionary duoc version hoa va ap dung thong nhat.
  2. So lieu dashboard khop query kiem toan.
  3. Co regression test cho cong thuc KPI chinh.

### TICKET PM-35 (P1)
- **Title**: DB index pack cho scale (care/appointment/finance)
- **Type**: Task (DB + BE)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Bo sung composite index theo query thuc te cho notes care queue, appointment capacity, finance aging.
  - Do EXPLAIN truoc/sau.
  - Chot playbook index rollout an toan.
- **Status Note**:
  - Da bo sung index cho notes/appointments/finance branch hot-path.
  - Da bo sung command `reports:explain-ops-hotpaths` de sinh EXPLAIN baseline artifact (JSON) cho cac hot-path query.
  - Da bo sung test command baseline va flag full-scan detection cho tung query key.
- **Acceptance Criteria (QA)**:
  1. Query dashboard/list chinh dat SLA de ra.
  2. Khong full scan tren table lon o filter pho bien.
  3. Co tai lieu EXPLAIN baseline truoc/sau.

### TICKET PM-36 (P1)
- **Title**: Scheduler hardening cho environment nhieu node
- **Type**: Task (BE + Ops)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Them lock `withoutOverlapping`/`onOneServer` cho command quan trong.
  - Chot timeout/retry/alert khi command fail.
  - Tranh duplicate run gay duplicate ticket/snapshot.
- **Status Note**:
  - Da chuyen scheduler critical command qua wrapper `ops:run-scheduled-command` de enforce timeout/retry/alert policy thong nhat.
  - Da bo sung runtime config scheduler trong Integration Settings (`scheduler.command_*`) + lock TTL tinh theo policy runtime.
  - Da bo sung audit alert cho `command_failed`/`timeout`/`sla_breach` va test regression cho retry + schedule lock.
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

### TICKET PM-38 (P0)
- **Title**: Action permission baseline hardening (drift guard + backfill)
- **Type**: Story (Security + Ops)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Backfill idempotent `Action:*` permissions + role matrix cho env da migrate nhung chua reseed.
  - Bo sung command gate `security:assert-action-permission-baseline` (co `--sync`) de check drift runtime.
  - Gan gate vao CI sau migration de chan deploy khi baseline phan quyen action-level lech.
- **Status Note**:
  - Da them migration backfill `ActionPermission` + matrix role theo `SensitiveActionRegistry`.
  - Migration backfill da duoc chuan hoa theo huong self-contained (khong goi runtime service container) de an toan rollback/deploy dai han.
  - Da them command assert/sync baseline va test drift + repair.
  - Da cap nhat CI workflow voi `Action permission baseline gate`.
- **Acceptance Criteria (QA)**:
  1. Command automation khong fail do thieu permission `Action:*` tren env da chi nhanh.
  2. Role matrix action-level khop `SensitiveActionRegistry`.
  3. CI fail neu baseline permission bi drift.

---

## 8) Thu tu go-live de xuat cho da chi nhanh

1. `Go-live Gate A (bat buoc)`: PM-28, PM-29, PM-30, PM-31, PM-32, PM-38, PM-39
2. `Go-live Gate B (on dinh van hanh)`: PM-33, PM-34, PM-35, PM-36, PM-40
3. `Go-live Gate C (debt cleanup)`: PM-37
4. `Go-live Gate D (release control + regression)` : PM-41, PM-42, PM-43, PM-44, PM-45, PM-46

---

## 9) Backlog mo rong sau audit production (Moi)

### TICKET PM-39 (P1)
- **Title**: Branch snapshot attribution cho clinical records (Prescription/Note/Consent)
- **Type**: Story (BE + Data + Security)
- **Estimate**: 5 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Bo sung `branch_id` tai record-level cho clinical entities quan trong de co ownership lich su.
  - Migration backfill theo nguyen tac "branch tai thoi diem tao record".
  - Cap nhat policy/print/audit de dung branch snapshot thay vi suy dien tu `patient.first_branch_id`.
- **Status Note**:
  - Da bo sung `branch_id` + index cho `prescriptions`, `notes`, `consents` kem migration backfill du lieu lich su.
  - Da cap nhat model/policy/resource de enforce branch isolation bang snapshot record-level.
  - Da bo sung audit metadata + test cross-branch cho note/consent/prescription print sau khi chuyen chi nhanh patient.
- **Acceptance Criteria (QA)**:
  1. Chuyen chi nhanh benh nhan khong lam thay doi quyen truy cap record cu.
  2. Print/export policy theo branch snapshot, khong phu thuoc branch hien tai cua patient.
  3. Co test cross-branch denied/allowed cho record lich su.

### TICKET PM-40 (P2)
- **Title**: Harden scheduler actor model (service account + rotation policy)
- **Type**: Task (Security + Ops)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Thay `CARE_AUTOMATION_ACTOR_USER_ID` bang service account chuyen biet cho automation.
  - Bo sung health check canh bao khi actor bi khoa/mat permission.
  - Chot quy trinh rotation/quyen toi thieu cho scheduler actor.
- **Status Note**:
  - Da them baseline role `AutomationService` + permission toi thieu `Action:AutomationRun` va service account scheduler.
  - Da bo sung `AutomationActorResolver` + command `security:check-automation-actor --strict` va scheduler health check dinh ky.
  - Da cap nhat `ActionGate` va wrapper scheduler de fail-fast + audit alert khi actor khong hop le.
- **Acceptance Criteria (QA)**:
  1. Scheduler fail fast neu actor khong hop le va co alert ro rang.
  2. Rotation actor khong gay gian doan command critical.
  3. Co test gate cho actor automation.

### TICKET PM-41 (P1)
- **Title**: Release gate orchestration command (single entrypoint)
- **Type**: Story (DevEx + QA + Ops)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Them command `ops:run-release-gates` de chay checklist gate theo profile (`ci/ops/production`).
  - Ho tro `--dry-run` de kiem tra release plan truoc khi chay that.
  - Ho tro `--with-finance --from --to` de chen gate doi soat finance attribution vao checklist.
- **Acceptance Criteria (QA)**:
  1. Co 1 command duy nhat de chay gate checklist khong can go tung command le.
  2. Command fail neu gate con loi va in danh sach step fail.
  3. Co test regression cho invalid profile + dry-run + profile ci.

### TICKET PM-42 (P1)
- **Title**: CI gate profile standardization
- **Type**: Task (DevEx)
- **Estimate**: 2 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Cap nhat workflow CI su dung `ops:run-release-gates --profile=ci`.
  - Giam drift giua local go-live checklist va CI pipeline.
- **Acceptance Criteria (QA)**:
  1. CI co step gate profile ro rang.
  2. Schema drift + action baseline tiep tuc duoc enforce thong qua command tong.

### TICKET PM-43 (P1)
- **Title**: Admin critical page smoke pack (treatment + integration)
- **Type**: Story (QA + FE)
- **Estimate**: 3 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Bo sung smoke test route-level cho cac man hinh critical:
    - patient exam-treatment
    - treatment plan create (chan doan dieu tri)
    - integration settings
    - customer list
  - Chan regression 500/permission/layout break sau cac dot refactor.
- **Acceptance Criteria (QA)**:
  1. Test fail ngay khi mot page critical khong load duoc.
  2. Co assertion text key de bao dam page render dung context nghiep vu.

### TICKET PM-44 (P2)
- **Title**: Release gate profile expansion cho ops/production
- **Type**: Story (Ops)
- **Estimate**: 2 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Profile `ops` bo sung gate `reports:explain-ops-hotpaths --strict`.
  - Profile `production` bo sung gate health check scheduler actor (`security:check-automation-actor --strict`).
- **Acceptance Criteria (QA)**:
  1. Profile `ci`, `ops`, `production` cho output step list khac nhau dung theo muc dich.
  2. Dry-run hien ro command map cua tung profile.

### TICKET PM-45 (P2)
- **Title**: Finance reconciliation hook vao release gate
- **Type**: Task (Finance + Ops)
- **Estimate**: 2 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Gate runner ho tro chen `finance:reconcile-branch-attribution` co range date.
  - Tu dong tao export path report doi soat khi chay tu gate runner.
- **Acceptance Criteria (QA)**:
  1. Khi bat `--with-finance`, gate runner goi dung command reconciliation + args.
  2. Dry-run hien duoc args from/to/export de de audit.

### TICKET PM-46 (P2)
- **Title**: Backlog-go-live alignment cho wave PM-41..PM-45
- **Type**: Task (PM + QA)
- **Estimate**: 1 SP
- **Status**: Done (`2026-02-27`)
- **Scope**:
  - Cap nhat backlog status theo code da trien khai.
  - Dong bo thu tu gate de doi release co checklist ro rang va co test canh bao regression.
- **Acceptance Criteria (QA)**:
  1. Backlog co du ticket PM-41..PM-45 voi scope va acceptance ro rang.
  2. Khong con ticket "lam roi nhung chua ghi backlog" trong wave nay.

---

## 10) Open (Feasibility backlog tu external review)

### TICKET PM-47 (P0)
- **Title**: Exam session separation khoi clinical note aggregate
- **Type**: Story (BE + FE + Data)
- **Estimate**: 13 SP
- **Status**: Done (`2026-03-01`)
- **Decision**: Adopt
- **Scope**:
  - Tao bang `exam_sessions`, mapping relation voi patient/treatment/prescription.
  - Migration du lieu cu sang aggregate moi.
  - Enforce lifecycle `draft -> planned -> in_progress -> completed -> locked`.
- **Status Note**:
  - Da tao schema `exam_sessions` + backfill `exam_session_id` tren `clinical_notes/clinical_orders/prescriptions`.
  - Da chuyen patient workspace (`PatientExamForm`) sang doc session moi, giu backward-compat voi `clinical_notes`.
  - Da hoan tat lifecycle theo event nghiep vu: `completed` khi tien trinh ngay da hoan tat, `locked` khi phat sinh don thuoc/ket qua lam sang final.
  - Da bo sung service recompute lifecycle va regression test cho state transition.
- **Acceptance Criteria (QA)**:
  1. Du lieu clinical cu khong mat sau migration.
  2. Workspace benh nhan (`exam-treatment`) hoat dong on dinh.
  3. Session co du lieu tien trinh thi khong cho xoa.

### TICKET PM-48 (P0)
- **Title**: Treatment progress days/items model hoa day thuc hien
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Status**: Done (`2026-03-01`)
- **Decision**: Adopt
- **Scope**:
  - Tao `treatment_progress_days` va `treatment_progress_items`.
  - Dong bo treatment state tren odontogram/plan item.
  - Tinh tong chi phi theo ngay va theo session.
- **Status Note**:
  - Da tao schema + backfill du lieu tu `treatment_sessions`.
  - Da dong bo runtime qua observer (`TreatmentSessionObserver`) va service `TreatmentProgressSyncService`.
  - Da noi vao UI `exam-treatment` (table Tien trinh dieu tri doc tu progress items) + bo sung tong hop theo ngay, tong chi phi theo session/day.
  - Da bo sung guard form tao/sua treatment session de bat buoc mapping `treatment_plan` ↔ `plan_item` dung nghiep vu.
  - Da bo sung regression tests cho summary theo ngay/session va form guard.
- **Acceptance Criteria (QA)**:
  1. Them ngay dieu tri + item tu plan hoat dong dung.
  2. Tong tien khop line items.
  3. UI table tien trinh khong vo layout desktop/mobile.

### TICKET PM-49 (P0)
- **Title**: Performance baseline + pre-aggregation cho report hot paths
- **Type**: Story (BE + Data)
- **Estimate**: 8 SP
- **Status**: Open
- **Decision**: Re-scope (MVP)
- **Scope**:
  - Pre-aggregate 3 report nong nhat (ops dashboard, revenue branch, care queue).
  - Chot SLA p95 list/workspace/report.
  - Artifact EXPLAIN + latency baseline truoc/sau.
- **Acceptance Criteria (QA)**:
  1. Report hot path dat SLA da chot.
  2. Khong full-scan tren strict explain gate.
  3. Co baseline de so sanh regression.

### TICKET PM-50 (P0)
- **Title**: Security ops hardening (MFA + session policy + PHI read log)
- **Type**: Story (Security + BE)
- **Estimate**: 8 SP
- **Status**: Open
- **Decision**: Re-scope (MVP)
- **Scope**:
  - MFA bat buoc cho role `Admin/Manager`.
  - Session timeout + lockout co audit.
  - Log truy cap read PHI cho clinical/EMR entities nhay cam.
- **Acceptance Criteria (QA)**:
  1. Role nhay cam khong MFA thi khong vao panel.
  2. Co lockout + timeout policy truy vet duoc.
  3. PHI access logs day du actor/entity/timestamp.

### TICKET PM-51 (P0)
- **Title**: Go-live reliability pack (backup/restore + monitoring + production gate)
- **Type**: Task (Ops + BE)
- **Estimate**: 5 SP
- **Status**: Open
- **Decision**: Adopt
- **Scope**:
  - Backup automation DB/file + restore drill.
  - Runbook alert (error rate, queue lag, scheduler miss).
  - Cap nhat profile production gate theo danh sach bat buoc.
- **Acceptance Criteria (QA)**:
  1. Restore drill pass tren staging.
  2. Monitoring alert map ro owner va threshold.
  3. Production gate fail-fast neu thieu precondition.

### TICKET PM-52 (P1)
- **Title**: Calendar operational view (day/week MVP)
- **Type**: Story (FE + BE)
- **Estimate**: 8 SP
- **Status**: Open
- **Decision**: Re-scope (MVP)
- **Scope**:
  - Day/week schedule view cho le tan.
  - Quick-create/reschedule + conflict warning.
  - Metrics bar theo appointment operational status.
- **Acceptance Criteria (QA)**:
  1. Luong dieu phoi lich nhanh hon list thuong.
  2. Conflict doctor/branch hien thi dung.
  3. Khong pha state machine appointment hien tai.

### TICKET PM-53 (P1)
- **Title**: Photo library completion (clinical-first)
- **Type**: Story (FE + BE)
- **Estimate**: 5 SP
- **Status**: Open
- **Decision**: Re-scope (MVP)
- **Scope**:
  - Chuan hoa loai anh `normal/ext/int/xray`.
  - Upload + paste clipboard on dinh trong clinical flow.
  - Retention policy theo clinic settings.
- **Acceptance Criteria (QA)**:
  1. Upload/paste chay on cho phieu kham.
  2. Filter/phan loai anh dung.
  3. Khong mat lien ket voi note/session.

### TICKET PM-54 (P1)
- **Title**: Patient contacts separation (nhieu nguoi lien he)
- **Type**: Story (BE + FE)
- **Estimate**: 3 SP
- **Status**: Open
- **Decision**: Adopt
- **Scope**:
  - Tao `patient_contacts` support nhieu nguoi lien he.
  - Migrate emergency contact cu neu co.
  - Hien thi relation manager tai ho so benh nhan.
- **Acceptance Criteria (QA)**:
  1. Ho tro >1 contact/benh nhan.
  2. Duu lieu cu giu duoc sau migration.

### TICKET PM-55 (P1)
- **Title**: CSKH SLA dashboard v2
- **Type**: Story (BE + FE)
- **Estimate**: 5 SP
- **Status**: Open
- **Decision**: Adopt
- **Scope**:
  - SLA board theo nhan vien/branch/channel.
  - Queue filter priority cho no-show/recall/follow-up.
  - Export view cho manager.
- **Acceptance Criteria (QA)**:
  1. Ticket overdue canh bao ro.
  2. Tong hop KPI CSKH khop du lieu ticket.

### TICKET PM-56 (P1)
- **Title**: Labo module foundation (orders + items)
- **Type**: Story (BE + FE)
- **Estimate**: 13 SP
- **Status**: Open
- **Decision**: Adopt
- **Scope**:
  - Tao `factory_orders`, `factory_order_items`.
  - Tab Xuong/Vat tu tai patient workspace.
  - State machine `ordered -> in_progress -> delivered`.
- **Acceptance Criteria (QA)**:
  1. CRUD dat xuong on dinh.
  2. Link dung voi patient/session branch context.

### TICKET PM-57 (P1)
- **Title**: Material issue notes integration voi ton kho
- **Type**: Story (BE)
- **Estimate**: 5 SP
- **Status**: Open
- **Decision**: Adopt
- **Scope**:
  - Tao `material_issue_notes`, `material_issue_items`.
  - Tru ton kho + ghi inventory transactions.
  - Canh bao ton kho thap theo nguong.
- **Acceptance Criteria (QA)**:
  1. Xuat vat tu tru ton kho dung.
  2. Co canh bao khi duoi min stock.

### TICKET PM-58 (P1)
- **Title**: ZNS campaign lifecycle MVP
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
- **Status**: Open
- **Decision**: Re-scope (MVP)
- **Scope**:
  - Campaign draft/schedule/run/complete.
  - Retry co idempotency + delivery logs.
  - Audience filter co ban (branch/source/last_visit).
- **Acceptance Criteria (QA)**:
  1. Khong gui trung khi retry.
  2. Theo doi duoc sent/failed theo campaign.

### TICKET PM-59 (P2)
- **Title**: API v1 expansion theo use-case mobile/SPA
- **Type**: Story (BE)
- **Estimate**: 13 SP
- **Status**: Open
- **Decision**: Re-scope (MVP)
- **Scope**:
  - Endpoint MVP: Auth, Appointment, Patient summary, Invoice summary.
  - Sanctum + rate-limit + response envelope.
  - OpenAPI docs cho endpoint MVP.
- **Acceptance Criteria (QA)**:
  1. Mobile/SPA co the dung luong co ban.
  2. Auth/rate-limit/permission pass.

### TICKET PM-60 (P2)
- **Title**: Wallet/deposit ledger full module
- **Type**: Story (Finance + BE)
- **Estimate**: 5 SP
- **Status**: Open
- **Decision**: Defer (sau wave A/B)
- **Scope**:
  - Vi benh nhan + giao dich nap/tru/hoan.
  - Dong bo voi payment/receipt lifecycle.
- **Acceptance Criteria (QA)**:
  1. Balance khong am.
  2. Co truy vet giao dich day du.
