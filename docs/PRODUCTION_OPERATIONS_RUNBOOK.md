# Production Operations Runbook

Cap nhat: 2026-03-07

## 1. Muc tieu

- Cung cap runbook van hanh production sau khi toan bo 13 module da dat `Clean Baseline Reached`.
- Chot mot nguon thao tac duy nhat cho:
  - migrate
  - production seeding
  - baseline assert/sync
  - backup/restore/readiness verification
  - smoke test sau deploy

## 2. Source of truth

- Master index: `docs/reviews/00-master-index.md`
- OPS review: `docs/reviews/modules/OPS-production-readiness.md`
- OPS issues: `docs/issues/OPS-issues.md`
- OPS plan: `docs/planning/OPS-plan.md`
- Local demo accounts: `docs/LOCAL_DEMO_USERS.md`

## 3. Quick deploy checklist

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\ProductionMasterDataSeeder --force
php artisan security:assert-governance-resource-baseline --no-interaction
php artisan security:assert-action-permission-baseline --no-interaction
php artisan ops:create-backup-artifact --strict --no-interaction
php artisan ops:run-release-gates --profile=production --with-finance --no-interaction
php artisan ops:run-production-readiness --with-finance --report=storage/app/release-readiness/production-readiness.json --no-interaction
```

## 4. Nguyen tac van hanh

- Release gate la `verify-only`. Khong coi release gate la lane tu sua he thong.
- Production khong duoc chay `migrate:fresh --seed`.
- Production chi duoc seed master data an toan:
  - `Database\\Seeders\\ProductionMasterDataSeeder`
- `DatabaseSeeder` chi dung cho `local` va `testing`.
- Neu baseline permission tren DB that bi drift, phai chay `--sync` co y thuc, sau do assert lai.

## 5. Lenh production bat buoc

### 5.1 Deploy code

```bash
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

### 5.2 Migrate schema

```bash
php artisan migrate --force
```

### 5.3 Seed master data an toan

```bash
php artisan db:seed --class=Database\\Seeders\\ProductionMasterDataSeeder --force
```

Khong chay lenh sau tren production:

```bash
php artisan migrate:fresh --seed
php artisan db:seed
```

## 6. Governance baseline va drift check

### 6.1 Assert baseline

```bash
php artisan security:assert-governance-resource-baseline --no-interaction
php artisan security:assert-action-permission-baseline --no-interaction
```

### 6.2 Neu baseline drift tren DB cu

Chay explicit remediation lane:

```bash
php artisan security:assert-governance-resource-baseline --sync --no-interaction
php artisan security:assert-action-permission-baseline --sync --no-interaction
```

Sau do assert lai:

```bash
php artisan security:assert-governance-resource-baseline --no-interaction
php artisan security:assert-action-permission-baseline --no-interaction
```

## 7. OPS control-plane verification

### 7.1 Backup artifact

```bash
php artisan ops:create-backup-artifact --strict --no-interaction
php artisan ops:check-backup-health --strict --no-interaction
```

### 7.2 Restore drill

```bash
php artisan ops:run-restore-drill --strict --no-interaction
```

### 7.3 Release gates

```bash
php artisan ops:run-release-gates --profile=production --with-finance --no-interaction
```

Neu can preview gate truoc:

```bash
php artisan ops:run-release-gates --profile=production --with-finance --dry-run --no-interaction
```

### 7.4 Production readiness report

```bash
php artisan ops:run-production-readiness \
  --with-finance \
  --report=storage/app/release-readiness/production-readiness.json \
  --no-interaction
```

Neu can dry-run checklist:

```bash
php artisan ops:run-production-readiness \
  --with-finance \
  --dry-run \
  --no-interaction
```

### 7.5 QA / PM signoff

```bash
php artisan ops:verify-production-readiness-report \
  storage/app/release-readiness/production-readiness.json \
  --qa=<qa-email-noi-bo> \
  --pm=<pm-email-noi-bo> \
  --release-ref=<release-ticket> \
  --strict \
  --output=storage/app/release-readiness/production-readiness-signed.json \
  --no-interaction
```

## 8. Post-deploy runtime reset

```bash
php artisan queue:restart
php artisan schedule:clear-cache
php artisan optimize:clear
```

Neu co cache production rieng:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 9. Smoke test theo module

### 9.1 GOV

- Admin vao Filament va xac nhan:
  - `Manager` khong con CRUD `User`, `Role`, `Branch`
  - audit log chi hien cho actor hop le

### 9.2 PAT / APPT / CLIN / TRT

- Tao hoac sua 1 patient.
- Tao 1 lich hen.
- Mo workspace benh nhan va xac nhan doctor/staff selector dung branch scope.
- Ghi 1 clinical/treatment update co audit trail.

### 9.3 FIN / INV / SUP

- Tao 1 invoice va 1 payment.
- Xac nhan stock mutation va batch traceability khong loi.
- Tao 1 factory order va kiem tra supplier/report datasource.

### 9.4 CARE / ZNS / INT / KPI

- Mo page van hanh CSKH, ZNS, Integration Settings, KPI.
- Chay smoke command:

```bash
php artisan zns:prune-operational-data --no-interaction
php artisan integrations:prune-operational-data --no-interaction
php artisan reports:check-snapshot-sla --no-interaction
php artisan ops:check-observability-health --strict --no-interaction
```

## 10. Migration / rollout can luu y

- `patients` PII hardening migration da duoc sua cho MySQL fresh install:
  - legacy index tren `patients.phone` va `patients.email` se duoc drop truoc khi doi column sang `text`
- Seeder da tach:
  - production: `ProductionMasterDataSeeder`
  - local/testing: `DatabaseSeeder` + `LocalDemoDataSeeder`
- Neu can reset local hoac staging:

```bash
php artisan migrate:fresh --seed
```

Lenh tren se reset toan bo DB va chi duoc dung ngoai production.

### 10.1 Demo / staging bootstrap login cho Admin va Manager

Chi ap dung cho demo site hoac staging can login nhanh bang account seed. Khong ap dung cho production that.

Them vao `.env`:

```env
CARE_SECURITY_ALLOW_BOOTSTRAP_WITHOUT_MFA=true
CARE_SECURITY_SEED_DEMO_MFA=false
```

Sau do chay:

```bash
php artisan optimize:clear
php artisan db:seed --class=Database\\Seeders\\LocalDemoDataSeeder --force
```

Luu y:

- `DatabaseSeeder` khong tu dong goi `LocalDemoDataSeeder` tren environment production-like.
- Bootstrap bypass chi mo khi chua co `Admin` hoac `Manager` nao da cau hinh MFA/passkey.
- Neu can seed demo MFA deterministic de QA test flow 2FA, dat `CARE_SECURITY_SEED_DEMO_MFA=true` roi reseed lai `LocalDemoDataSeeder`.
- Tuyet doi khong bat `CARE_SECURITY_ALLOW_BOOTSTRAP_WITHOUT_MFA=true` tren production that.

## 11. Tieu chi pass / fail

### Pass

- `migrate --force` thanh cong
- production master-data seed thanh cong
- 2 lenh baseline assert pass
- backup artifact, backup health, restore drill pass
- release gates production pass
- production readiness report pass va duoc signer hop le ky
- smoke test module chinh khong co blocker

### Fail va dung rollout neu

- con pending migration
- baseline permission fail va chua duoc sync explicit
- backup health hoac restore drill fail
- release gates fail
- signoff khong map duoc signer noi bo hop le
- smoke test nghiep vu loi tren patient / appointment / payment / stock / zns

## 12. Rollback notes

- Rollback code chi duoc thuc hien sau khi:
  - xac nhan backup artifact moi nhat healthy
  - restore drill pass
  - danh gia migration co destructive hay khong
- Neu rollback schema khong an toan, uu tien:
  - maintenance mode
  - restore tu artifact backup da verify
  - chay lai smoke test toi thieu sau restore

## 13. Lich van hanh de xuat

- Truoc deploy:
  - preview release gate `--dry-run`
  - verify baseline
- Trong deploy:
  - migrate
  - production master-data seed
  - release gates
  - readiness report
  - signoff
- Sau deploy:
  - queue/schedule reset
  - smoke test
  - theo doi logs, failed jobs, backup windows, observability budget trong 24h dau
