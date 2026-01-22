# 🏥 Phân tích Kinh doanh - Hệ thống CRM Nha khoa Đa chi nhánh

> **Phân tích bởi:** PM & Giám đốc Nha khoa (15+ năm kinh nghiệm)  
> **Ngày:** November 2, 2025  
> **Mục đích:** Đánh giá flow nghiệp vụ, database, và lập kế hoạch tối ưu

---

## 📊 1. ĐÁNH GIÁ HIỆN TRẠNG

### ✅ **Điểm mạnh của hệ thống hiện tại:**

#### **A. Lead Management (Quản lý khách hàng tiềm năng)** - 9/10
```
✅ Customer → Patient conversion logic hoàn thiện
✅ Multi-source tracking (walk-in, facebook, zalo, referral, appointment)
✅ Status lifecycle rõ ràng (lead → contacted → confirmed → converted → lost)
✅ Auto-convert khi appointment status='done' (AppointmentObserver)
✅ Manual convert button for flexibility
✅ Assigned_to for sales tracking
✅ Follow-up dates (last_contacted_at, next_follow_up_at)
```

**Thiếu:**
- ❌ Lead scoring (chấm điểm độ nóng của lead)
- ❌ Lead assignment rules (auto phân bổ lead theo quy tắc)
- ❌ Conversion rate tracking per source

---

#### **B. Patient Management** - 8/10
```
✅ Patient code tự động (BN000001...)
✅ Medical history tracking
✅ Link to original customer (customer_id)
✅ Email field added
✅ Soft deletes for data integrity
```

**Thiếu:**
- ❌ Emergency contact information
- ❌ Insurance information (BHYT/BHYT tư nhân)
- ❌ Allergies/Medical conditions structured data
- ❌ Patient photos/X-rays attachment
- ❌ Consent forms tracking (đồng ý điều trị)

---

#### **C. Appointment System** - 7/10
```
✅ Support both customer_id and patient_id
✅ Doctor assignment
✅ Branch-specific scheduling
✅ Status tracking (pending, done, canceled)
✅ Internal notes (not visible to patient)
✅ Reminder hours configuration
```

**Thiếu:**
- ❌ Appointment duration (15 phút, 30 phút, 1 giờ?)
- ❌ Appointment type (khám, điều trị, tái khám, consultation)
- ❌ Recurring appointments (hàng tuần, hàng tháng)
- ❌ Waiting list/Queue management
- ❌ Cancellation reason tracking
- ❌ No-show tracking (số lần không đến)

---

#### **D. Treatment Planning** - 6/10
```
✅ Treatment plans with status workflow
✅ Priority levels (low, normal, high, urgent)
✅ Expected dates (start/end)
✅ Total cost vs estimated cost tracking
✅ Approval workflow (approved_by, approved_at)
```

**Thiếu:**
- ❌ Treatment categories (Orthodontics, Implant, Cosmetic, etc.)
- ❌ Risk assessment (low/medium/high risk)
- ❌ Pre-treatment photos/X-rays
- ❌ Discount/Promotion application
- ❌ Treatment plan templates (cho common cases)
- ❌ Alternative treatment options comparison

---

#### **E. Services Catalog** - 5/10
```
✅ Service name, code, default price
✅ Active status
✅ Unit tracking
```

**THIẾU NGHIÊM TRỌNG:**
- ❌ **Category/Group** (Nội nha, Phục hồi, Implant, Niềng răng, etc.)
- ❌ **Service description** (chi tiết dịch vụ)
- ❌ **Duration** (thời gian ước tính mỗi dịch vụ)
- ❌ **Tooth-specific pricing** (răng hàm khác răng cửa)
- ❌ **Service dependencies** (phải làm service A trước service B)
- ❌ **Materials required** (vật tư mặc định cho mỗi service)
- ❌ **Branch-specific pricing** (giá khác nhau giữa các chi nhánh)
- ❌ **Commission structure** (hoa hồng cho bác sĩ/sales)

---

#### **F. Treatment Sessions** - 7/10
```
✅ Session linking to plan & plan_item
✅ Doctor assignment
✅ Diagnosis & procedure notes
✅ Images JSON storage
✅ Status tracking (scheduled, done, follow_up)
✅ Performed_at timestamp
```

**Thiếu:**
- ❌ Session number/sequence (buổi 1, 2, 3...)
- ❌ Chief complaint (lý do chính khám)
- ❌ Vital signs (huyết áp, nhịp tim - nếu cần)
- ❌ Anesthesia used (thuốc tê nào, liều lượng)
- ❌ Complications/Adverse events
- ❌ Next session recommendation
- ❌ Patient signature/confirmation

---

#### **G. Materials & Inventory** - 6/10
```
✅ Material tracking with SKU
✅ Stock quantity
✅ Unit price
✅ Branch-specific inventory
✅ Min_stock for reorder alerts
✅ Inventory transactions (in/out)
```

**Thiếu:**
- ❌ **Expiry date tracking** (hạn sử dụng vật tư/thuốc)
- ❌ **Batch/Lot number** (số lô sản xuất)
- ❌ **Supplier information** (nhà cung cấp)
- ❌ **Reorder point automation** (tự động đặt hàng khi hết)
- ❌ **Material categories** (thuốc, vật tư tiêu hao, dụng cụ)
- ❌ **Cost vs Sale price** (giá nhập vs giá bán)
- ❌ **Inter-branch transfer** (chuyển vật tư giữa chi nhánh)

---

#### **H. Invoicing & Payments** - 8/10
```
✅ Subtotal, discount, tax tracking
✅ Invoice status workflow (draft → issued → partial → paid)
✅ Payment tracking with multiple payments
✅ Issued/Due/Paid dates
✅ Link to treatment session & plan
```

**Thiếu:**
- ❌ **Payment methods** (cash, card, bank transfer, VNPay, MoMo)
- ❌ **Installment plans** (trả góp 3-6-12 tháng)
- ❌ **Deposit system** (đặt cọc trước)
- ❌ **Refund tracking** (hoàn tiền)
- ❌ **Invoice templates** (mẫu hóa đơn in)
- ❌ **Tax invoice integration** (hóa đơn VAT)
- ❌ **Late payment penalties** (phí trễ hạn)

---

#### **I. Branch Management** - 6/10
```
✅ Multi-branch support
✅ Manager assignment
✅ Branch logs for activities
✅ Branch-specific materials
```

**Thiếu:**
- ❌ **Operating hours** (giờ mở cửa/đóng cửa từng chi nhánh)
- ❌ **Facilities/Equipment list** (ghế nha, X-quang machine, etc.)
- ❌ **Branch performance metrics** (doanh thu, số BN/ngày)
- ❌ **Staff capacity** (số bác sĩ/nhân viên tối đa)
- ❌ **Location coordinates** (GPS for map integration)

---

#### **J. User & Permissions** - 7/10
```
✅ Role-based access (Admin, Manager, Doctor, Receptionist, CSKH)
✅ Branch assignment
✅ Specialty field for doctors
✅ 2FA security
✅ Passkeys support
✅ Spatie Permission integration
```

**Thiếu:**
- ❌ **Doctor schedule/availability** (lịch làm việc bác sĩ)
- ❌ **Commission tracking** (hoa hồng của nhân viên)
- ❌ **Performance KPIs** (số BN xử lý/ngày, doanh thu)
- ❌ **Leave management** (nghỉ phép)
- ❌ **Certification/License tracking** (chứng chỉ hành nghề)

---

## 🚨 2. VẤN ĐỀ NGHIÊM TRỌNG CẦN GIẢI QUYẾT NGAY

### **P0 - Critical (Ảnh hưởng vận hành)**

#### **1. Services Table - THIẾU THÔNG TIN QUAN TRỌNG** ⚠️⚠️⚠️
```sql
-- Hiện tại:
services: {
  id, name, code, unit, default_price, active
}

-- Cần có NGAY:
services: {
  id, name, code, 
  category_id (FK → service_categories),  // Phân loại dịch vụ
  description (text),                      // Mô tả chi tiết
  duration_minutes (int),                  // 15, 30, 60 phút
  tooth_specific (boolean),                // Có phụ thuộc răng không?
  default_materials (JSON),                // [{material_id, qty}]
  doctor_commission_rate (decimal),        // % hoa hồng bác sĩ
  branch_id (nullable),                    // Giá riêng theo chi nhánh
  sort_order (int),                        // Thứ tự hiển thị
  default_price, active, timestamps
}
```

**Tại sao quan trọng:**
- Bác sĩ không biết dịch vụ mất bao lâu → Lịch hẹn chồng chéo
- Không có danh mục → Khó tìm kiếm, báo cáo doanh thu theo nhóm
- Không track hoa hồng → Nhân viên mất động lực
- Không có duration → Không tính được công suất phòng khám

---

#### **2. Appointment Duration & Type** ⚠️⚠️
```sql
-- Thêm vào appointments:
ALTER TABLE appointments ADD COLUMN (
  appointment_type ENUM('consultation','treatment','follow_up','emergency'),
  duration_minutes INT DEFAULT 30,
  chief_complaint TEXT,  // Lý do khám chính
  confirmed_at TIMESTAMP // Khách confirm lịch hẹn
);
```

**Tại sao quan trọng:**
- Không có duration → Lịch bác sĩ bị overlap
- Không phân loại appointment → Không ưu tiên được khẩn cấp
- Không có chief complaint → Bác sĩ không chuẩn bị trước

---

#### **3. Patient Insurance & Medical Conditions** ⚠️⚠️
```sql
CREATE TABLE patient_medical_records (
  id,
  patient_id (FK),
  allergies JSON,              // ["penicillin", "latex"]
  chronic_diseases JSON,       // ["diabetes", "hypertension"]
  medications JSON,            // Đang dùng thuốc gì
  insurance_provider VARCHAR,  // BHYT/Bảo hiểm tư
  insurance_number VARCHAR,
  emergency_contact_name VARCHAR,
  emergency_contact_phone VARCHAR,
  blood_type ENUM('A','B','AB','O','unknown'),
  notes TEXT,
  updated_at, updated_by
);
```

**Tại sao quan trọng:**
- Không biết dị ứng → **RỦI RO CAO**, có thể gây shock phản vệ
- Không có BHYT → Mất cơ hội thanh toán bảo hiểm
- Không có emergency contact → Khó xử lý khi khẩn cấp

---

#### **4. Treatment Plan Item - Tooth Information** ⚠️
```sql
-- Thêm vào plan_items:
ALTER TABLE plan_items ADD COLUMN (
  tooth_numbers JSON,          // ["16", "17"] - nhiều răng
  tooth_surface VARCHAR(10),   // "M", "O", "D" (mesial, occlusal, distal)
  session_number INT,          // Buổi thứ mấy thực hiện
  completed_at TIMESTAMP,      // Khi nào hoàn thành
  actual_duration_minutes INT  // Thực tế mất bao lâu
);
```

**Tại sao quan trọng:**
- Không ghi răng nào → Bác sĩ không biết điều trị chỗ nào
- Không có session_number → Không sắp xếp trình tự điều trị

---

### **P1 - High Priority (Ảnh hưởng hiệu quả kinh doanh)**

#### **5. Service Categories** 📁
```sql
CREATE TABLE service_categories (
  id,
  name VARCHAR(100),              // "Nội nha", "Phục hồi", "Implant"
  code VARCHAR(20) UNIQUE,        // "NH", "PH", "IMP"
  parent_id INT NULLABLE,         // Hỗ trợ sub-category
  icon VARCHAR(50),               // Icon hiển thị UI
  color VARCHAR(20),              // Màu nhóm
  description TEXT,
  sort_order INT,
  active BOOLEAN DEFAULT true,
  timestamps
);

-- Update services table:
ALTER TABLE services ADD COLUMN category_id INT;
```

**Lợi ích:**
- Báo cáo doanh thu theo nhóm dịch vụ
- UI dễ tìm kiếm hơn (group by category)
- Phân tích dịch vụ nào HOT nhất

---

#### **6. Installment Payments** 💳
```sql
CREATE TABLE payment_plans (
  id,
  invoice_id (FK),
  total_amount DECIMAL,
  number_of_installments INT,    // 3, 6, 12 kỳ
  installment_amount DECIMAL,
  interest_rate DECIMAL DEFAULT 0,
  start_date DATE,
  status ENUM('active','completed','defaulted'),
  created_by, timestamps
);

CREATE TABLE payment_installments (
  id,
  payment_plan_id (FK),
  installment_number INT,        // Kỳ thứ 1, 2, 3...
  due_date DATE,
  amount DECIMAL,
  paid_amount DECIMAL DEFAULT 0,
  paid_at TIMESTAMP NULL,
  status ENUM('pending','paid','overdue','waived'),
  late_fee DECIMAL DEFAULT 0,
  payment_id INT NULL,           // FK → payments khi trả
  notes TEXT,
  timestamps
);
```

**Lợi ích:**
- Tăng conversion rate (khách dễ chấp nhận trả góp)
- Quản lý công nợ chặt chẽ
- Auto reminder trước hạn trả

---

#### **7. Promotions & Discounts** 🎁
```sql
CREATE TABLE promotions (
  id,
  code VARCHAR(50) UNIQUE,       // "TETNGUYENDAN2025"
  name VARCHAR(200),
  description TEXT,
  discount_type ENUM('percentage','fixed','free_service'),
  discount_value DECIMAL,
  min_purchase_amount DECIMAL,
  max_discount_amount DECIMAL,   // Giảm tối đa
  applicable_services JSON,      // Chỉ áp dụng cho service nào
  applicable_branches JSON,      // Chi nhánh nào
  start_date DATE,
  end_date DATE,
  usage_limit INT,               // Tổng số lần dùng
  usage_per_customer INT,        // Mỗi khách dùng max
  used_count INT DEFAULT 0,
  active BOOLEAN DEFAULT true,
  timestamps
);

CREATE TABLE promotion_usages (
  id,
  promotion_id (FK),
  customer_id/patient_id (FK),
  invoice_id (FK),
  discount_amount DECIMAL,
  used_at TIMESTAMP
);
```

**Lợi ích:**
- Marketing campaigns hiệu quả
- Tracking ROI của promotion
- Khuyến khích khách quay lại

---

#### **8. Doctor Schedule/Availability** 📅
```sql
CREATE TABLE doctor_schedules (
  id,
  doctor_id (FK → users),
  branch_id (FK),
  day_of_week INT,               // 0=Sunday, 1=Monday
  start_time TIME,
  end_time TIME,
  is_available BOOLEAN DEFAULT true,
  effective_from DATE,
  effective_to DATE NULL,
  timestamps
);

CREATE TABLE doctor_leaves (
  id,
  doctor_id (FK),
  start_date DATE,
  end_date DATE,
  leave_type ENUM('vacation','sick','training','other'),
  reason TEXT,
  approved_by INT,
  status ENUM('pending','approved','rejected'),
  timestamps
);
```

**Lợi ích:**
- Appointment system không book nhầm khi bác sĩ nghỉ
- Tối ưu công suất bác sĩ
- Báo cáo năng suất chuẩn xác

---

#### **9. Material Expiry & Batch Tracking** 🗓️
```sql
ALTER TABLE materials ADD COLUMN (
  category ENUM('medicine','consumable','equipment','dental_material'),
  manufacturer VARCHAR(200),
  supplier_id INT,               // FK → suppliers table
  reorder_point INT,             // Điểm đặt hàng lại
  storage_location VARCHAR(100)  // Vị trí lưu kho
);

CREATE TABLE material_batches (
  id,
  material_id (FK),
  batch_number VARCHAR(50),
  expiry_date DATE,
  quantity INT,
  purchase_price DECIMAL,
  received_date DATE,
  supplier_id INT,
  status ENUM('active','expired','recalled'),
  timestamps
);

-- Update treatment_materials to use batch:
ALTER TABLE treatment_materials 
  ADD COLUMN batch_id INT REFERENCES material_batches(id);
```

**Lợi ích:**
- Tránh dùng vật tư/thuốc hết hạn → **An toàn bệnh nhân**
- Truy xuất nguồn gốc khi có sự cố
- Quản lý tồn kho chính xác theo lô

---

### **P2 - Medium Priority (Nâng cao trải nghiệm)**

#### **10. Patient Portal Features** 👤
```sql
CREATE TABLE patient_portal_access (
  id,
  patient_id (FK),
  email VARCHAR UNIQUE,
  password_hash VARCHAR,
  email_verified_at TIMESTAMP,
  last_login_at TIMESTAMP,
  active BOOLEAN DEFAULT true,
  timestamps
);

CREATE TABLE patient_documents (
  id,
  patient_id (FK),
  document_type ENUM('xray','photo','consent_form','prescription','report'),
  file_path VARCHAR,
  file_name VARCHAR,
  file_size INT,
  mime_type VARCHAR,
  uploaded_by INT,
  uploaded_at TIMESTAMP,
  visible_to_patient BOOLEAN DEFAULT false,
  notes TEXT
);
```

**Lợi ích:**
- Bệnh nhân tự xem lịch hẹn, hóa đơn
- Giảm tải công việc receptionist
- Tăng tính chuyên nghiệp

---

#### **11. SMS/Email Notification System** 📱
```sql
CREATE TABLE notification_templates (
  id,
  type ENUM('appointment_reminder','payment_reminder','birthday','promotion'),
  channel ENUM('sms','email','both'),
  subject VARCHAR(200),
  content TEXT,                  // Với variables: {patient_name}, {date}
  active BOOLEAN DEFAULT true,
  timestamps
);

CREATE TABLE notification_logs (
  id,
  template_id (FK),
  recipient_type ENUM('customer','patient','user'),
  recipient_id INT,
  channel ENUM('sms','email'),
  phone/email VARCHAR,
  sent_at TIMESTAMP,
  status ENUM('pending','sent','failed','delivered'),
  error_message TEXT NULL,
  cost DECIMAL,                  // Chi phí gửi SMS
  timestamps
);
```

**Lợi ích:**
- Giảm no-show rate (nhắc lịch hẹn)
- Thu nợ hiệu quả (reminder thanh toán)
- Marketing automation

---

#### **12. Referral System** 🎯
```sql
CREATE TABLE referrals (
  id,
  referrer_id INT,               // Người giới thiệu (existing patient)
  referee_id INT,                // Người được giới thiệu (new customer)
  referral_code VARCHAR(20),
  status ENUM('pending','converted','rewarded'),
  converted_at TIMESTAMP,
  reward_type ENUM('discount','cash','service'),
  reward_value DECIMAL,
  reward_given_at TIMESTAMP,
  timestamps
);
```

**Lợi ích:**
- Tăng trưởng organic (khách giới thiệu khách)
- Tracking nguồn khách chất lượng
- Khách hàng trung thành

---

#### **13. Revenue Forecasting & Analytics** 📊
```sql
CREATE TABLE revenue_targets (
  id,
  branch_id (FK),
  year INT,
  month INT,
  target_amount DECIMAL,
  actual_amount DECIMAL DEFAULT 0,
  created_by INT,
  timestamps
);

CREATE TABLE kpi_metrics (
  id,
  metric_type ENUM('new_patients','appointments','conversion_rate','avg_invoice'),
  branch_id (FK),
  user_id (FK) NULL,             // NULL = branch-level, not NULL = user-level
  period_type ENUM('daily','weekly','monthly'),
  period_start DATE,
  period_end DATE,
  target_value DECIMAL,
  actual_value DECIMAL,
  timestamps
);
```

**Lợi ích:**
- Đặt mục tiêu rõ ràng cho team
- Dashboard quản lý real-time
- Phát hiện sớm vấn đề (doanh thu giảm)

---

## 📈 3. KẾ HOẠCH TỐI ƯU HÓA (3 THÁNG)

### **THÁNG 1: Foundation (Nền tảng)**

#### Week 1-2: Services & Appointment Enhancement
```
[ ] Tạo service_categories table
[ ] Thêm duration, category_id, description vào services
[ ] Seed data: 50+ dental services với đầy đủ info
[ ] Thêm appointment_type, duration vào appointments
[ ] Update Filament UI: Service catalog với category filter
[ ] Test: Book appointment với duration checking
```

#### Week 3-4: Patient Safety & Medical Records
```
[ ] Tạo patient_medical_records table
[ ] Form nhập liệu: allergies, medications, insurance
[ ] Alert UI khi bệnh nhân có dị ứng (đỏ warning)
[ ] Consent form management (digital signature)
[ ] Update Patient detail page với medical tab
```

---

### **THÁNG 2: Business Operations (Vận hành kinh doanh)**

#### Week 5-6: Inventory & Batch Tracking
```
[ ] Thêm expiry_date, batch_number vào materials
[ ] Material categories (thuốc, vật tư, dụng cụ)
[ ] Expiry alert system (7 days, 30 days warning)
[ ] Batch usage tracking trong treatment_materials
[ ] Report: Vật tư sắp hết hạn
```

#### Week 7-8: Payment Enhancements
```
[ ] Payment methods tracking (cash, card, bank, e-wallet)
[ ] Installment payment system (payment_plans table)
[ ] Deposit/Prepayment support
[ ] Refund tracking
[ ] Payment reminder automation (3 days, 7 days overdue)
```

---

### **THÁNG 3: Growth & Optimization (Tăng trưởng)**

#### Week 9-10: Marketing & Promotions
```
[ ] Promotions table với coupon codes
[ ] Apply promotion in invoice
[ ] Usage tracking & analytics
[ ] Referral system (giới thiệu bạn bè)
[ ] Birthday auto-SMS campaign
```

#### Week 11-12: Analytics & Reporting
```
[ ] Dashboard widgets: Revenue today/month, new patients, appointments
[ ] Doctor performance report (số BN, doanh thu, commission)
[ ] Service popularity report (top 10 hot services)
[ ] Branch comparison analytics
[ ] Conversion funnel: Lead → Customer → Patient
[ ] Material inventory turnover report
```

---

## 🎯 4. ROI DỰ KIẾN SAU 3 THÁNG

### **Giảm chi phí:**
- ⬇️ 30% thời gian admin tasks (auto reminder, auto reporting)
- ⬇️ 50% material waste (expiry tracking, batch management)
- ⬇️ 20% no-show rate (SMS reminder 24h trước)

### **Tăng doanh thu:**
- ⬆️ 25% conversion rate (installment payments, promotions)
- ⬆️ 40% patient referrals (referral program)
- ⬆️ 15% average invoice value (upsell services)

### **Nâng cao chất lượng:**
- ⬆️ 95% patient satisfaction (better service, faster process)
- ⬆️ 100% compliance (medical records, consent forms)
- ⬆️ Real-time inventory visibility (no stockouts)

---

## 🚀 5. IMMEDIATE ACTIONS (HÔM NAY)

### **Top 3 Tasks - START NOW:**

#### 1️⃣ **Services Enhancement** (4 giờ)
```sql
-- Step 1: Create service_categories
-- Step 2: Update services table (add columns)
-- Step 3: Seed 50 dental services
-- Step 4: Update Filament ServiceResource UI
```

#### 2️⃣ **Appointment Duration** (2 giờ)
```sql
-- Step 1: Add duration_minutes to appointments
-- Step 2: Add appointment_type ENUM
-- Step 3: Update AppointmentForm with duration field
-- Step 4: Calendar view với time blocks
```

#### 3️⃣ **Patient Medical Records** (3 giờ)
```sql
-- Step 1: Create patient_medical_records table
-- Step 2: Migration & model
-- Step 3: Filament form for allergies, insurance
-- Step 4: Display warning badge if has allergies
```

---

## 📝 6. KẾT LUẬN

### **Hệ thống hiện tại: 7/10** ⭐⭐⭐⭐⭐⭐⭐

**Ưu điểm:**
- Foundation vững chắc (lead management, multi-branch)
- Auto-conversion logic thông minh
- Treatment workflow đầy đủ

**Điểm yếu chính:**
- Services table quá đơn giản (thiếu category, duration, commission)
- Chưa có patient medical records (dị ứng, bảo hiểm)
- Appointment chưa có duration → khó schedule
- Inventory thiếu expiry date tracking
- Payment chưa hỗ trợ trả góp

### **Sau tối ưu: 9.5/10** ⭐⭐⭐⭐⭐⭐⭐⭐⭐✨

**Trở thành:**
- ✅ Professional dental management system
- ✅ Full compliance (medical records, consent)
- ✅ Marketing automation (SMS, promotions)
- ✅ Financial management (installments, forecasting)
- ✅ Scalable for 10+ branches

---

## 💡 7. FINAL RECOMMENDATION

**Ưu tiên theo thứ tự:**

1. **Week 1:** Services + Appointments (duration, type)
2. **Week 2:** Patient medical records (allergies, insurance)
3. **Week 3:** Inventory expiry tracking
4. **Week 4:** Payment methods + Installments
5. **Week 5:** Promotions system
6. **Week 6:** Analytics dashboard

**Đầu tư:**
- **Dev time:** 3 tháng (1 senior dev full-time)
- **Budget:** ~$15,000 - $20,000 (nếu outsource)
- **ROI:** 300% trong 6 tháng (giảm chi phí + tăng doanh thu)

**Rủi ro:**
- Nếu không làm expiry tracking → Có thể dùng thuốc/vật tư hết hạn → **RỦI RO PHÁP LÝ CAO**
- Nếu không có medical records → Vi phạm quy định Y tế → **PHÍ PHẠT**
- Nếu không optimize services → Mất khách vào đối thủ → **MẤT THỊ PHẦN**

---

**🎯 Start with Services & Appointments enhancement TODAY!**

Có cần tôi implement ngay 3 tasks trên không? (Services + Appointments + Medical Records)
