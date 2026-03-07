# Metadata

- Module code: `INT`
- Module name: `Integrations`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Review file: `docs/reviews/modules/INT-integrations.md`
- Issue file: `docs/issues/INT-issues.md`
- Plan file: `docs/planning/INT-plan.md`
- Issue ID prefix: `INT-`
- Task ID prefix: `TASK-INT-`
- Dependencies: `GOV, APPT, CLIN, ZNS, OPS`
- Last updated: `2026-03-07`

# Scope

- Review module `INT` theo 4 lop: architecture, database, domain logic, UI/UX.
- Trong pham vi baseline da duoc khoa:
  - auth/settings boundary tren `IntegrationSettings`
  - internal EMR mutation scope
  - transaction + optimistic revision cho runtime settings
  - payload governance + retention cho integration operational tables
  - secret rotation grace window + revoke flow

# Context

- `INT` la boundary tiep xuc voi he thong ngoai: Web Lead, Zalo OA/ZNS, Google Calendar, EMR internal API va scheduler automation actor.
- Day la module co blast radius cao vi mot sai lech nho co the:
  - lo secret provider
  - sua du lieu lam sang sai scope
  - giu PII/PHI raw qua muc can thiet
  - lam drift runtime toan he thong
- Re-audit nay duoc chot sau khi:
  - harden auth/page save surface
  - harden EMR internal mutation scope
  - them settings revision + transaction lock
  - ma hoa/sanitize payload van hanh + retention command
  - them workflow secret rotation co grace window va revoke command
  - chay regression suite rong cua module va full suite toan CRM

# Executive Summary

- Muc do an toan hien tai: `Tot cho baseline hien tai`
- Muc do rui ro nghiep vu: `Da giam ve muc kiem soat duoc`
- Muc do san sang production: `Dat baseline`, con follow-up rollout van hanh
- Canh bao nghiem trong con mo: `Khong con blocker baseline dang mo`
- Residual follow-up:
  - chay migrate tren moi truong thuc
  - smoke test grace token rollover voi client ngoai
  - can nhac tach delegated role `IntegrationAdmin` neu muon mo governance ve sau

# Architecture Findings

## Security

- Danh gia: `Trung binh -> Tot`
- Da khoa:
  - tach `View:IntegrationSettings`, `Manage:IntegrationRuntimeSettings`, `Manage:IntegrationSecrets`
  - `Manager` khong con sua secret/endpoint runtime nhay cam
  - internal EMR mutation reject note ngoai scope branch/record
  - inbound shared token (`web_lead.api_token`, `zalo.webhook_token`, `emr.api_key`) da ho tro grace window co kiem soat
- Residual note:
  - chua co delegated `IntegrationAdmin`; baseline hien tai uu tien safe-by-default cho `Admin`

## Data Integrity & Database

- Danh gia: `Trung binh -> Tot`
- Da khoa:
  - payload/log operational cua Web Lead, Zalo webhook, EMR sync, Google sync da duoc encrypt/sanitize
  - `clinic_setting_logs` co them `change_reason` va `context` de audit rotation/runtime change ro hon
  - them retention boundary + prune command cho du lieu operational integration
- Residual note:
  - can rollout migration moi tren DB thuc te va verify backfill encrypted payload

## Concurrency / Race-condition

- Danh gia: `Tot`
- Da khoa:
  - `IntegrationSettings::save()` di qua cache lock + transaction + optimistic revision
  - stale form bi chan, partial write bi loai bo
  - secret rotation khong con cat token cu ngay lap tuc; revoke di qua command rieng

## Performance / Scalability

- Danh gia: `Trung binh -> Tot`
- Da khoa:
  - retention command giam footprint cua bang operational integration
  - payload sanitize giam kich thuoc log va backup blast radius
  - schedule wrapper da bao ve revoke/prune command bang single-node lock

## Maintainability

- Danh gia: `Trung binh -> Tot`
- Da khoa:
  - them `IntegrationSecretRotationService` lam source of truth cho grace token
  - them `IntegrationOperationalPayloadSanitizer` de dong nhat payload governance
  - them regression suite tach rieng cho auth, settings concurrency, rotation va retention
- Residual note:
  - `IntegrationSettings` van la page lon; ve sau nen tach `health`, `runtime policy`, `secret rotation`

## Better Architecture Proposal

- Sau baseline, huong tot hon la:
  - tach `IntegrationSettings` thanh boundary UI nho hon
  - giu `IntegrationSecretRotationService` lam canonical workflow cho moi inbound shared secret moi
  - tiep tuc dua runbook/ops gate sang `OPS` de khong de `INT` ganh qua nhieu van hanh docs

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Tot`
- Web Lead, Google Calendar, EMR sync van giu outbox/idempotency pattern tot.
- Internal EMR mutation da co record scope boundary.
- Secret rotation da chuyen tu one-shot overwrite sang workflow co grace window + revoke.

## State transitions

- Danh gia: `Tot`
- `IntegrationSettings` save da co compare-and-swap theo revision.
- Secret rotation co 3 pha ro rang:
  - active secret moi duoc luu
  - old secret con hieu luc tam thoi trong grace window
  - grace secret bi revoke sau khi het han

## Missing business rules

- Da giam phan lon backlog baseline.
- Con mo o muc van hanh/chinh sach:
  - role delegated cho integration governance
  - SOP rollout secret rotation cho client ngoai

## Invalid states / forbidden transitions

- Da khoa:
  - manager sua secret/endpoint
  - amend clinical note ngoai scope branch
  - stale settings overwrite silent
  - token cu het grace van tiep tuc duoc chap nhan

## Service / action / transaction boundary

- Boundary hien tai da hop ly cho baseline:
  - `IntegrationSettings` save: transaction + revision + audit
  - `IntegrationSecretRotationService`: canonical rotation/revoke flow
  - `ValidateInternalEmrToken` / `ValidateWebLeadToken` / `ZaloWebhookController`: grace-aware validation
  - `PruneIntegrationOperationalDataCommand` + `RevokeRotatedIntegrationSecretsCommand`: operational cleanup boundary

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Da tot hon ro rang:
  - non-admin bi chan save surface nhay cam
  - co grace window banner khi token cu dang con hieu luc
  - recent logs hien duoc ly do va grace expiry metadata
- Van con friction:
  - `IntegrationSettings` van la mot page lon, chua tach theo muc do nhay cam

## Filament UX

- Danh gia: `Trung binh`
- Da them:
  - stale revision warning
  - grace window banner
  - audit note cho secret rotation
- Follow-up de dat UX production-grade hon:
  - tach page thanh `readiness`, `runtime`, `secret rotation`
  - them change-reason input tuong minh thay vi reason mac dinh

## Edge cases quan trong

- Da xu ly:
  - hai admin luu settings cung luc
  - token cu van can song song trong rollout
  - EMR internal mutation note ngoai scope
  - payload raw nhay cam bi giu qua lau
- Con lai sau baseline:
  - runbook rollout voi client ben ngoai
  - xac nhan retention exact policy voi doi van hanh/ops

## Diem de thao tac sai

- Giam manh so voi review dau tien.
- Phan con lai chu yeu la mat do thong tin qua lon tren mot page duy nhat.

## De xuat cai thien UX

1. Tach `IntegrationSettings` thanh 3 page/cluster nho hon.
2. Them `reason` input bat buoc khi rotate secret trong moi truong production.
3. Them filter provider/group trong bang `clinic_setting_logs` neu audit volume tang cao.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| INT-001 | Critical | Security | IntegrationSettings cho phep Manager sua secret va runtime endpoint | Resolved | Page auth da tach view/runtime/secrets va Manager chi con read-only. |
| INT-002 | Critical | Security | Internal EMR mutation chua branch-scope theo clinical note | Resolved | Middleware/request scope da khoa record/branch boundary. |
| INT-003 | High | Concurrency | Luu IntegrationSettings khong transactional va khong co optimistic lock | Resolved | Save da co cache lock + transaction + revision guard. |
| INT-004 | High | Security | Bang van hanh integration giu raw payload PII/PHI va chua co retention | Resolved | Payload da duoc sanitize/encrypt va co prune command. |
| INT-005 | Medium | Maintainability | Secret rotation la one-shot, khong co grace window hay rollback metadata | Resolved | Da co grace window, metadata audit va revoke command. |
| INT-006 | Medium | Maintainability | Coverage chua khoa auth matrix, settings concurrency va payload governance | Resolved | Regression suite INT da duoc mo rong va da xanh. |

# Dependencies

- GOV cho permission/page gate va service-account governance.
- APPT cho Google Calendar payload va appointment sync side-effects.
- CLIN cho internal EMR mutation va payload lam sang.
- ZNS cho Zalo webhook/runtime settings coupling.
- OPS cho retention/rotation SOP va rollout observability.

# Open Questions

- Co can tao role delegated `IntegrationAdmin` sau baseline hay giu `Admin-only` cho secret boundary?
- Grace window mac dinh 1440 phut co phu hop voi van hanh that hay can profile rieng theo provider?
- Co can tach audit log settings thanh page/report rieng neu volume van hanh tang them?

# Recommended Next Steps

1. Chay migrate tren moi truong that cho `2026_03_07_101231_harden_integration_operational_payload_governance.php` va `2026_03_07_102454_add_rotation_metadata_to_clinic_setting_logs_table.php`.
2. Smoke test token rollover voi Web Lead, Zalo webhook verify va EMR internal client that.
3. Mo review module `KPI` tiep theo de chot data lineage/reporting coupling sau khi `INT` da sach baseline.

# Current Status

- Clean Baseline Reached
