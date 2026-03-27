# Checklist UAT Theo Vai Trò

Tài liệu này dùng cho kiểm thử chấp nhận người dùng cuối theo vai trò vận hành thực tế.

## Mục tiêu

- Kiểm tra người dùng nhìn đúng màn hình, thao tác đúng flow, và không đi lạc sang surface ngoài quyền.
- Dùng được cho local demo, staging, hoặc môi trường nghiệm thu nội bộ.
- Bám role thực tế trong hệ thống: `CSKH`, `Doctor`, `Manager`, `Admin`.

## Điều kiện trước khi chạy

- Dữ liệu demo đã được reset:

```bash
php artisan migrate:fresh --seed
```

- Tài khoản mẫu:
  - xem [../getting-started/local-demo-users.md](../getting-started/local-demo-users.md)
- Với `Manager` và `Admin`:
  - cần MFA hoặc recovery code demo local
- Nếu đang test local:
  - ưu tiên dùng đúng account demo theo chi nhánh seeded

## Cách đánh dấu

- `[ ]` chưa test
- `[x]` pass
- `[!]` fail
- Nếu fail, ghi thêm:
  - bước lỗi
  - ảnh màn hình hoặc video
  - dữ liệu đầu vào
  - kết quả thực tế
  - kết quả mong đợi

## Tiêu chí pass chung

- Đăng nhập được bằng tài khoản đúng vai trò.
- Chỉ thấy menu và màn hình đúng phạm vi quyền.
- Dữ liệu hiển thị đúng chi nhánh và đúng hồ sơ seeded.
- Các action chính tạo hoặc cập nhật dữ liệu đúng một lần.
- Không có `403`, `500`, loading treo, form trắng, hoặc action silent fail trong hot path.

## 1) CSKH

Tài khoản gợi ý:
- `cskh.q1@demo.ident.test`

Màn hình chính cần dùng:
- `Frontdesk Control Center`
- `Khách hàng`
- `Bệnh nhân`
- `Lịch hẹn`
- `Chăm sóc khách hàng`

Checklist:

- [ ] Đăng nhập thành công và vào được `/admin`.
- [ ] Mở `Frontdesk Control Center` và thấy:
  - `Điều phối front-office`
  - `Lead pipeline`
  - `Pham Minh Chau`
  - `QA Appointment Base`
- [ ] Không thấy dữ liệu chi nhánh khác như `Le Van Nam`.
- [ ] Vào `Khách hàng`, tìm `Pham Minh Chau`, bấm `Xác nhận thành bệnh nhân`.
- [ ] Sau khi convert, mở được workspace bệnh nhân ngay từ link hồ sơ.
- [ ] Từ customer hoặc patient workspace, tạo được lịch hẹn mới.
- [ ] Mở được lịch hẹn vừa tạo để kiểm tra thông tin bác sĩ, chi nhánh, thời gian.
- [ ] Vào `Chăm sóc khách hàng` hoặc queue liên quan và thấy ticket seeded đúng chi nhánh.
- [ ] Không vào được các surface sau:
  - `Dashboard Tài chính`
  - `Thu/chi`
  - `Firewall IP`
  - `Audit logs`
  - `Cài đặt tích hợp`

Pass nếu:
- convert lead thành patient thành công
- tạo lịch hẹn thành công
- không nhìn thấy hoặc không mở được surface tài chính/hạ tầng

## 2) Doctor

Tài khoản gợi ý:
- `doctor.q1@demo.ident.test`

Màn hình chính cần dùng:
- `Delivery Ops Center`
- `Bệnh nhân`
- `Lịch hẹn`
- `Hồ sơ y tế`
- `Kế hoạch điều trị`

Checklist:

- [ ] Đăng nhập thành công và vào được `/admin`.
- [ ] Mở `Delivery Ops Center` và thấy:
  - `Điều phối điều trị`
  - `Workflow điều trị`
  - `Hồ sơ lâm sàng`
  - `QA Treatment Workflow Plan`
  - `QA Clinical Consent`
- [ ] Không thấy dữ liệu kho và labo ngoài phạm vi, ví dụ:
  - `QA Inventory Low Stock Composite`
  - `FO-QA-SUP-001`
- [ ] Vào `Bệnh nhân`, tìm patient seeded cùng chi nhánh.
- [ ] Mở tab `Kế hoạch điều trị` hoặc `exam-treatment` và thấy dữ liệu treatment plan seeded.
- [ ] Mở trang tạo `Hồ sơ y tế` từ hồ sơ bệnh nhân.
- [ ] Mở một lịch hẹn seeded của chi nhánh mình và thấy đúng thông tin patient.
- [ ] Thử cập nhật trạng thái một lịch tương lai thành `Hoàn thành` hoặc `Không đến`.
  - kỳ vọng: không cho thao tác hoặc không có lựa chọn đó
- [ ] Không vào được các surface sau:
  - `Dashboard Tài chính`
  - `Thu/chi`
  - `Firewall IP`
  - `Audit logs`
  - `Cài đặt tích hợp`

Pass nếu:
- xem và thao tác được patient/appointment/clinical workflow
- không bị lộ inventory/labo sai scope
- không thể thực hiện outcome status sai cho lịch tương lai

## 3) Manager

Tài khoản gợi ý:
- `manager.q1@demo.ident.test`

Màn hình chính cần dùng:
- `Dashboard Tài chính`
- `Thu/chi`
- `Operational KPI Pack`
- `Zalo ZNS`
- `Ops Control Center`
- `Frontdesk Control Center`

Checklist:

- [ ] Đăng nhập bằng MFA thành công.
- [ ] Vào `Dashboard Tài chính` và thấy đúng surface tài chính.
- [ ] Vào `Thu/chi` và thấy voucher seeded của chi nhánh mình như `PT-DEMO-Q1-001`.
- [ ] Vào `Operational KPI Pack` và thấy:
  - `KPI vận hành nha khoa`
  - `Ngày snapshot`
  - `Alert mở`
- [ ] Vào `Zalo ZNS` và thấy:
  - `Automation dead-letter`
  - `Campaign đang chạy`
- [ ] Vào `Ops Control Center` và thấy:
  - `Finance & collections`
  - `KPI freshness & alerts`
  - `ZNS triage cockpit`
  - `Role-limited overview`
- [ ] Không thấy dữ liệu governance admin-only như email seeded ẩn.
- [ ] Mở `Frontdesk Control Center` để xác nhận manager vẫn nhìn được dữ liệu front-office cùng chi nhánh.
- [ ] Không vào được:
  - `Firewall IP`
  - `User management` toàn quyền
  - `Audit logs` nếu chưa có quyền delegated riêng
  - `Cài đặt tích hợp` nếu dùng role manager chuẩn

Pass nếu:
- MFA hoạt động
- chỉ thấy dữ liệu branch của mình
- dùng được finance, KPI, ZNS, ops cockpit
- vẫn bị chặn ở surface admin-only

## 4) Admin

Tài khoản gợi ý:
- `admin@demo.ident.test`

Màn hình chính cần dùng:
- `Ops Control Center`
- `Cài đặt tích hợp`
- `Người dùng`
- `Audit logs`
- `Firewall IP`

Checklist:

- [ ] Đăng nhập bằng MFA thành công.
- [ ] Vào `Ops Control Center` và thấy:
  - `Trung tâm OPS`
  - `Finance & collections`
  - `Governance & audit scope`
  - `Readiness signoff fixture`
- [ ] Vào `Cài đặt tích hợp` và thấy các nhóm:
  - `Web Lead API`
  - `EMR`
  - `Zalo OA`
  - `ZNS`
  - `Google Calendar`
- [ ] Vào `Người dùng` và mở được user seeded governance.
- [ ] Vào `Audit logs` và thấy:
  - `Audit logs`
  - `Entity`
  - `Hành động`
  - `Người thực hiện`
- [ ] Vào `Firewall IP` và thấy:
  - `Tường Lửa IP`
  - `Thêm IP của tôi`
  - `Tạo mới`
- [ ] Mở được các create form rủi ro cao:
  - `Invoices / create`
  - `Treatment plans / create`
  - `Receipts expense / create`
  - `Factory orders / create`
- [ ] Xác nhận không có lỗi trắng trang, `500`, hoặc form render sai ở các trang trên.

Pass nếu:
- MFA hoạt động
- admin vào được toàn bộ surface control-plane nhạy cảm
- create forms lớn render bình thường

## 5) Checklist chéo sau khi test xong từng vai trò

- [ ] Không có hồ sơ bệnh nhân trùng được tạo ngoài ý muốn.
- [ ] Không có lịch hẹn trùng hoặc trạng thái sai sau UAT.
- [ ] Không có invoice, payment, receipt test bị ghi sai chi nhánh.
- [ ] Không có user vai trò thấp nhìn thấy menu/surface vượt quyền.
- [ ] Không có bước nào phải dùng link trực tiếp để đi tiếp flow chính.

## 6) Kết luận nghiệm thu

Ngày test:
- ................................

Môi trường:
- ................................

Người test:
- ................................

Kết quả:
- [ ] Pass toàn bộ
- [ ] Pass có điều kiện
- [ ] Fail

Ghi chú:
- ................................
