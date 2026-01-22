🏗️ Tổng quan hệ thống — Phần mềm Quản lý Nha Khoa (Dental CRM)

Dự án được xây dựng bằng Laravel 12 + Filament 4, hướng tới đa chi nhánh, có phân quyền (Admin, Quản lý, Bác sĩ, Lễ tân).
Mục tiêu: quản lý khách hàng, bệnh nhân, kế hoạch điều trị, vật tư, hóa đơn, lịch hẹn, và ghi chú CSKH.

## ⚙️ Công nghệ

| Thành phần   | Mô tả |
|--------------|------|
| Framework    | Laravel 12.x |
| Admin Panel  | Filament v4 |
| Database     | MySQL |
| Authentication | Laravel Breeze / Filament Auth |
| Soft Deletes | Dùng cho các bảng chính |
| Encryption   | Mã hóa dữ liệu nhạy cảm trong bảng patients, treatment_plans |

## 🧩 1. Kiến trúc tổng quan

```
+----------------------+
|     Admin Panel      |  ← Filament v4 (UI CRUD)
+----------------------+
          |
          v
+----------------------+
|  Application Layer   |
| (Models, Policies,   |
|  Validation, Events) |
+----------------------+
          |
          v
+----------------------+
|   Database Layer     |
| (MySQL - Migrations, |
|  Relationships)      |
+----------------------+
```

## 🧠 2. Luồng nghiệp vụ chính (Flow)

### 🏁 1. Lễ tân tiếp nhận & xác nhận khách hàng

- Lễ tân tạo Customer (thông tin ban đầu).
- Trạng thái: `lead`, `contacted`, `confirmed`, `converted`, `lost`.
- Khi lễ tân xác nhận điều trị → dùng hành động “Xác nhận thành bệnh nhân” để chuyển đổi sang Patient (auto tạo record trong `patients`, cập nhật status `converted`).
- Mã bệnh nhân được sinh tự động và không cho chỉnh sửa trong form.

### 🧑‍⚕️ 2. Bác sĩ / Quản lý tạo Kế hoạch điều trị

- Một patient có thể có nhiều `treatment_plans`.
- Mỗi kế hoạch gồm nhiều `treatment_sessions` (các buổi điều trị).
- Mỗi buổi có thể sử dụng nhiều `materials` (qua bảng `treatment_materials`).

### 💊 3. Quản lý vật tư

- `materials`: danh mục vật tư nha khoa.
- `treatment_materials`: ghi nhận vật tư đã dùng trong từng session → để trừ kho & tính chi phí.

### 💰 4. Thanh toán & hóa đơn

- `invoices`: hóa đơn tổng cho 1 kế hoạch điều trị.
- `payments`: từng lần thanh toán (nhiều payment / invoice).
- Có thể theo dõi công nợ bệnh nhân.

### 🕓 5. Lịch hẹn & điều trị

- `treatment_sessions` lưu ngày hẹn, bác sĩ phụ trách, chi nhánh.
- Có thể dùng `appointments` nếu muốn mở rộng tính năng đặt lịch riêng biệt.

### 🏢 6. Đa chi nhánh

- Mỗi user thuộc 1 branch.
- Mỗi customer, patient, treatment_plan, session, invoice đều ghi lại `branch_id`.
- `branch_logs` lưu lịch sử di chuyển bệnh nhân giữa các chi nhánh.

### 🧾 7. CSKH & hành vi khách hàng

- `notes` ghi chú hành vi: khó tính, nhạy cảm, cần chăm sóc riêng.
- Hiển thị trong hồ sơ khách hàng cho lễ tân & CSKH.

## 🗃️ 3. Thiết kế cơ sở dữ liệu (tóm tắt chính)

| Bảng | Mục đích | Liên kết chính |
|------|----------|-----------------|
| users | Quản lý tài khoản nhân sự | belongsTo(branch) |
| branches | Chi nhánh | hasMany(users/customers/patients) |
| customers | Khách hàng tiềm năng | hasOne(patient) |
| patients | Bệnh nhân chính thức | belongsTo(customer) |
| treatment_plans | Kế hoạch điều trị | belongsTo(patient) |
| treatment_sessions | Các buổi điều trị | belongsTo(treatment_plan) |
| materials | Danh mục vật tư | hasMany(treatment_materials) |
| treatment_materials | Liên kết session ↔ vật tư | belongsTo(session/material) |
| invoices | Hóa đơn điều trị | belongsTo(treatment_plan) |
| payments | Lịch sử thanh toán | belongsTo(invoice) |
| notes | Ghi chú hành vi khách hàng | belongsTo(customer/patient) |
| branch_logs | Lịch sử di chuyển chi nhánh | belongsTo(patient, branch) |

## 💡 4. Filament Resources (chuẩn v4)

| Resource | Namespace | Chức năng |
|----------|-----------|-----------|
| BranchResource | App\\Filament\\Resources\\Branches | CRUD chi nhánh |
| UserResource | App\\Filament\\Resources\\Users | CRUD người dùng (quản lý phân quyền) |
| CustomerResource | App\\Filament\\Resources\\Customers | CRUD khách hàng tiềm năng |
| PatientResource | App\\Filament\\Resources\\Patients | Hồ sơ bệnh nhân |
| TreatmentPlanResource | App\\Filament\\Resources\\TreatmentPlans | Kế hoạch điều trị |
| TreatmentSessionResource | App\\Filament\\Resources\\TreatmentSessions | Buổi điều trị cụ thể |
| MaterialResource | App\\Filament\\Resources\\Materials | Quản lý vật tư |
| InvoiceResource | App\\Filament\\Resources\\Invoices | Quản lý hóa đơn |
| PaymentResource | App\\Filament\\Resources\\Payments | Ghi nhận thanh toán |
| NoteResource | App\\Filament\\Resources\\Notes | Ghi chú hành vi khách hàng |

## 🧭 5. Định hướng mở rộng

- Dashboard tổng quan chi nhánh (doanh thu, số bệnh nhân, vật tư dùng).
- Tự động nhắc lịch hẹn (qua Zalo/Email).
- Mã hóa thông tin bệnh nhân bằng Laravel Encryption.
- Quản lý tồn kho vật tư và cảnh báo gần hết.
- Phân quyền chi nhánh (users chỉ xem dữ liệu của chi nhánh họ).
- Ghi lịch sử thao tác (Activity Logs) cho audit.

---

🧠 Gợi ý cho AI nội bộ hiểu nhanh:

```
System context:
This project is a multi-branch Dental CRM built with Laravel 12 + Filament 4.
Entities: users, branches, customers, patients, treatment_plans, sessions, materials, invoices, payments, notes, branch_logs.
Receptionists create customers → converted to patients → treatment plans → sessions → invoices/payments.
Each branch is isolated but patients can move between branches (branch_logs).
The Filament resources follow v4 architecture with Schemas/ and Tables/ folders.
Continue generating consistent Filament resources, migration, and relationships.
```
🏗️ Tổng quan hệ thống — Phần mềm Quản lý Nha Khoa (Dental CRM)

Dự án được xây dựng bằng Laravel 12 + Filament 4, hướng tới đa chi nhánh, có phân quyền (Admin, Quản lý, Bác sĩ, Lễ tân).
Mục tiêu: quản lý khách hàng, bệnh nhân, kế hoạch điều trị, vật tư, hóa đơn, lịch hẹn, và ghi chú CSKH.

⚙️ Công nghệ
Thành phần	Mô tả
Framework	Laravel 12.x
Admin Panel	Filament v4
Database	MySQL
Authentication	Laravel Breeze / Filament Auth
Soft Deletes	Dùng cho các bảng chính
Encryption	Mã hóa dữ liệu nhạy cảm trong bảng patients, treatment_plans
🧩 1. Kiến trúc tổng quan
+----------------------+
|     Admin Panel      |  ← Filament v4 (UI CRUD)
+----------------------+
          |
          v
+----------------------+
|  Application Layer   |
| (Models, Policies,   |
|  Validation, Events) |
+----------------------+
          |
          v
+----------------------+
|   Database Layer     |
| (MySQL - Migrations, |
|  Relationships)      |
+----------------------+

🧠 2. Luồng nghiệp vụ chính (Flow)
🏁 1. Lễ tân tiếp nhận khách hàng mới

Lễ tân tạo Customer (thông tin ban đầu).

Trạng thái: lead, contacted, confirmed, converted, lost.

Khi khách xác nhận điều trị → chuyển đổi sang Patient (tạo record trong bảng patients liên kết với customers).

🧑‍⚕️ 2. Bác sĩ / Quản lý tạo Kế hoạch điều trị

Một patient có thể có nhiều treatment_plans.

Mỗi kế hoạch gồm nhiều treatment_sessions (các buổi điều trị).

Mỗi buổi có thể sử dụng nhiều materials (qua bảng treatment_materials).

💊 3. Quản lý vật tư

materials: danh mục vật tư nha khoa.

treatment_materials: ghi nhận vật tư đã dùng trong từng session → để trừ kho & tính chi phí.

💰 4. Thanh toán & hóa đơn

invoices: hóa đơn tổng cho 1 kế hoạch điều trị.

payments: từng lần thanh toán (nhiều payment / invoice).

Có thể theo dõi công nợ bệnh nhân.

🕓 5. Lịch hẹn & điều trị

treatment_sessions lưu ngày hẹn, bác sĩ phụ trách, chi nhánh.

Có thể dùng appointments nếu muốn mở rộng tính năng đặt lịch riêng biệt.

🏢 6. Đa chi nhánh

Mỗi user thuộc 1 branch.

Mỗi customer, patient, treatment_plan, session, invoice đều ghi lại branch_id.

branch_logs lưu lịch sử di chuyển bệnh nhân giữa các chi nhánh.

🧾 7. CSKH & hành vi khách hàng

notes ghi chú hành vi: khó tính, nhạy cảm, cần chăm sóc riêng.

Hiển thị trong hồ sơ khách hàng cho lễ tân & CSKH.

🗃️ 3. Thiết kế cơ sở dữ liệu (tóm tắt chính)
Bảng	Mục đích	Liên kết chính
users	Quản lý tài khoản nhân sự	belongsTo(branch)
branches	Chi nhánh	hasMany(users/customers/patients)
customers	Khách hàng tiềm năng	hasOne(patient)
patients	Bệnh nhân chính thức	belongsTo(customer)
treatment_plans	Kế hoạch điều trị	belongsTo(patient)
treatment_sessions	Các buổi điều trị	belongsTo(treatment_plan)
materials	Danh mục vật tư	hasMany(treatment_materials)
treatment_materials	Liên kết session ↔ vật tư	belongsTo(session/material)
invoices	Hóa đơn điều trị	belongsTo(treatment_plan)
payments	Lịch sử thanh toán	belongsTo(invoice)
notes	Ghi chú hành vi khách hàng	belongsTo(customer/patient)
branch_logs	Lịch sử di chuyển chi nhánh	belongsTo(patient, branch)
💡 4. Filament Resources (chuẩn v4)
Resource	Namespace	Chức năng
BranchResource	App\Filament\Resources\Branches	CRUD chi nhánh
UserResource	App\Filament\Resources\Users	CRUD người dùng (quản lý phân quyền)
CustomerResource	App\Filament\Resources\Customers	CRUD khách hàng tiềm năng
PatientResource	App\Filament\Resources\Patients	Hồ sơ bệnh nhân
TreatmentPlanResource	App\Filament\Resources\TreatmentPlans	Kế hoạch điều trị
TreatmentSessionResource	App\Filament\Resources\TreatmentSessions	Buổi điều trị cụ thể
MaterialResource	App\Filament\Resources\Materials	Quản lý vật tư
InvoiceResource	App\Filament\Resources\Invoices	Quản lý hóa đơn
PaymentResource	App\Filament\Resources\Payments	Ghi nhận thanh toán
NoteResource	App\Filament\Resources\Notes	Ghi chú hành vi khách hàng
🧭 5. Định hướng mở rộng

Dashboard tổng quan chi nhánh (doanh thu, số bệnh nhân, vật tư dùng).

Tự động nhắc lịch hẹn (qua Zalo/Email).

Mã hóa thông tin bệnh nhân bằng Laravel Encryption.

Quản lý tồn kho vật tư và cảnh báo gần hết.

Phân quyền chi nhánh (users chỉ xem dữ liệu của chi nhánh họ).

Ghi lịch sử thao tác (Activity Logs) cho audit.

🧠 Gợi ý cho AI nội bộ hiểu nhanh:

Bạn có thể chèn đoạn tóm tắt này vào prompt đầu khi mở project trong editor, ví dụ:

💬 System context:
This project is a multi-branch Dental CRM built with Laravel 12 + Filament 4.
Entities: users, branches, customers, patients, treatment_plans, sessions, materials, invoices, payments, notes, branch_logs.
Receptionists create customers → converted to patients → treatment plans → sessions → invoices/payments.
Each branch is isolated but patients can move between branches (branch_logs).
The Filament resources follow v4 architecture with Schemas/ and Tables/ folders.
Continue generating consistent Filament resources, migration, and relationships.


✅ Quản lý vai trò (Role) và quyền (Permission) linh hoạt.

✅ Mỗi user chỉ nhìn thấy dữ liệu thuộc chi nhánh của họ (branch isolation).

✅ Hỗ trợ dễ dàng gán quyền trong Filament (ẩn menu, nút, record theo role).

✅ Tương thích Laravel 12 + Filament v4.

✅ Có thể mở rộng về sau (ví dụ: quyền tùy chỉnh từng module).

⚙️ Giải pháp đề xuất:
🥇 Spatie Laravel Permission + Filament Shield

Đây là combo chuẩn nhất hiện nay, tương thích 100% với Filament 4 và được Filament team khuyên dùng.

🧩 1️⃣ Cài đặt
composer require spatie/laravel-permission
composer require bezhansalleh/filament-shield

Vai trò	Quyền hạn chính
Admin	Toàn quyền truy cập Filament
Doctor	Quản lý bệnh nhân & kế hoạch điều trị
Receptionist	Tạo khách hàng mới, xem lịch hẹn
Staff	Chỉ xem, không chỉnh sửa dữ liệu nhạy cảm


🧩 Quy trình tổng thể Bệnh án điện tử (EHR Flow)
1️⃣ Khách hàng tiềm năng (Customer)

Được lễ tân tạo đầu tiên khi khách liên hệ qua điện thoại, Facebook, Zalo, v.v.

Trạng thái: lead → contacted → confirmed → converted

Khi khách xác nhận điều trị → chuyển sang Patient.

2️⃣ Bệnh nhân (Patient)

Sinh mã bệnh nhân duy nhất (patient_code)

Có thể thuộc 1 hoặc nhiều chi nhánh (qua bảng branch_logs)

Có các thông tin nhạy cảm cần mã hoá (encrypted):

medical_history (tiền sử bệnh lý)

allergies (dị ứng)

diagnosis_notes (chẩn đoán ban đầu)

emergency_contact (liên hệ khẩn cấp)

3️⃣ Hồ sơ bệnh án (Medical Record / Treatment Plan)

Mỗi bệnh nhân có thể có nhiều kế hoạch điều trị (treatment_plans)

Mỗi kế hoạch gồm:

plan_name, diagnosis, doctor_id, estimated_cost, status

Gắn với một hoặc nhiều chi nhánh

Các mục điều trị chi tiết: plan_items (răng số, dịch vụ, chi phí đơn vị)

4️⃣ Phiên điều trị (Treatment Session)

Thuộc về 1 plan_item

Ghi nhận:

date, doctor_id, branch_id, notes, status

Vật tư đã sử dụng (qua treatment_materials)

Hình ảnh / file đính kèm (attachments table hoặc Media Library)

5️⃣ Vật tư điều trị (Treatment Materials)

Ghi lại vật tư sử dụng trong từng session:

material_id, quantity_used, cost, unit

Kết nối kho vật tư (materials) → cập nhật tồn kho tự động.

6️⃣ Hóa đơn & thanh toán (Invoices / Payments)

Khi bệnh nhân hoàn tất một hoặc nhiều session, hệ thống sinh hóa đơn:

invoice_no, patient_id, treatment_plan_id, amount, status

Mỗi hóa đơn có nhiều payments:

payment_method, amount_paid, paid_at, staff_id

7️⃣ Lịch sử chi nhánh (Branch Logs)

Khi bệnh nhân điều trị ở chi nhánh khác:

Ghi vào branch_logs (patient_id, branch_id, action, note, timestamp)

Giúp lễ tân biết lịch sử di chuyển và chăm sóc khách hàng hiệu quả.

8️⃣ Ghi chú hành vi khách hàng (Notes)

Tạo bởi nhân viên CSKH / Lễ tân

Ghi nhận các hành vi, thái độ, lưu ý chăm sóc (ví dụ: “khó tính”, “sợ đau”, “ưu tiên bác sĩ A”)

Dùng để hỗ trợ chăm sóc khách hàng toàn diện.

🧱 Sơ đồ quan hệ (Entity Relationship bổ sung)
customers 1—1 patients
patients 1—* treatment_plans
treatment_plans 1—* plan_items
plan_items 1—* treatment_sessions
treatment_sessions *—* materials (via treatment_materials)
treatment_plans 1—* invoices 1—* payments
patients 1—* branch_logs
patients 1—* notes

🧠 Lưu ý kỹ thuật

Các dữ liệu nhạy cảm trong patients nên mã hoá AES (Laravel Encryptable Caster).

Lịch sử (branch_logs, notes) nên có created_by để truy vết.

Khi customer → patient, copy thông tin cơ bản & tạo patient_code tự động:

$patient->patient_code = 'BN' . str_pad($patient->id, 6, '0', STR_PAD_LEFT);# crm-dental
