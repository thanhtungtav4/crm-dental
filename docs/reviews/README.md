# Review Pipeline

Tai lieu nay mo ta pipeline review theo module va la quy uoc luu artifact review/issue/plan trong repo.

## Muc tieu

Bo prompt nay dung de review codebase Laravel + Filament cho CRM phong nha khoa theo pipeline co dinh, theo chien luoc `module nao sach module do`.

Pipeline nay duoc thiet ke de:
- luu artifact vao repo
- tham chieu lai duoc giua review, issue, plan, fix, re-audit
- de AI local doc lai dung phase va dung source of truth
- giu mapping on dinh giua markdown trong repo va GitHub Issues neu mo sau nay

## Source Of Truth

- `docs/reviews/modules/*.md`: narrative review va issue summary cua module
- `docs/reviews/issues/*.md`: canonical issue register cua module
- `docs/reviews/plans/*.md`: canonical implementation plan va task register cua module
- `docs/reviews/00-master-index.md`: canonical phase, verdict, top risk, va bieu do tong quan
- `docs/reviews/program-audit-summary.md`: tong hop cap chuong trinh sau khi da co du review/issue/plan theo module
- `docs/roadmap/refactor-review-master-backlog.md`: backlog canonical cho phase sau baseline
- `docs/roadmap/refactor-review-execution-plan.md`: phan ky rollout/refactor cap chuong trinh

## Status Enum

Chi duoc dung cac gia tri sau:
- `Pending Review`
- `Reviewing`
- `Reviewed`
- `Planning`
- `In Fix`
- `Re-audit Needed`
- `Clean Baseline Reached`

## Naming Convention

- Issue ID: `APPT-001`, `FIN-004`, `CLIN-002`
- Task ID: `TASK-APPT-001`, `TASK-FIN-003`
- Khong duoc doi Issue ID hoac Task ID sau khi da phat hanh, tru khi co migration tai lieu ro rang.

## Global Rules

- Chi review module hien tai va dependency edge lien quan. Khong mo rong thanh review ca he thong neu khong can thiet.
- Moi nhan dinh manh phai co evidence tu code, schema, query shape, Filament resource, hoac user flow cu the.
- Neu thieu thong tin, phai ghi ro: `Thong tin con thieu lam giam do chinh xac review`.
- Neu phat hien loi nghiem trong, mo dau bang: `🚨 CANH BAO NGHIEM TRONG`.
- Uu tien context Laravel / Filament / production safety / race-condition / branch scoping / auditability.
- Review file khong duoc tro thanh source of truth cua issue chi tiet. Issue file moi la canonical source.

## Cach dung pipeline

1. Chay Prompt 1 de review module.
2. Chay Prompt 2 de luu review markdown vao repo.
3. Chay Prompt 3 de trich issue va tao issue file canonical.
4. Chay Prompt 4 de tao implementation plan cho module.
5. Chay Prompt 5 de fix tung task theo plan.
6. Chay Prompt 6 de re-audit module sau khi fix.
7. Chay Prompt 7 de cap nhat master index.

## Prompt 1 - Review Module

```md
Ban la mot team AI senior chuyen review codebase Laravel + Filament cho CRM phong nha khoa production-grade.

Toi dang review THEO TUNG MODULE, khong review ca he thong mot lan.

## Muc tieu
1. Review that sau module hien tai
2. Chuan bi du lieu de luu review vao repo
3. Chuan bi issue list cho module
4. Lam nen cho plan, fix, re-audit ve sau
5. Giu dung chien luoc: module nao sach module do

## Ngu canh he thong
- du lieu nhay cam benh nhan / ho so y te / thanh toan
- da chi nhanh
- nhieu vai tro: le tan, bac si, tro thu, ke toan, quan ly, admin
- Laravel + Filament
- production-ready
- khong duoc co race-condition o lich hen, thanh toan, ton kho, dieu tri
- UX phai de dung cho nguoi khong ky thuat

## Module metadata
- Module code: [MODULE CODE]
- Module name: [MODULE NAME]
- Current status: [CURRENT STATUS]
- Review file: docs/reviews/modules/[MODULE-REVIEW-FILE].md
- Issue file: docs/reviews/issues/[MODULE-CODE]-issues.md
- Plan file: docs/reviews/plans/[MODULE-CODE]-plan.md
- Issue ID prefix: [MODULE-CODE]-
- Task ID prefix: TASK-[MODULE-CODE]-
- Dependencies: [LIST MODULE CODES]

## Yeu cau review
Review du 4 lop:
- Architecture
- Database
- Domain logic
- UI/UX

## Vai tro phan tich theo dung thu tu
1. Architect
2. Domain Logic Auditor
3. QA/UX
4. Arbiter

## Bat buoc phai kiem tra
- database schema
- foreign keys
- unique / composite indexes
- branch scoping
- authorization / policy / RBAC
- auditability / traceability
- state machine / status transitions
- transaction / locking / race-condition / idempotency
- N+1 / eager loading / indexing / filter query / report impact
- Filament Resource / form / table / relation manager / actions / custom pages
- real user flow
- edge cases
- test coverage gaps

## Luat danh gia
Moi nhom phai danh gia:
- Tot / Trung binh / Kem
kem ly do cu the.

Neu thieu thong tin, bat buoc ghi:
`Thong tin con thieu lam giam do chinh xac review`

Moi ket luan manh phai gan voi evidence cu the tu code, schema, flow, resource hoac query shape.

Neu phat hien loi nghiem trong, phai mo dau bang:
🚨 CANH BAO NGHIEM TRONG

## Dinh dang output bat buoc

# 1. Tom tat dieu hanh
- Muc do an toan hien tai
- Muc do rui ro nghiep vu
- Muc do san sang production
- Cac canh bao nghiem trong

# 2. Architect Review
## 2.1 Security
## 2.2 Data Integrity & Database
## 2.3 Concurrency / Race-condition
## 2.4 Performance / Scalability
## 2.5 Maintainability
## 2.6 Kien truc de xuat tot hon

# 3. Domain Logic Review
## 3.1 Workflow chinh
## 3.2 State transitions
## 3.3 Missing business rules
## 3.4 Invalid states / forbidden transitions
## 3.5 De xuat service / action / state machine / transaction boundary

# 4. QA/UX Review
## 4.1 User flow
## 4.2 Filament UX
## 4.3 Edge cases quan trong
## 4.4 Diem de thao tac sai
## 4.5 De xuat cai thien UX

# 5. Arbiter Verdict
## 5.1 Xep hang module: A / B / C / D
## 5.2 3 van de nguy hiem nhat
## 5.3 5 viec nen sua som nhat
## 5.4 Test can bo sung
## 5.5 Dependency voi module khac

# 6. Candidate Issue List
Moi issue theo format:
## [ISSUE-ID] [Tieu de]
- Severity:
- Category:
- Module:
- Description:
- Why it matters:
- Evidence:
- Suggested fix:
- Affected areas:
- Tests needed:
- Dependencies:
- Suggested order:

## Module hien tai
- Module code: [MODULE CODE]
- Module name: [MODULE NAME]

## Noi dung can review
[PASTE CODE / SCHEMA / RESOURCE / FLOW HERE]
```

## Prompt 2 - Save Review Markdown

```md
Hay chuyen ket qua review vua roi thanh file markdown chuan de luu trong repo.

## File path
`docs/reviews/modules/[MODULE-REVIEW-FILE].md`

## Rules
- Giu nguyen verdict
- Khong doi Issue ID
- File nay la review narrative + issue summary, khong phai canonical issue register
- Moi issue chi can xuat hien o dang summary, khong lap lai full issue body

## Noi dung bat buoc
# Metadata
- Module code
- Module name
- Current status
- Current verdict
- Review file
- Issue file
- Plan file
- Issue ID prefix
- Task ID prefix
- Dependencies
- Last updated

# Scope
# Context
# Executive Summary
# Architecture Findings
## Security
## Data Integrity & Database
## Concurrency / Race-condition
## Performance / Scalability
## Maintainability
## Better Architecture Proposal

# Domain Logic Findings
## Workflow chinh
## State transitions
## Missing business rules
## Invalid states / forbidden transitions
## Service / action / state machine / transaction boundary de xuat

# QA/UX Findings
## User flow
## Filament UX
## Edge cases quan trong
## Diem de thao tac sai
## De xuat cai thien UX

# Issue Summary
Dung bang:
| Issue ID | Severity | Category | Title | Status | Short note |

# Dependencies
# Open Questions
# Recommended Next Steps
# Current Status

## Current Status chi dung mot trong:
- Pending Review
- Reviewing
- Reviewed
- Planning
- In Fix
- Re-audit Needed
- Clean Baseline Reached

## Input
### Module metadata
[PASTE MODULE METADATA HERE]

### Review result
[PASTE PROMPT 1 OUTPUT HERE]
```

## Prompt 3 - Extract Issues

```md
Ban la Issue Curator cho CRM Laravel + Filament.

Hay chuyen candidate issue list cua mot module thanh file issue markdown chuan de luu trong repo.

## File path
`docs/reviews/issues/[MODULE-CODE]-issues.md`

## Rules
- Giu nguyen Issue ID
- Khong doi severity neu chua co ly do ro rang
- Day la source of truth chinh cho issue cua module
- Moi issue co status ban dau: Open / Planned / In Fix / Partial / Resolved
- Khong tu y sinh issue ngoai pham vi module tru khi dependency that su chan fix
- Neu thieu thong tin, ghi ro

## Noi dung bat buoc
# Metadata
- Module code
- Module name
- Current status
- Current verdict
- Issue ID prefix
- Task ID prefix
- Review file
- Plan file
- Dependencies
- Last updated

# Issue Backlog
## [ISSUE-ID] [Tieu de]
- Severity:
- Category:
- Module:
- Description:
- Why it matters:
- Evidence:
- Suggested fix:
- Affected areas:
- Tests needed:
- Dependencies:
- Suggested order:
- Current status:
- Linked task IDs:

# Summary
- Open critical count
- Open high count
- Open medium count
- Open low count
- Next recommended action

## Input
### Module metadata
[PASTE MODULE METADATA HERE]

### Review result
[PASTE PROMPT 1 OUTPUT HERE]
```

## Prompt 4 - Make Module Plan

```md
Ban la Technical Planner cho CRM Laravel + Filament.

Dua tren review va issue list cua mot module, hay tao implementation plan rieng cho module do.

## Muc tieu
- Module nao review xong thi lap ke hoach rieng va lam sach module do
- Khong lap ke hoach cho toan he thong o buoc nay

## Rules
- Moi task phai map toi issue ID cu the
- Khong tao task mo ho khong co issue nguon
- Task phai phan anh dung thu tu uu tien fix
- Testing va re-audit la phan bat buoc

## Yeu cau
Hay tao plan theo thu tu:
1. Foundation fixes
2. Critical fixes
3. High priority fixes
4. Medium priority fixes
5. Low priority fixes
6. Testing & regression protection
7. Re-audit checklist

## Moi task phai co format
## [TASK-ID] [Tieu de]
- Based on issue(s):
- Priority:
- Objective:
- Scope:
- Why now:
- Suggested implementation:
- Affected files or layers:
- Tests required:
- Estimated effort: S / M / L
- Dependencies:
- Exit criteria:

## Cuoi cung phai co
- Execution order
- What can be done in parallel
- What must be done first
- Suggested milestone breakdown

## File path dau ra
`docs/reviews/plans/[MODULE-CODE]-plan.md`

## Input
### Module metadata
[PASTE MODULE METADATA HERE]

### Review markdown
[PASTE REVIEW MARKDOWN HERE]

### Issue markdown
[PASTE ISSUE MARKDOWN HERE]
```

## Prompt 5 - Fix Task

```md
Ban la Senior Laravel + Filament Engineer.

Toi se dua cho ban mot task cu the thuoc mot module cua CRM phong nha khoa.
Muc tieu la fix chuyen sau, an toan production, khong lan man.

## Bat buoc phai xet du
- Architecture impact
- Database impact
- Domain logic
- UI/UX impact
- Authorization / RBAC
- Transaction / locking / idempotency
- Audit log
- Test coverage
- Regression risk
- Dependency voi module khac

## Rules
- Chi fix task hien tai
- Khong mo rong scope sang module khac neu chua can
- Neu bi block boi module khac, ghi ro o Dependencies va Rollout notes
- Uu tien code Laravel / Filament cu the

## Output bat buoc
# 1. Root cause analysis
# 2. Design decision
# 3. Required code changes
# 4. Migration changes
# 5. Service / action changes
# 6. Filament / UI changes
# 7. Tests to add
# 8. Rollout / regression notes
# 9. Post-fix verification checklist

## Input
### Module metadata
[PASTE MODULE METADATA HERE]

### Task can xu ly
[PASTE TASK HERE]

### Review context
[PASTE RELEVANT REVIEW SECTION HERE]

### Related issue(s)
[PASTE RELEVANT ISSUE BLOCK HERE]

### Code lien quan
[PASTE CODE HERE]
```

## Prompt 6 - Re-Audit Module

```md
Ban dang o vai tro Re-Audit Reviewer cho module CRM Laravel + Filament.

Toi da fix mot so task cua module nay. Hay re-audit module dua tren:
- review cu
- issue list cu
- implementation plan
- code da sua

## Muc tieu
- xac nhan issue nao da resolved
- issue nao con open
- issue nao chi fix mot phan
- co regression risk moi phat sinh khong
- module da dat clean baseline chua

## Rules
- Khong doi Issue ID
- Chi them issue moi neu that su co risk moi do code sua tao ra
- Neu issue chi duoc fix mot phan thi phai ghi ro phan nao con ho
- Ket luan clean baseline chi khi ca code, test va re-audit cung dat

## Output bat buoc
# 1. Re-audit summary
# 2. Resolved issues
# 3. Partially resolved issues
# 4. Still open issues
# 5. New risks introduced
# 6. Updated verdict: A / B / C / D
# 7. Clean baseline status: Yes / No
# 8. Updated markdown block for review file
# 9. Updated markdown block for issue file
# 10. Suggested next actions

## Input
### Module metadata
[PASTE MODULE METADATA HERE]

### Old review
[PASTE OLD REVIEW]

### Old issues
[PASTE OLD ISSUES]

### Module plan
[PASTE MODULE PLAN]

### Updated code
[PASTE UPDATED CODE]
```

## Prompt 7 - Update Master Index

```md
Ban la Documentation Architect cho chuong trinh review CRM phong nha khoa.

Toi dang review theo tung module, luu tat ca vao repo.
Hay tao hoac cap nhat MASTER INDEX de AI sau nay co the doc lai nhanh va lap roadmap.

## File path
`docs/reviews/00-master-index.md`

## Muc tieu
- Theo doi module nao da review
- Module nao dang fix
- Module nao da dat clean baseline
- Tong hop issue nghiem trong nhat
- Giu buc tranh tong the cua he thong

## Rules
- Status chi dung:
  - Pending Review
  - Reviewing
  - Reviewed
  - Planning
  - In Fix
  - Re-audit Needed
  - Clean Baseline Reached
- Khong tu doi verdict neu module chua co review hoac re-audit moi
- Moi summary phai map ve file review, issue, plan tuong ung

## Noi dung bat buoc
# 1. Tong quan he thong
# 2. Danh sach module
Voi moi module ghi:
- Module code
- Module name
- Current status
- Review file
- Issue file
- Plan file
- Current verdict
- Top open risks
- Dependencies

# 3. Cross-module risks
# 4. Priority overview
# 5. Modules ready for deep fix
# 6. Modules needing re-audit
# 7. Suggested next module to review
# 8. Suggested next module to fix

## Input
[PASTE ALL CURRENT MODULE SUMMARIES HERE]
```

## Recommended Execution Contract

- Bat dau review mot module: doi status trong master index thanh `Reviewing`
- Xong Prompt 1 + Prompt 2 + Prompt 3: doi thanh `Reviewed`
- Xong Prompt 4: doi thanh `Planning`
- Bat dau Prompt 5: doi thanh `In Fix`
- Xong fix va cho audit lai: doi thanh `Re-audit Needed`
- Xong Prompt 6 va dat baseline: doi thanh `Clean Baseline Reached`
- Sau moi phase transition, chay Prompt 7 de sync `00-master-index.md`
