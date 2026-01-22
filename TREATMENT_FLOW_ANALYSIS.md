# 🦷 Treatment Flow Analysis - Dental CRM System

> **Research Date:** October 31, 2025  
> **Purpose:** Phân tích quy trình điều trị nha khoa trong thực tế

---

## 🏥 Quy trình thực tế tại phòng khoa

### **Bước 1: Khám ban đầu (Initial Consultation)**

**Khi bệnh nhân đến:**
1. ✅ Receptionist tạo Appointment hoặc walk-in
2. ✅ Bác sĩ khám → Chẩn đoán (Diagnosis)
3. ✅ Tư vấn phương án điều trị

**Database:**
- `appointments.status = 'done'`
- Tạo ghi chú trong `notes` (polymorphic → Patient)

---

### **Bước 2: Lập Kế hoạch Điều trị (Treatment Plan)**

**Nội dung kế hoạch:**

#### **Ví dụ thực tế:**
```
Bệnh nhân: Nguyễn Văn A
Chẩn đoán: Sâu răng nhiều vị trí, mất răng hàm

KHAI HOẠCH ĐIỀU TRỊ:
┌────────────────────────────────────────────────────────────────┐
│ 1. Điều trị nội nha răng số 16                                 │
│    - Lấy tủy                                500,000 VNĐ         │
│    - Chụp X-quang                           200,000 VNĐ         │
│    - Trám composite                         300,000 VNĐ         │
│    Số buổi dự kiến: 2-3 buổi                                   │
│                                                                 │
│ 2. Nhổ răng khôn số 48                                         │
│    - Nhổ răng khôn mọc lệch              1,500,000 VNĐ         │
│    - Thuốc kháng sinh + giảm đau           150,000 VNĐ         │
│    Số buổi: 1 buổi                                             │
│                                                                 │
│ 3. Trồng răng Implant số 36                                    │
│    - Cấy Implant Osstem                 15,000,000 VNĐ         │
│    - Làm răng sứ                         8,000,000 VNĐ         │
│    Số buổi: 3-4 buổi (cách 3 tháng)                           │
│                                                                 │
│ 4. Lấy cao răng toàn hàm                                       │
│    - Lấy cao răng                           500,000 VNĐ         │
│    Số buổi: 1 buổi                                             │
│                                                                 │
│ TỔNG Dự TOÁN:                          26,150,000 VNĐ         │
│ Thời gian hoàn thành: 4-6 tháng                                │
└────────────────────────────────────────────────────────────────┘
```

**Database Structure:**

#### Table: `treatment_plans`
```sql
{
  id: 1,
  patient_id: 123,
  doctor_id: 5,
  branch_id: 1,
  title: "Kế hoạch điều trị toàn diện - Nguyễn Văn A",
  notes: "Bệnh nhân sức khỏe tốt, không dị ứng thuốc",
  total_estimated_cost: 26150000, // Tổng dự toán
  total_cost: 0, // Tổng thực tế (cập nhật khi thực hiện)
  status: 'approved', // draft → approved → in_progress → completed
  approved_by: 1, // Admin/Manager
  approved_at: '2025-10-31 10:00:00',
  created_at: '2025-10-31 09:30:00'
}
```

#### Table: `plan_items` (Chi tiết từng dịch vụ)
```sql
[
  {
    id: 1,
    treatment_plan_id: 1,
    service_id: 10, // FK → services (Lấy tủy)
    tooth_number: "16", // Răng số 16
    description: "Điều trị nội nha răng số 16",
    estimated_cost: 1000000,
    actual_cost: 0, // Sẽ cập nhật khi thực hiện
    quantity: 1,
    status: 'pending' // pending → in_progress → completed
  },
  {
    id: 2,
    treatment_plan_id: 1,
    service_id: 15, // Nhổ răng khôn
    tooth_number: "48",
    description: "Nhổ răng khôn mọc lệch",
    estimated_cost: 1650000,
    actual_cost: 0,
    quantity: 1,
    status: 'pending'
  },
  {
    id: 3,
    treatment_plan_id: 1,
    service_id: 20, // Implant
    tooth_number: "36",
    description: "Cấy Implant Osstem + Răng sứ",
    estimated_cost: 23000000,
    actual_cost: 0,
    quantity: 1,
    status: 'pending'
  },
  {
    id: 4,
    treatment_plan_id: 1,
    service_id: 8, // Lấy cao răng
    tooth_number: "toàn hàm",
    description: "Lấy cao răng toàn hàm",
    estimated_cost: 500000,
    actual_cost: 0,
    quantity: 1,
    status: 'pending'
  }
]
```

**🎯 Key Points:**
- Mỗi `plan_item` = 1 dịch vụ cụ thể
- `tooth_number` quan trọng cho dental (răng số 16, 17, 36, 48...)
- `estimated_cost` vs `actual_cost` → Track budget vs actual
- `status` riêng cho từng item → Flexible workflow

---

### **Bước 3: Thực hiện Điều trị (Treatment Sessions)**

**Kịch bản thực tế:**

#### **Buổi 1 - Ngày 01/11/2025: Lấy cao răng + Bắt đầu điều trị nội nha**

```
09:00 - Bệnh nhân check-in
09:15 - Bác sĩ A bắt đầu lấy cao răng
09:45 - Hoàn thành lấy cao răng
10:00 - Chụp X-quang răng 16
10:15 - Bắt đầu lấy tủy răng 16
11:00 - Hoàn thành, đặt thuốc tạm
11:15 - Kê đơn thuốc, hẹn tái khám sau 1 tuần
```

**Database:**

#### Table: `treatment_sessions`
```sql
{
  id: 1,
  treatment_plan_id: 1,
  plan_item_id: 4, // Lấy cao răng
  doctor_id: 5,
  start_at: '2025-11-01 09:15:00',
  end_at: '2025-11-01 09:45:00',
  performed_at: '2025-11-01 09:45:00',
  diagnosis: "Cao răng nhiều, viêm nướu nhẹ",
  procedure: "Lấy cao răng toàn hàm bằng máy siêu âm",
  images: [
    "/storage/treatments/session1_before.jpg",
    "/storage/treatments/session1_after.jpg"
  ],
  notes: "Bệnh nhân chịu đựng tốt, không xuất huyết bất thường",
  status: 'done',
  created_at: '2025-11-01 09:45:00'
}

{
  id: 2,
  treatment_plan_id: 1,
  plan_item_id: 1, // Điều trị nội nha răng 16
  doctor_id: 5,
  start_at: '2025-11-01 10:00:00',
  end_at: '2025-11-01 11:00:00',
  performed_at: '2025-11-01 11:00:00',
  diagnosis: "Sâu răng sâu, viêm tủy",
  procedure: "Lấy tủy 3 ống tủy, đặt thuốc diệt khuẩn, trám tạm",
  images: [
    "/storage/treatments/xray_16_before.jpg",
    "/storage/treatments/xray_16_treatment.jpg"
  ],
  notes: "Đã gây tê, lấy tủy thành công. Hẹn 1 tuần sau để trám vĩnh viễn",
  status: 'follow_up', // Chưa xong, cần tái khám
  created_at: '2025-11-01 11:00:00'
}
```

#### Table: `treatment_materials` (Vật tư sử dụng)
```sql
[
  // Session 1 - Lấy cao răng
  {
    id: 1,
    treatment_session_id: 1,
    material_id: 50, // FK → materials (Đầu lấy cao răng)
    quantity_used: 1,
    unit_price: 50000,
    total_price: 50000,
    notes: "Đầu lấy cao răng dùng 1 lần"
  },
  
  // Session 2 - Điều trị nội nha
  {
    id: 2,
    treatment_session_id: 2,
    material_id: 101, // Thuốc gây tê
    quantity_used: 1,
    unit_price: 30000,
    total_price: 30000
  },
  {
    id: 3,
    treatment_session_id: 2,
    material_id: 102, // Lưỡi mài nội nha
    quantity_used: 3,
    unit_price: 20000,
    total_price: 60000
  },
  {
    id: 4,
    treatment_session_id: 2,
    material_id: 103, // Thuốc diệt khuẩn
    quantity_used: 1,
    unit_price: 80000,
    total_price: 80000
  },
  {
    id: 5,
    treatment_session_id: 2,
    material_id: 104, // Trám tạm
    quantity_used: 1,
    unit_price: 50000,
    total_price: 50000
  }
]
```

**Auto-update khi lưu session:**
```javascript
// Pseudo-code trong Observer hoặc Model Event
TreatmentSession::saved(function($session) {
  // Tính tổng chi phí vật tư
  $materialsCost = $session->materials()->sum('total_price'); // 270,000
  
  // Cộng vào plan_item.actual_cost
  if ($session->plan_item_id) {
    $session->planItem->increment('actual_cost', $materialsCost);
  }
  
  // Cập nhật treatment_plan.total_cost
  $session->treatmentPlan->total_cost = $session->treatmentPlan->planItems->sum('actual_cost');
  $session->treatmentPlan->save();
});
```

---

#### **Buổi 2 - Ngày 08/11/2025: Hoàn thành điều trị nội nha răng 16**

```sql
{
  id: 3,
  treatment_plan_id: 1,
  plan_item_id: 1, // Cùng plan_item với buổi 1
  doctor_id: 5,
  start_at: '2025-11-08 14:00:00',
  end_at: '2025-11-08 15:00:00',
  performed_at: '2025-11-08 15:00:00',
  diagnosis: "Răng đã hết đau, sẵn sàng trám vĩnh viễn",
  procedure: "Bơm thuốc vào ống tủy, trám composite chính thức",
  images: [
    "/storage/treatments/xray_16_final.jpg",
    "/storage/treatments/tooth_16_completed.jpg"
  ],
  notes: "Hoàn thành điều trị nội nha. Răng ổn định.",
  status: 'done',
  created_at: '2025-11-08 15:00:00'
}
```

**Update:**
```javascript
// plan_items.id = 1
{
  status: 'completed', // ✅ Đã xong
  actual_cost: 1000000 + 270000 = 1270000 // Service + Materials
}

// treatment_plans.id = 1
{
  status: 'in_progress', // Vẫn đang làm các item khác
  total_cost: 1270000 + ... // Cộng dồn
}
```

---

### **Bước 4: Phát hành Hóa đơn (Invoices)**

**Có 2 cách phát hành hóa đơn:**

#### **Cách 1: Invoice theo từng buổi (Session-based)**
→ Phù hợp với thanh toán từng lần

```sql
// Invoice cho Buổi 1
{
  id: 1,
  treatment_session_id: 1, // Lấy cao răng
  treatment_plan_id: 1,
  patient_id: 123,
  invoice_no: 'INV-2025-001',
  total_amount: 500000, // plan_item.estimated_cost
  status: 'issued',
  created_at: '2025-11-01 11:30:00'
}

// Invoice cho Buổi 2
{
  id: 2,
  treatment_session_id: 2, // Điều trị nội nha lần 1
  treatment_plan_id: 1,
  patient_id: 123,
  invoice_no: 'INV-2025-002',
  total_amount: 500000 + 270000, // Service + materials
  status: 'paid',
  created_at: '2025-11-01 11:30:00'
}
```

#### **Cách 2: Invoice theo Plan Item (Service-based)**
→ Phù hợp với gói điều trị trả góp

```sql
// Invoice cho toàn bộ Điều trị nội nha răng 16
{
  id: 3,
  treatment_session_id: null, // Không liên kết với session cụ thể
  treatment_plan_id: 1,
  patient_id: 123,
  invoice_no: 'INV-2025-003',
  total_amount: 1270000, // Tổng actual_cost của plan_item.id=1
  status: 'issued',
  created_at: '2025-11-08 15:30:00'
}
```

#### **Cách 3: Invoice tổng cho toàn bộ Treatment Plan**
→ Phù hợp với thanh toán 1 lần sau khi hoàn thành

```sql
// Sau khi hoàn thành TẤT CẢ các plan_items
{
  id: 4,
  treatment_session_id: null,
  treatment_plan_id: 1,
  patient_id: 123,
  invoice_no: 'INV-2025-100',
  total_amount: 26150000, // treatment_plan.total_cost
  status: 'issued',
  created_at: '2025-12-01 10:00:00'
}
```

---

### **Bước 5: Thanh toán (Payments)**

**Kịch bản thực tế:**

#### **Thanh toán từng đợt:**
```sql
[
  // Thanh toán Invoice 1
  {
    id: 1,
    invoice_id: 1,
    patient_id: 123,
    amount: 500000,
    payment_method: 'cash',
    payment_date: '2025-11-01',
    notes: "Thanh toán tiền mặt sau buổi lấy cao răng"
  },
  
  // Thanh toán Invoice 2 (trả 1 phần)
  {
    id: 2,
    invoice_id: 2,
    patient_id: 123,
    amount: 500000,
    payment_method: 'card',
    payment_date: '2025-11-01',
    notes: "Thanh toán thẻ, còn nợ 270,000"
  },
  {
    id: 3,
    invoice_id: 2,
    patient_id: 123,
    amount: 270000,
    payment_method: 'transfer',
    payment_date: '2025-11-05',
    notes: "Chuyển khoản thanh toán phần còn lại"
  }
]
```

**Auto-update Invoice status:**
```javascript
Payment::created(function($payment) {
  $invoice = $payment->invoice;
  $totalPaid = $invoice->payments()->sum('amount');
  
  if ($totalPaid >= $invoice->total_amount) {
    $invoice->status = 'paid'; // Đã thanh toán đủ
  } else if ($totalPaid > 0) {
    $invoice->status = 'partial'; // Thanh toán 1 phần
  }
  
  $invoice->save();
});
```

---

## 📊 Complete Flow Diagram

```
PATIENT ARRIVES
      │
      ▼
┌─────────────────┐
│  APPOINTMENT    │ status: pending → done
└─────────────────┘
      │
      ▼
┌─────────────────┐
│ DIAGNOSIS       │ Doctor khám, chẩn đoán
│ (Notes)         │
└─────────────────┘
      │
      ▼
┌─────────────────────────────────────────────────────┐
│           TREATMENT PLAN                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │ Plan Item 1 │  │ Plan Item 2 │  │ Plan Item 3 │ │
│  │ Lấy tủy 16  │  │ Nhổ răng 48 │  │ Implant 36  │ │
│  │ 1,000,000   │  │ 1,650,000   │  │ 23,000,000  │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
│                                                      │
│  Status: draft → approved → in_progress → completed │
└─────────────────────────────────────────────────────┘
      │                    │                    │
      ▼                    ▼                    ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  SESSION 1   │    │  SESSION 2   │    │  SESSION 3   │
│  Lấy tủy     │    │  Trám vĩnh   │    │  Nhổ răng    │
│  + Materials │    │  + Materials │    │  + Materials │
└──────────────┘    └──────────────┘    └──────────────┘
      │                    │                    │
      ▼                    ▼                    ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  INVOICE 1   │    │  INVOICE 2   │    │  INVOICE 3   │
│  500,000     │    │  770,000     │    │  1,650,000   │
└──────────────┘    └──────────────┘    └──────────────┘
      │                    │                    │
      ▼                    ▼                    ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  PAYMENT 1   │    │  PAYMENT 2   │    │  PAYMENT 3   │
│  Cash        │    │  Card        │    │  Transfer    │
└──────────────┘    └──────────────┘    └──────────────┘
```

---

## 🎯 Business Rules

### **Treatment Plan Approval:**
1. Doctor tạo plan → `status='draft'`
2. Manager/Admin review → Approve → `status='approved'`
3. Khi bắt đầu session đầu tiên → `status='in_progress'`
4. Khi tất cả plan_items completed → `status='completed'`

### **Plan Item Status:**
- `pending` - Chưa bắt đầu
- `in_progress` - Đang thực hiện (có session nhưng chưa xong)
- `completed` - Đã hoàn thành
- `cancelled` - Hủy (bệnh nhân không muốn làm nữa)

### **Invoice Generation:**
**Option A: Auto-generate sau mỗi session**
```php
TreatmentSession::created(function($session) {
  if ($session->status === 'done') {
    Invoice::create([
      'treatment_session_id' => $session->id,
      'treatment_plan_id' => $session->treatment_plan_id,
      'patient_id' => $session->treatmentPlan->patient_id,
      'invoice_no' => generateInvoiceNumber(),
      'total_amount' => calculateSessionCost($session),
      'status' => 'issued'
    ]);
  }
});
```

**Option B: Manual - Staff tạo invoice khi cần**
- Linh hoạt hơn cho trường hợp thanh toán trả góp
- Có thể gộp nhiều sessions vào 1 invoice

### **Payment Tracking:**
```php
// Check invoice payment status
$invoice = Invoice::find(1);
$totalPaid = $invoice->payments()->sum('amount');
$remaining = $invoice->total_amount - $totalPaid;

if ($remaining > 0) {
  echo "Còn nợ: " . number_format($remaining) . " VNĐ";
}
```

---

## 🏆 Best Practices từ các hệ thống thực tế

### **1. DentalCare Pro (USA)**
- Invoice per session
- Allow partial payments
- Auto-send payment reminders via SMS
- Insurance integration

### **2. NhaSach Dental System (Vietnam)**
- Treatment plan với discount cho gói
- Thanh toán trả góp 0% (6-12 tháng)
- Invoice tổng sau khi hoàn thành plan
- Tích hợp VNPay/MoMo

### **3. SmileSoft (Australia)**
- Estimated vs Actual cost tracking
- Material inventory auto-deduction
- Session appointment auto-scheduling
- Patient portal để xem invoice online

---

## 💡 Recommendations cho CRM của bạn

### **Immediate (Phase 1):**
1. ✅ Treatment Plan với Plan Items
2. ✅ Treatment Sessions với Materials tracking
3. ✅ Invoice generation (manual hoặc auto)
4. ✅ Payment tracking với status updates

### **Future Enhancements (Phase 2):**
1. 🔄 Auto-schedule next session based on plan
2. 🔄 SMS reminder trước appointment
3. 🔄 Patient portal để xem treatment history
4. 🔄 Discount/Promotion system
5. 🔄 Insurance claim integration
6. 🔄 Installment payment plans
7. 🔄 Inventory auto-deduction khi dùng materials

### **Advanced (Phase 3):**
1. 🔄 AI suggest treatment plans based on diagnosis
2. 🔄 Revenue forecasting per treatment plan
3. 🔄 Doctor performance analytics
4. 🔄 Patient satisfaction surveys after sessions
5. 🔄 Referral bonus program

---

## 🛠️ Implementation Checklist

### Database Schema:
- [x] `treatment_plans` table exists
- [x] `plan_items` table exists
- [x] `treatment_sessions` table exists
- [x] `treatment_materials` table exists
- [x] `invoices` table exists
- [x] `payments` table exists
- [x] Foreign keys properly set up

### Models & Relationships:
- [ ] TreatmentPlan hasMany PlanItems
- [ ] TreatmentPlan hasMany TreatmentSessions
- [ ] TreatmentPlan hasMany Invoices
- [ ] TreatmentSession belongsTo PlanItem
- [ ] TreatmentSession hasMany TreatmentMaterials
- [ ] Invoice hasMany Payments
- [ ] Auto-calculate total_cost on save

### Filament Resources:
- [ ] TreatmentPlanResource with PlanItems relation manager
- [ ] TreatmentSessionResource with Materials relation manager
- [ ] InvoiceResource with Payments relation manager
- [ ] Dashboard widgets: Revenue, Pending treatments, Overdue invoices

### Business Logic:
- [ ] Observer: Auto-update plan total_cost when session saved
- [ ] Observer: Auto-update invoice status when payment received
- [ ] Validation: Cannot complete plan if items pending
- [ ] Notification: Send SMS when invoice issued
- [ ] Notification: Send reminder for overdue payments

---

**Next Step:** Bạn muốn tôi implement phần nào trước? 🚀
1. Relation Managers cho Treatment Plan → Plan Items?
2. Session creation với material tracking?
3. Invoice generation logic?
4. Payment tracking system?

