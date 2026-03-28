# Refactor and Review Execution Plan

Tai lieu nay chuyen backlog sau baseline thanh chuoi phase co the trien khai tuan tu, an toan voi repo dang co co che auto-deploy khi push.

## Metadata

- Backlog source: `docs/roadmap/refactor-review-master-backlog.md`
- Audit source: `docs/reviews/program-audit-summary.md`
- Last updated: `2026-03-28`

## Current State

- `13/13` module da dat `Clean Baseline Reached`.
- `100/100` issue baseline da `Resolved`.
- Docs hub, module inventory, review pipeline, issue register, va module plans da co.
- Production release gates va production readiness pack da pass tren environment that ngay `2026-03-28`.
- Priority hien tai la shared contracts va structural convergence.

## Phase 0 - Docs Cleanup and Audit Packaging

- Status: `Completed`
- Goal:
  - Co mot bo tai lieu review/doc/backlog ro rang de khong review thieu module hay lap lai issue da fix.
- Scope:
  - `README`
  - `docs/README.md`
  - `docs/modules/README.md`
  - `docs/reviews/*`
  - `docs/roadmap/*`
- Dependency:
  - none
- Rollback risk:
  - `Low`
- Test strategy:
  - link/path review
  - metadata consistency review
- Deploy safety note:
  - docs-only, khong can deploy app runtime.

## Phase 1 - Rollout and Smoke-Test Gate

- Status: `Completed`
- Goal:
  - Dong khoang cach giua code baseline va moi truong that truoc khi lam refactor sau hon.
- Scope:
  - `RRB-001`
  - `RRB-002`
- Dependency:
  - Phase 0
- Rollback risk:
  - `High`
- Test strategy:
  - backup truoc rollout
  - pre/post migration verification
  - module smoke test theo wave
  - queue/provider/control-plane drills
- Deploy safety note:
  - Khong push gom wave lon.
  - Moi wave phai co rollback checklist, owner ro rang, va thoi gian quan sat sau deploy.

## Phase 2 - Low-Risk Fixes and Operator UX

- Status: `Completed`
- Goal:
  - Giam thao tac sai cua operator ma khong thay doi domain boundary lon.
- Scope:
  - `RRB-004`
  - `RRB-005`
  - `RRB-006`
- Dependency:
  - Phase 1
- Rollback risk:
  - `Low`
- Test strategy:
  - feature/browser tests cho operator-facing flows
  - smoke test queue triage/read-model
- Deploy safety note:
  - Chia thanh batch UI nho, khong gom nhieu module workflow trong mot deploy.

## Phase 3 - Authorization and Scope Hardening Convergence

- Status: `Completed`
- Goal:
  - Rut gon cac pattern auth/branch scope thanh contract on dinh toan he thong.
- Scope:
  - `RRB-003`
  - `RRB-007`
  - `RRB-008`
- Dependency:
  - Phase 1
- Rollback risk:
  - `Medium`
- Test strategy:
  - permission matrix tests
  - actor/branch scope contract tests
  - spot-check regression o module dai dien
- Deploy safety note:
  - Mọi thay doi seeder/policy/scope phai duoc review rieng va deploy bat dau tu module co blast radius nho hon.

## Phase 4 - Workflow Consistency and Auditability

- Status: `In progress`
- Goal:
  - Chuan hoa cac workflow mutation lane va audit reason contract giua module co state machine.
- Scope:
  - `RRB-009`
  - `RRB-010`
- Dependency:
  - Phase 3
- Rollback risk:
  - `Medium`
- Test strategy:
  - workflow transition tests
  - audit timeline tests
  - browser checks cho dangerous actions
- Progress update:
  - `RRB-009` da cover lane uu tien cho `APPT`, `TRT`, `FIN`, `SUP`, `ZNS` va cac observer audit lien quan.
  - `RRB-010` da co read-model dau tien cho patient operational timeline ben canh `ClinicalAuditTimelineService`.
  - Chua mo wave rieng cho `CARE` / `OPS` beyond baseline packs.
- Deploy safety note:
  - Lam tung workflow module mot.
  - Khong doi workflow contract cua nhieu module trong cung mot release.

## Phase 5 - Structural Refactor

- Goal:
  - Xu ly nhung diem coupling lon nhat giua domain va control-plane.
- Scope:
  - `RRB-011`
  - `RRB-013`
- Dependency:
  - Phase 4
- Rollback risk:
  - `High`
- Test strategy:
  - end-to-end reconciliation tests
  - concurrency tests
  - migration rehearsal
  - canary rollout va rollback drill
- Deploy safety note:
  - Chi nen lam tren branch/PR rieng, co feature flag hoac song song read-model neu can.

## Phase 6 - Performance and Reporting Hardening

- Goal:
  - Hoan thien layer report/read-model khi dataset va tan suat van hanh tang.
- Scope:
  - `RRB-012`
  - phan measurement/perf verification cua `RRB-002`
- Dependency:
  - Phase 1
  - Phase 4
- Rollback risk:
  - `Medium` den `High` tuy batch
- Test strategy:
  - explain/query measurement
  - export scope tests
  - production-like dataset smoke tests
  - snapshot freshness drills
- Deploy safety note:
  - Moi toi uu query/report phai co measurement truoc/sau, va tranh thay doi cung luc voi structural ledger refactor.

## Recommended Delivery Rhythm

1. Mot phase chi nen co `1-3` batch runtime.
2. Moi batch runtime nen co:
   - test file scope ro rang
   - smoke test list
   - rollback note
   - owner van hanh
3. Moi batch push len branch co PR rieng; chi merge `main` khi da chot deploy window.
4. Sau moi phase, cap nhat lai:
   - `docs/reviews/00-master-index.md`
   - `docs/reviews/program-audit-summary.md`
   - `docs/roadmap/refactor-review-master-backlog.md`

## Exit Conditions Per Phase

- Phase 1 xong khi:
  - migration/backfill con lai da chay
  - smoke tests control-plane/async lane co bang chung pass
- Phase 2 xong khi:
  - operator-facing friction chinh da duoc giam ma khong mo lai auth/scope regression
- Phase 3 xong khi:
  - shared auth/scope contracts da duoc chot o muc du dung lai
- Phase 4 xong khi:
  - workflow + audit reason pattern da nhat quan tren nhom module uu tien
- Phase 5 xong khi:
  - immutable/adjustment/control-plane refactor da duoc tach thanh lanes ro rang
- Phase 6 xong khi:
  - report/read-model hot path co metric va cadence theo doi ro rang
