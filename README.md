# 🏥 CRM Nha Khoa (Laravel 12 + Filament 4)

Hệ thống CRM nha khoa đa chi nhánh, tập trung vào 4 trục nghiệp vụ chính:

- **Tăng trưởng**: web lead, chuyển đổi Customer → Patient, chăm sóc tái khám.
- **Lâm sàng**: khám, bệnh án, kế hoạch điều trị, odontogram, chỉ định cận lâm sàng.
- **Tài chính**: hóa đơn, thanh toán nhiều đợt, công nợ, trả góp, hoàn tiền có audit.
- **Vận hành**: RBAC, audit log, KPI, đồng bộ EMR, policy theo chi nhánh.

---

## Công nghệ

- PHP 8.4 / Laravel 12
- Filament 4 + Livewire 3 + Alpine
- Sanctum, Spatie Permission
- Pest 4 cho test tự động

---

## Phạm vi chức năng đang có trong hệ thống

## 1) CRM & Frontdesk
- Quản lý **Customer/Lead** theo nguồn và trạng thái.
- Chuyển đổi lead thành **Patient** với ràng buộc dữ liệu định danh.
- Lịch hẹn có chuẩn hóa trạng thái, kiểm soát overbooking theo policy chi nhánh.
- Ghi nhận tương tác/chăm sóc và workflow nhắc lịch.

## 2) Clinical / EMR
- Hồ sơ bệnh nhân + bệnh án lâm sàng theo mốc thời gian.
- Form khám và chỉ định (hỗ trợ upload minh chứng).
- Sơ đồ răng và tình trạng răng theo danh mục chuẩn.
- Kế hoạch điều trị, vòng đời phê duyệt item, theo dõi tiến độ thực hiện.
- Visit episode để gom phiên khám/điều trị theo đợt.

## 3) Billing / Finance
- Invoice state machine + kiểm soát idempotency khi ghi nhận payment.
- Payment đa phương thức (bao gồm VNPay), reversal có log và truy vết.
- Installment plan, nhắc kỳ trả, phân bổ theo chi nhánh.
- Sổ thu/chi và đối soát theo branch context.

## 4) Platform / Governance
- RBAC chi tiết theo action, có baseline & test guard.
- Audit log theo sự kiện quan trọng (lâm sàng, tài chính, vận hành).
- Snapshot báo cáo có lineage/versioning.
- Đồng bộ EMR qua event/log/map, có pipeline theo dõi sức khỏe.
- Cấu hình runtime theo phòng khám/chi nhánh (branding, web-lead realtime,...).

---

## Tài liệu nghiệp vụ nên đọc theo thứ tự

1. `docs/DENTAL_CRM_SPECIFICATION.md` – đặc tả tổng thể.
2. `docs/GAP_ANALYSIS.md` – khoảng cách giữa đặc tả và hiện trạng.
3. `docs/IMPLEMENTATION_SPRINT_BACKLOG.md` – backlog triển khai theo sprint.
4. `docs/PM_DENTAL_FLOW_BACKLOG.md` – backlog PM chi tiết theo luồng.
5. `DATABASE_SCHEMA.md` – bản đồ schema theo domain (living doc).

---

## Chạy dự án local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

> Nếu giao diện chưa phản ánh thay đổi frontend, chạy lại `npm run dev` hoặc `npm run build`.

---

## Testing nhanh

```bash
php artisan test
```

Có thể chạy theo file để tối ưu thời gian:

```bash
php artisan test tests/Feature/<TenFileTest>.php
```
