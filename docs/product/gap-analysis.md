# Gap Analysis: DentalFlow vs Current CRM Implementation

> **Ngày tạo:** 2026-01-25  
> **Tham chiếu:** [DentalFlow](https://app.dentalflow.vn/)  
> **Spec nguồn:** [DENTAL_CRM_SPECIFICATION.md](./dental-crm-specification.md)

---

## Tổng quan

Tài liệu này phân tích chi tiết sự khác biệt giữa hệ thống DentalFlow (tham chiếu) và CRM nha khoa hiện tại đang phát triển.

### Legend

| Icon | Ý nghĩa |
|------|---------|
| ✅ | Đã triển khai đầy đủ |
| ⚠️ | Triển khai một phần / Cần cải tiến |
| ❌ | Chưa triển khai |
| 🔄 | Đang phát triển |

---

## 1. Tab Thông tin cơ bản (Basic Information)

### 1.1 Thông tin cá nhân

| Trường (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------|------------|--------------|---------|
| Mã hồ sơ | ✅ | `patient_code` | Auto-generate: PAT-YYYYMMDD-XXXXXX |
| Ngày tạo | ✅ | `created_at` | Tự động |
| Họ tên | ✅ | `full_name` | |
| Giới tính | ✅ | `gender` | enum: male/female/other |
| Email | ✅ | `email` | |
| Ngày sinh | ✅ | `birthday` | |
| Số CCCD | ❌ | - | **Cần thêm trường** |
| Số điện thoại 1 | ✅ | `phone` | |
| Số điện thoại 2 | ❌ | - | **Cần thêm trường** |
| Nghề nghiệp | ❌ | - | **Cần thêm trường** |

### 1.2 Thông tin Marketing

| Trường (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------|------------|--------------|---------|
| Nguồn khách hàng | ✅ | `Customer.source` | enum: walkin/facebook/zalo/referral/appointment/other |
| Nhóm khuyến mãi | ❌ | - | **Cần tạo module PromotionGroup** |
| Nhóm khách hàng | ❌ | - | **Cần tạo module CustomerGroup (VIP, Gold...)** |
| Địa chỉ | ✅ | `address` | |

### 1.3 Thông tin Y tế

| Trường (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------|------------|--------------|---------|
| Lý do đến khám | ⚠️ | `Appointment.chief_complaint` | Có nhưng ở Appointment, không ở Patient |
| Bác sĩ phụ trách | ⚠️ | - | Có ở TreatmentPlan, không ở Patient level |
| Nhân viên phụ trách | ✅ | `Customer.assigned_to` | |

### 1.4 Thông tin Người thân

| Trường (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------|------------|--------------|---------|
| Họ tên người thân | ⚠️ | `PatientMedicalRecord.emergency_contact_name` | Chỉ emergency contact |
| Quan hệ | ✅ | `PatientMedicalRecord.emergency_contact_relationship` | |
| Số điện thoại | ✅ | `PatientMedicalRecord.emergency_contact_phone` | |
| Email người thân | ❌ | - | **Cần thêm trường** |

### 1.5 Tiền sử bệnh

| Trường (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------|------------|--------------|---------|
| Tiền sử bệnh | ✅ | `Patient.medical_history` | Text field |
| Dị ứng | ✅ | `PatientMedicalRecord.allergies` | JSON array |
| Bệnh mãn tính | ✅ | `PatientMedicalRecord.chronic_diseases` | JSON array |
| Thuốc đang dùng | ✅ | `PatientMedicalRecord.current_medications` | JSON array |
| Nhóm máu | ✅ | `PatientMedicalRecord.blood_type` | |

### 1.6 Bảo hiểm

| Trường (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------|------------|--------------|---------|
| Nhà cung cấp bảo hiểm | ✅ | `PatientMedicalRecord.insurance_provider` | |
| Số bảo hiểm | ✅ | `PatientMedicalRecord.insurance_number` | |
| Ngày hết hạn | ✅ | `PatientMedicalRecord.insurance_expiry_date` | |

---

## 2. Tab Khám & Điều trị (Examination & Treatment)

### 2.1 Khám Tổng Quát

| Trường (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------|------------|--------------|---------|
| Bác sĩ khám | ✅ | `ClinicalNote.examining_doctor_id` | |
| Bác sĩ điều trị | ✅ | `ClinicalNote.treating_doctor_id` | |
| Phòng khám | ✅ | `ClinicalNote.branch_id` | |
| Nhận xét tổng quát | ✅ | `ClinicalNote.general_exam_notes` | |
| Nhận xét khuyến khích | ⚠️ | - | **Cần thêm trường `recommendation_notes`** |

### 2.2 Chỉ định (Orders)

| Loại chỉ định (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|---------------------------|------------|--------------|---------|
| Cephalometric | ✅ | `ClinicalNote.indications` | JSON array với các loại |
| Panorama | ✅ | ✅ | |
| 3D 5x5 | ✅ | ✅ | |
| 3D | ✅ | ✅ | |
| Cắn chốp | ✅ | ✅ | |
| Ảnh ngoài miệng (ext) | ✅ | ✅ | |
| Ảnh trong miệng (int) | ✅ | ✅ | |
| Xét nghiệm huyết học | ✅ | ✅ | |
| Xét nghiệm sinh hóa | ✅ | ✅ | |
| **Upload ảnh chỉ định** | ✅ | `ClinicalNote.indication_images` | JSON array paths |

### 2.3 Sơ đồ Răng (Tooth Chart)

| Tính năng (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------------|------------|--------------|---------|
| Hiển thị 4 hàng răng | ✅ | `ToothConditionsRelationManager` | Adult Upper/Lower, Child Upper/Lower |
| Chọn răng để chẩn đoán | ✅ | `PatientToothCondition` | |
| Modal chọn tình trạng | ✅ | `ToothCondition` seeder | |
| Màu sắc theo trạng thái | ✅ | `treatment_status` | Gray/Red/Green |
| Danh sách tình trạng răng | ✅ | Seeder có ~15 conditions | SR, RV, SL, SNR, A, MC, VN... |
| Lưu vào Treatment Plan | ✅ | `TreatmentPlan.tooth_diagnosis_data` | JSON |

### 2.4 Kế hoạch Điều trị

| Cột (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------|------------|--------------|---------|
| Răng số | ✅ | `PlanItem.tooth_number` | |
| Tình trạng răng | ✅ | `PlanItem.tooth_condition` | |
| Tên thủ thuật | ✅ | `PlanItem.service_id` → Service | |
| KH đồng ý | ✅ | `PlanItem.patient_approved` | Boolean |
| S.L (Số lượng) | ✅ | `PlanItem.quantity` | |
| Đơn giá | ✅ | `PlanItem.unit_price` | |
| Thành tiền | ✅ | Calculated | |
| Giảm giá (%) | ✅ | `PlanItem.discount_percentage` | |
| Tiền giảm giá | ✅ | Calculated | |
| Tổng chi phí | ✅ | `PlanItem.total_cost` | |
| Ghi chú | ✅ | `PlanItem.notes` | |
| Tình trạng | ✅ | `PlanItem.status` | pending/in_progress/completed/cancelled |
| Thao tác (Sửa/Xóa) | ✅ | Filament Actions | |

---

## 3. Tab Đơn thuốc (Prescription)

| Tính năng (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------------|------------|--------------|---------|
| Ngày điều trị | ✅ | `Prescription.treatment_date` | |
| Ngày tạo | ✅ | `created_at` | |
| Mã đơn thuốc | ✅ | `prescription_code` | Auto-generate: DT + YYMMDD + 0001 |
| Tên đơn thuốc | ✅ | `prescription_name` | |
| Bác sĩ kê đơn | ✅ | `doctor_id` | |
| Chi tiết thuốc | ✅ | `PrescriptionItem` | name, dosage, frequency, duration, quantity, instructions |
| **Thêm đơn thuốc** | ✅ | `PrescriptionsRelationManager` | Modal creation |
| **In đơn thuốc** | ❌ | - | **Cần phát triển PDF export** |
| **Xem đơn thuốc** | ✅ | View action | |
| **Xóa đơn thuốc** | ✅ | Delete action | Soft delete |

---

## 4. Tab Thư viện Ảnh (Photo Library)

| Tính năng (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------------|------------|--------------|---------|
| Ảnh thông thường | ✅ | `PatientPhoto.type = 'normal'` | |
| Ảnh chỉnh nha | ✅ | `PatientPhoto.type = 'ortho'` | |
| Ảnh X-quang | ⚠️ | - | **Cần thêm type 'xray'** |
| Ngày chụp | ✅ | `date` | |
| Tiêu đề | ✅ | `title` | |
| Nội dung ảnh | ✅ | `content` | JSON - multiple paths |
| Mô tả | ✅ | `description` | |
| **Thêm ảnh chính thức** | ⚠️ | - | **Cần phân biệt formal vs quick upload** |
| **Drag & Drop** | ✅ | Filament FileUpload | |
| **Paste ảnh** | ❌ | - | **Cần custom component** |
| **Preview ảnh** | ✅ | View action | |
| **Xóa ảnh** | ✅ | Delete action | |

---

## 5. Tab Lịch hẹn (Appointment)

| Cột (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------|------------|--------------|---------|
| Ngày | ✅ | `date` | DateTime |
| Khung giờ | ⚠️ | `date` + `duration_minutes` | **Cần hiển thị dạng "15:00-15:15"** |
| Bác sĩ | ✅ | `doctor_id` | |
| Nội dung | ✅ | `note` | |
| Phân loại | ⚠️ | `appointment_type` | Có nhưng cần mở rộng options |
| Loại lịch hẹn | ⚠️ | - | **Cần thêm: Đặt hẹn / Tái khám** |
| Ghi chú | ✅ | `note`, `internal_notes` | 2 trường |
| Lý do hẹn tái/hủy | ⚠️ | - | **Cần thêm trường `cancellation_reason`** |
| Trạng thái | ✅ | `status` | pending/done/canceled |
| Thao tác | ✅ | Edit/Delete actions | |
| Xác nhận | ✅ | `confirmed_at`, `confirmed_by` | |
| Nhắc nhở | ✅ | `reminder_hours` | Có table `appointment_reminders` |

---

## 6. Tab Thanh toán (Payment)

### 6.1 Tổng quan thanh toán

| Chỉ số (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|--------------------|------------|--------------|---------|
| Tổng tiền điều trị | ✅ | `TreatmentPlan.total_cost` | |
| Giảm giá | ✅ | `Invoice.discount_amount` | |
| Phải thanh toán | ✅ | `Invoice.total_amount` | |
| Số dư | ⚠️ | - | **Cần implement wallet/deposit system** |
| Đã thu | ✅ | `Invoice.paid_amount` | |
| Còn lại | ✅ | `Invoice.calculateBalance()` | |

### 6.2 Hóa đơn điều trị

| Cột (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------|------------|--------------|---------|
| Ngày điều trị | ✅ | `issued_at` | |
| Thành tiền | ✅ | `subtotal` | |
| Tiền giảm giá | ✅ | `discount_amount` | |
| Tổng chi phí | ✅ | `total_amount` | |
| Đã thanh toán | ✅ | `paid_amount` | |
| Còn lại | ✅ | `calculateBalance()` | |
| Đã xuất hóa đơn | ⚠️ | `status` | **Cần thêm trường `invoice_exported`** |
| **Xuất hóa đơn** | ❌ | - | **Cần phát triển PDF/Invoice export** |

### 6.3 Phiếu thu/hoàn

| Cột (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------|------------|--------------|---------|
| Ngày tạo | ✅ | `created_at` | |
| Ngày lập phiếu | ✅ | `paid_at` | |
| Loại phiếu | ⚠️ | - | **Cần thêm: Thu / Hoàn (refund)** |
| Hình thức thanh toán | ✅ | `method` | cash/card/transfer/other |
| Người tạo | ✅ | `received_by` | |
| Số tiền | ✅ | `amount` | |
| Nội dung | ✅ | `note` | |
| Mã giao dịch | ✅ | `transaction_ref` | |
| **Phiếu hoàn tiền** | ❌ | - | **Cần tạo Refund model** |
| **In phiếu** | ❌ | - | **Cần phát triển print functionality** |

### 6.4 Trả góp

| Tính năng (DentalFlow) | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------------------|------------|--------------|---------|
| Kế hoạch trả góp | ✅ | `InstallmentPlan` | Full model |
| Chi tiết kỳ thanh toán | ✅ | Có trong model | |
| Nhắc nhở thanh toán | ✅ | `PaymentReminder` | |

---

## 7. Các Tab bổ sung (Chưa triển khai)

| Tab (DentalFlow) | Trạng thái | Ghi chú |
|-----------------|------------|---------|
| Xưởng/Vật tư | ⚠️ | Có `Material`, `MaterialBatch`, `TreatmentMaterial` nhưng thiếu Labo module |
| Biểu mẫu (Forms) | ❌ | **Cần tạo module ConsentForms, MedicalForms** |
| Chăm sóc KH | ⚠️ | Có `customer_interactions` table, cần RelationManager |
| Lịch sử thao tác | ⚠️ | Có `IdentificationLog` cho identification, cần full audit log |

---

## 8. Tính năng hệ thống

| Tính năng | Trạng thái | Hiện tại CRM | Ghi chú |
|-----------|------------|--------------|---------|
| Đa chi nhánh | ✅ | `Branch` model + `first_branch_id` | Full support |
| Chuyển chi nhánh | ✅ | `BranchLog` | Audit trail |
| Phát hiện trùng lặp | ✅ | `DuplicateDetection`, `IdentificationService` | Full featured |
| Merge records | ✅ | `RecordMerge`, `RecordMergeService` | With rollback |
| Phân quyền | ✅ | Spatie Permission | roles & permissions |
| Multi-tenant | ✅ | Branch-based filtering | |
| Soft Delete | ✅ | Tất cả models chính | |

---

## Tóm tắt Gap Analysis

### ✅ Đã triển khai tốt (80-100%)
1. **Kế hoạch điều trị** - Full featured với PlanItems, progress tracking
2. **Sơ đồ răng (Tooth Chart)** - Interactive UI với conditions
3. **Thanh toán cơ bản** - Invoice, Payment, InstallmentPlan
4. **Đơn thuốc** - Prescription với items
5. **Lịch hẹn cơ bản** - CRUD đầy đủ
6. **Thư viện ảnh** - Upload, categories
7. **Hệ thống Identification** - Duplicate detection, merge

### ⚠️ Cần cải tiến (50-79%)
1. **Thông tin bệnh nhân** - Thiếu một số trường (CCCD, SĐT2, nghề nghiệp)
2. **Chỉ định** - Có nhưng cần enhanced UI
3. **Lịch hẹn** - Thiếu khung giờ display, loại hẹn
4. **Photo Library** - Thiếu X-ray type, paste functionality

### ❌ Cần phát triển mới (0-49%)
1. **In ấn** - Đơn thuốc, hóa đơn, phiếu thu
2. **Hoàn tiền (Refund)** - Chưa có model
3. **Nhóm KH/Khuyến mãi** - Chưa có
4. **Biểu mẫu (Consent Forms)** - Chưa có
5. **Labo/Xưởng** - Thiếu workflow
6. **Số dư tài khoản (Wallet)** - Chưa có

---

## Ưu tiên Phát triển (Đề xuất)

### Priority 1 - Critical (Sprint 1-2)
| # | Tính năng | Effort | Impact |
|---|-----------|--------|--------|
| 1 | Thêm các trường thiếu cho Patient (CCCD, SĐT2, nghề nghiệp) | Low | High |
| 2 | Nhóm khách hàng (CustomerGroup) | Medium | High |
| 3 | In đơn thuốc PDF | Medium | High |
| 4 | Refund/Hoàn tiền | Medium | High |

### Priority 2 - Important (Sprint 3-4)
| # | Tính năng | Effort | Impact |
|---|-----------|--------|--------|
| 5 | In hóa đơn/phiếu thu PDF | Medium | Medium |
| 6 | X-ray photo type | Low | Medium |
| 7 | Appointment type (Tái khám/Đặt hẹn) | Low | Medium |
| 8 | Cancellation reason field | Low | Medium |

### Priority 3 - Nice to have (Sprint 5+)
| # | Tính năng | Effort | Impact |
|---|-----------|--------|--------|
| 9 | Consent Forms module | High | Medium |
| 10 | Customer Wallet/Deposit | High | Medium |
| 11 | Labo workflow | High | Medium |
| 12 | Paste image functionality | Medium | Low |
| 13 | Full audit log | Medium | Low |

---

## Kết luận

Hệ thống CRM hiện tại đã triển khai được **~70%** tính năng so với DentalFlow. Các module core (Patient, Treatment Plan, Payment, Prescription, Tooth Chart) đã hoàn thiện. Các gap chính tập trung vào:

1. **Data fields** - Thiếu một số trường thông tin cá nhân
2. **Print/Export** - Chưa có chức năng in ấn
3. **Workflows** - Thiếu Labo, Consent Forms
4. **Financial** - Thiếu Refund, Wallet

Việc bổ sung Priority 1 sẽ nâng tỉ lệ hoàn thiện lên **~85%**.

---

> **Người tạo:** AI Assistant  
> **Ngày cập nhật:** 2026-01-25
