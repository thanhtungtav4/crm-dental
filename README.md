# IDENT Dental CRM

CRM nha khoa da chi nhanh duoc xay bang Laravel 12, Filament 4, Livewire 3.
He thong tap trung vao van hanh phong kham thuc te: lead/customer, patient/EMR, lich hen, dieu tri, tai chinh, kho, tich hop, governance, va release safety.

## Tai lieu chinh

- [Documentation Hub](docs/README.md)
- [Module Inventory](docs/modules/README.md)
- [User Guides](docs/user-guides/README.md)
- [Production Runbook](docs/operations/production-operations-runbook.md)
- [Review Master Index](docs/reviews/00-master-index.md)
- [Review Pipeline](docs/reviews/README.md)

## Pham vi nghiep vu

- Frontdesk / CSKH: lead, customer, appointment, care queue
- Patient / EMR: patient profile, medical records, clinical notes, consent, media
- Treatment: plans, sessions, prescriptions, materials usage
- Finance: invoices, payments, wallets, receipts/expense, installments, insurance
- Inventory / Suppliers: materials, batches, issue notes, factory orders
- Integrations / Ops: web lead, Zalo/ZNS, Google Calendar, EMR bridge, backup, readiness, KPI

## Tech stack

- PHP 8.4
- Laravel 12
- Filament 4
- Livewire 3
- MySQL
- Tailwind CSS 4
- Pest 4

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Neu can bo account demo va scenario local, xem [Local Demo Users](docs/getting-started/local-demo-users.md).

## Lam viec an toan

- Khong push thang len `main` neu chua chot batch thay doi.
- Repo nay co the auto-deploy sau khi push, nen moi batch can nho, ro pham vi, va co rollback path.
- Uu tien review, backlog, docs, va low-risk refactor truoc khi sua sau.

## Source of truth

- Tai lieu tong: [docs/README.md](docs/README.md)
- Module map: [docs/modules/README.md](docs/modules/README.md)
- Danh gia module: [docs/reviews/00-master-index.md](docs/reviews/00-master-index.md)
- Van hanh production: [docs/operations/production-operations-runbook.md](docs/operations/production-operations-runbook.md)
- Product spec va gap analysis: [docs/product/README.md](docs/product/README.md)
- Roadmap va backlog: [docs/roadmap/README.md](docs/roadmap/README.md)
