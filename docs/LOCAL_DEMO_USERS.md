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

## Du lieu nghiep vu demo co san

### Benh nhan mau

| Ho ten | So dien thoai | Chi nhanh | Muc dich demo |
| --- | --- | --- | --- |
| `Nguyen Hoang Minh` | `0909123001` | `HCM-Q1` | Tu van implant, co lich hen confirmed, invoice partial, ZNS recall completed |
| `Tran Bao Chau` | `0909123002` | `HCM-Q1` | Dieu tri rang su, co lich completed, invoice paid, factory order dang ordered |
| `Le Gia Han` | `0912123004` | `HN-CG` | Demo doctor da chi nhanh tai Cau Giay, co branch transfer log |
| `Pham Quynh Nhu` | `0912123005` | `HN-CG` | No-show recovery / reschedule / invoice overdue / ZNS scheduled |
| `Vo Minh Anh` | `0935123007` | `DN-HC` | No-show case cho luong CSKH goi lai |
| `Do Quoc Tuan` | `0935123008` | `DN-HC` | Ca implant / labo urgent / lich hen cancelled |

### Lich hen demo

| Patient phone | Trang thai | Loai | Phan loai | Ghi chu |
| --- | --- | --- | --- | --- |
| `0909123001` | `confirmed` | `consultation` | `booking` | Tu van implant Quan 1, da confirm qua Zalo |
| `0909123002` | `completed` | `treatment` | `booking` | Dieu tri rang su da hoan tat |
| `0912123004` | `scheduled` | `follow_up` | `re_exam` | Demo `doctor.float` phuc vu tai `HN-CG` |
| `0912123005` | `rescheduled` | `consultation` | `booking` | Khach doi lich vi ban hop cong ty |
| `0935123007` | `no_show` | `consultation` | `booking` | Dau vao cho queue CSKH no-show recovery |
| `0935123008` | `cancelled` | `treatment` | `booking` | Huy do chua du phim/xet nghiem |

### Care note demo

- Co ticket `no_show_recovery` cho patient `0935123007`
- Co ticket follow-up cho patient `0912123005`
- Co note lien ket customer/patient/appointment de demo customer care queue

### Tai chinh demo

| Invoice no | Patient phone | Trang thai | Tinh huong |
| --- | --- | --- | --- |
| `INV-DEMO-Q1-001` | `0909123001` | `partial` | Dat coc implant dot 1 |
| `INV-DEMO-Q1-002` | `0909123002` | `paid` | Thanh toan du rang su |
| `INV-DEMO-CG-001` | `0912123005` | `overdue` | Cong no qua han chua thu |
| `INV-DEMO-HC-001` | `0935123008` | `issued` | Hoa don da xuat, chua thanh toan |

### Labo demo

| Order no | Patient phone | Trang thai | Nha cung cap | Ghi chu |
| --- | --- | --- | --- | --- |
| `FO-DEMO-Q1-001` | `0909123002` | `ordered` | `SGDS` | Rang su zirconia Quan 1 |
| `FO-DEMO-HC-001` | `0935123008` | `in_progress` | `MEDIDN` | Implant urgent Hai Chau |

### ZNS demo

| Campaign code | Trang thai | Doi tuong | Ghi chu |
| --- | --- | --- | --- |
| `ZNS-DEMO-Q1-RECALL` | `completed` | Patient `0909123001` | Recall implant da gui thanh cong |
| `ZNS-DEMO-CG-NOSHOW` | `scheduled` | Patient `0912123005` | No-show recovery dang cho xu ly |

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
- Dung patient `0935123007` de demo no-show recovery
- Dung patient `0912123005` de demo callback va lich doi

### Demo bac si

- Login bang `doctor.cg@demo.nhakhoaanphuc.test`
- Mo patient workspace, clinical/treatment tab va lich hen cua `HN-CG`

### Demo finance va labo

- Login bang `manager.q1@demo.nhakhoaanphuc.test`
- Mo `INV-DEMO-Q1-001`, `INV-DEMO-Q1-002`, `FO-DEMO-Q1-001`
- Login bang `manager.hc@demo.nhakhoaanphuc.test`
- Mo `INV-DEMO-HC-001`, `FO-DEMO-HC-001`

### Demo ZNS

- Login bang `admin@demo.nhakhoaanphuc.test`
- Mo campaign `ZNS-DEMO-Q1-RECALL` va `ZNS-DEMO-CG-NOSHOW`
- Kiem tra relation deliveries va triage page

## Luu y

- Day la bo account demo co dinh. Khong dung email nay tren production.
- Neu can doi mat khau demo, cap nhat dong thoi:
  - `Database\\Seeders\\LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD`
  - tai lieu nay
