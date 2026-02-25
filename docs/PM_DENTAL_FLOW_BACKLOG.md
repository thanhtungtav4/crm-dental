# PM Backlog - Dental CRM Flow Hardening

Cap nhat: 2026-02-25
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
- **Scope**:
  - Lich ky thanh toan, trang thai qua han, nhac no tu dong.
- **Acceptance Criteria (QA)**:
  1. Moi ky tra gop co due state ro rang.
  2. Co nhac no theo aging bucket va log ket qua gui.

### TICKET PM-08 (P0)
- **Title**: Insurance claim workflow
- **Type**: Story (BE + FE)
- **Estimate**: 8 SP
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

### TICKET PM-12 (P1)
- **Title**: No-show recovery automation playbook
- **Type**: Story (BE + CSKH)
- **Estimate**: 5 SP

### TICKET PM-13 (P1)
- **Title**: Follow-up pipeline cho plan chua chot
- **Type**: Story (BE + FE)
- **Estimate**: 5 SP

### TICKET PM-14 (P1)
- **Title**: Payment reminder automation theo aging
- **Type**: Story (BE)
- **Estimate**: 3 SP

### TICKET PM-15 (P1)
- **Title**: KPI pack van hanh nha khoa (booking->visit, no-show, acceptance, chair, recall, LTV)
- **Type**: Story (BE + Data + FE)
- **Estimate**: 8 SP

### TICKET PM-16 (P1)
- **Title**: Report data lineage + snapshot SLA hardening
- **Type**: Story (BE + Data)
- **Estimate**: 5 SP

### TICKET PM-17 (P1)
- **Title**: Multi-branch master data sync + conflict resolution
- **Type**: Story (BE)
- **Estimate**: 8 SP

### TICKET PM-18 (P1)
- **Title**: MPI + dedupe policy lien chi nhanh
- **Type**: Story (BE + Ops)
- **Estimate**: 5 SP

### TICKET PM-19 (P1)
- **Title**: RBAC action-level freeze va enforce backend
- **Type**: Story (BE)
- **Estimate**: 5 SP

### TICKET PM-20 (P1)
- **Title**: Audit log mo rong cho clinical/finance/care critical events
- **Type**: Story (BE)
- **Estimate**: 5 SP

---

## 4) Backlog P2 (Medium)

### TICKET PM-21 (P2)
- **Title**: Loyalty + referral + reactivation flow
- **Type**: Story (Product + BE/FE)
- **Estimate**: 8 SP

### TICKET PM-22 (P2)
- **Title**: Predictive model cho no-show/churn risk
- **Type**: Discovery + Story (Data)
- **Estimate**: 8 SP

---

## 5) De xuat thu tu trien khai

1. `Wave 1 (on dinh van hanh)`: PM-01..PM-10
2. `Wave 2 (tu dong hoa + do luong)`: PM-11..PM-20
3. `Wave 3 (tang truong)`: PM-21..PM-22
