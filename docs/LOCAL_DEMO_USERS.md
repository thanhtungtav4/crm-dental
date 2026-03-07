# Local Demo Users

Cap nhat: 2026-03-07

## Muc tieu

- Chot bo user demo co dinh cho local va testing.
- Dung de demo theo role, theo chi nhanh, va demo branch scope.
- Khong ap dung cho production.

## Seed source

- `Database\\Seeders\\DatabaseSeeder`
- `Database\\Seeders\\LocalDemoDataSeeder`

Bo user nay chi duoc seed khi environment la `local` hoac `testing`.

## Cach tao du lieu demo

```bash
php artisan migrate:fresh --seed
```

Hoac neu chi can bo demo user tren schema hien co:

```bash
php artisan db:seed --class=Database\\Seeders\\LocalDemoDataSeeder --force
```

## Mat khau chung

- Tat ca account demo dung chung mat khau: `Demo@123456`

## Danh sach account demo

| Email | Role | Chi nhanh mac dinh | Muc dich demo |
| --- | --- | --- | --- |
| `admin@demo.nhakhoaanphuc.test` | `Admin` | `HCM-Q1` | Quan tri toan he thong, review settings, audit, KPI |
| `automation.bot@demo.nhakhoaanphuc.test` | `AutomationService` | `none` | Service account cho scheduler / OPS control-plane, khong vao Filament panel |
| `manager.q1@demo.nhakhoaanphuc.test` | `Manager` | `HCM-Q1` | Demo quan ly co so Quan 1 |
| `manager.cg@demo.nhakhoaanphuc.test` | `Manager` | `HN-CG` | Demo quan ly co so Cau Giay |
| `manager.hc@demo.nhakhoaanphuc.test` | `Manager` | `DN-HC` | Demo quan ly co so Hai Chau |
| `doctor.q1@demo.nhakhoaanphuc.test` | `Doctor` | `HCM-Q1` | Demo luong kham / dieu tri / lich hen Quan 1 |
| `doctor.cg@demo.nhakhoaanphuc.test` | `Doctor` | `HN-CG` | Demo luong kham / dieu tri / lich hen Cau Giay |
| `doctor.hc@demo.nhakhoaanphuc.test` | `Doctor` | `DN-HC` | Demo luong kham / dieu tri / lich hen Hai Chau |
| `doctor.float@demo.nhakhoaanphuc.test` | `Doctor` | `HCM-Q1` | Bac si da chi nhanh, co branch assignment tai `HN-CG` de demo branch scope |
| `cskh.q1@demo.nhakhoaanphuc.test` | `CSKH` | `HCM-Q1` | Demo lead/care queue Quan 1 |
| `cskh.cg@demo.nhakhoaanphuc.test` | `CSKH` | `HN-CG` | Demo lead/care queue Cau Giay |
| `cskh.hc@demo.nhakhoaanphuc.test` | `CSKH` | `DN-HC` | Demo lead/care queue Hai Chau |

## Ghi chu demo nhanh

- `doctor.float@demo.nhakhoaanphuc.test`
  - branch mac dinh: `HCM-Q1`
  - branch assignment bo sung: `HN-CG`
  - dung account nay de demo doctor selector, scheduling, branch-scoped visibility

- `automation.bot@demo.nhakhoaanphuc.test`
  - la service account, khong dung de login Filament panel
  - dung de test command, scheduler, va OPS actor boundary neu can

## Luong demo de xuat

### Demo governance

- Login bang `admin@demo.nhakhoaanphuc.test`
- Xac nhan `Manager` khong CRUD `User`, `Role`, `Branch`

### Demo branch scope

- Login bang `manager.q1@demo.nhakhoaanphuc.test`
- Mo patient/appointment tai `HCM-Q1`
- Doi sang `doctor.float@demo.nhakhoaanphuc.test` de demo doctor co the lam viec tai nhieu chi nhanh

### Demo CSKH

- Login bang `cskh.q1@demo.nhakhoaanphuc.test`
- Xem customer care queue, recall va birthday automation

### Demo bac si

- Login bang `doctor.cg@demo.nhakhoaanphuc.test`
- Mo patient workspace, clinical/treatment tab va lich hen cua `HN-CG`

## Luu y

- Day la bo account demo co dinh. Khong dung email nay tren production.
- Neu can doi mat khau demo, cap nhat dong thoi:
  - `Database\\Seeders\\LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD`
  - tai lieu nay
