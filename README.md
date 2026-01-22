# 🏥 Phần mềm Quản lý Nha Khoa (Dental CRM)

Dự án được xây dựng bằng **Laravel 12** + **Filament 4**, hướng tới mô hình đa chi nhánh (Multi-branch), có phân quyền sâu (Admin, Quản lý, Bác sĩ, Lễ tân).
Mục tiêu: Đơn giản hóa quy trình tiếp nhận, điều trị, và chăm sóc khách hàng.

---

## ⚙️ Công nghệ cốt lõi

| Thành phần | Công nghệ | Ghi chú |
| :--- | :--- | :--- |
| **Framework** | Laravel 12.x | PHP Framework mạnh mẽ nhất hiện nay |
| **Admin Panel** | Filament v4 | Giao diện quản trị hiện đại, UX/UI tối ưu |
| **Database** | MySQL | Cơ sở dữ liệu quan hệ |
| **Frontend** | Livewire 3 + Alpine.js | Xử lý tương tác realtime (Form khám, Sơ đồ răng) |
| **Auth** | Filament Auth | Đăng nhập, phân quyền (Roles & Permissions) |

---

## 🧠 Luồng nghiệp vụ chính (Full Flow)

### 1️⃣ Tiếp nhận & Chuyển đổi (Reception Flow)
**Diễn viên**: Lễ tân (Receptionist)

1.  **Tạo Khách hàng tiềm năng (Customer)**:
    *   Ghi nhận thông tin ban đầu: Tên, SĐT, Nguồn (Facebook, Zalo...).
    *   Trạng thái: `Lead` → `Contacted`.
2.  **Chuyển đổi sang Bệnh nhân (Conversion)**:
    *   Khi khách đến khám, Lễ tân thực hiện thao tác **"Tạo hồ sơ bệnh nhân"**.
    *   Hệ thống tự động tạo bản ghi trong bảng `patients`.
    *   Sinh mã bệnh nhân tự động (Ví dụ: `PAT-20240101-XB92KL`).
    *   Tự động liên kết Customer cũ với Patient mới.

### 2️⃣ Khám & Điều trị (Clinical Flow) - *Đã cập nhật UX mới*
**Diễn viên**: Bác sĩ (Doctor), Trợ thủ

Quy trình này diễn ra chủ yếu tại màn hình **Hồ sơ bệnh nhân** -> Tab **Khám & Điều trị**.

#### A. Khám Tổng Quát & Chỉ Định (Exam & Indications)
*Sử dụng Livewire Component: `PatientExamForm`*

*   **Khám tổng quát**:
    *   Bác sĩ chọn tên mình (Bác sĩ khám) và Bác sĩ điều trị chính từ danh sách (Searchable dropdown).
    *   Ghi chú tình trạng tổng quát và hướng điều trị sơ bộ.
*   **Chỉ định cận lâm sàng**:
    *   Bác sĩ tick chọn các chỉ định cần thiết: Cephalometric, Panorama, CT Conebeam, Xét nghiệm máu...
    *   **Upload ảnh trực tiếp**: Ngay khi tick chọn, ô upload ảnh sẽ hiện ra tương ứng với loại chỉ định đó. Ảnh được lưu vào hồ sơ bệnh án để truy xuất sau này.

#### B. Chẩn đoán & Sơ đồ răng (Diagnosis & Tooth Chart)
*Sử dụng: `ClinicalNotesRelationManager` & Custom Blade View*

*   Hiển thị **Sơ đồ răng (Odontogram)** trực quan.
*   Bác sĩ click vào từng răng để gắn tình trạng (Sâu, Mất, Implant, Veneer...).
*   Mã tình trạng sẽ hiển thị ngay trên răng.
*   Hệ thống lưu lịch sử chẩn đoán theo từng ngày.

#### C. Lên Kế hoạch điều trị (Treatment Planning)
*Được tích hợp vào Accordion "Kế hoạch điều trị"*

1.  **Tạo Kế hoạch**: Đặt tên (Vd: "Cấy ghép Implant Full hàm"), chọn bác sĩ chính.
2.  **Thêm hạng mục (Plan Items)**:
    *   Chọn dịch vụ (Nhổ răng, Cạo vôi, Implant...).
    *   Chọn răng áp dụng (nếu có).
    *   Hệ thống tự động lấy đơn giá.
3.  **Tiến độ**: Theo dõi trạng thái `New` → `In Progress` → `Completed`.

### 3️⃣ Điều trị & Vật tư (Session & Inventory)
**Diễn viên**: Bác sĩ, Thủ kho

*   Mỗi lần bệnh nhân đến làm dịch vụ là một **Phiên điều trị (Session)**.
*   Trong Session, bác sĩ ghi nhận:
    *   Công việc đã làm.
    *   **Vật tư tiêu hao**: Chọn vật tư từ kho (Implant, Thuốc tê...), nhập số lượng.
    *   Hệ thống tự động trừ kho (`materials` table) thông qua bảng trung gian `treatment_materials`.

### 4️⃣ Tài chính & Thanh toán (Financial Flow)
**Diễn viên**: Lễ tân, Kế toán

1.  **Hóa đơn (Invoice)**: Được tạo từ Kế hoạch điều trị.
2.  **Thanh toán (Payment)**:
    *   Hỗ trợ thanh toán nhiều lần (Trả góp/Đặt cọc).
    *   Ghi nhận phương thức: Tiền mặt, Chuyển khoản, Thẻ.
    *   Hệ thống tự động tính công nợ còn lại của bệnh nhân.

---

## 🏗️ Cấu trúc Cơ sở dữ liệu chính

| Bảng | Chức năng | Quan hệ quan trọng |
| :--- | :--- | :--- |
| `users` | Nhân sự (Bác sĩ, Lễ tân...) | `belongsTo(branch)` |
| `patients` | Hồ sơ bệnh nhân | `hasMany(clinical_notes, treatment_plans)` |
| `clinical_notes` | Phiếu khám lâm sàng | Chứa thông tin khám, chỉ định, link ảnh chỉ định |
| `treatment_plans` | Kế hoạch điều trị | `hasMany(plan_items, invoices)` |
| `invoices` | Hóa đơn | `hasMany(payments)` |
| `branches` | Chi nhánh | Dữ liệu được phân tách (isolation) theo chi nhánh |

---

## 🧩 Ghi chú cho Developer

### 1. PatientExamForm (Livewire)
*   **Vị trí**: `app/Livewire/PatientExamForm.php`
*   **View**: `resources/views/livewire/patient-exam-form.blade.php`
*   **Nhiệm vụ**: Xử lý logic cho Accordion "Khám tổng quát" và "Chỉ định". Tự động lưu (auto-save) khi focus out hoặc upload ảnh.

### 2. Tab "Khám & Điều trị"
*   **File**: `resources/views/filament/resources/patients/pages/view-patient.blade.php`
*   Được refactor từ việc gộp tab "Treatment Plans" cũ.
*   Sử dụng cấu trúc Accordion (Alpine.js `x-data`) để chứa các thành phần con.

---

> *Tài liệu này được cập nhật để phản ánh flow làm việc mới nhất sau khi refactor module Khám & Điều trị (01/2026).*
