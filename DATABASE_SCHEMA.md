# 📊 CRM Database Schema Review

> **Last Updated:** October 31, 2025  
> **Database:** MySQL 9.4.0  
> **Total Tables:** 35  
> **Total Size:** 1.53 MB

---

## 🎯 Core Business Logic

### **Lead → Customer → Patient Workflow**

```
┌─────────────┐     Convert      ┌──────────────┐
│  CUSTOMER   │ ──────────────>  │   PATIENT    │
│  (Lead)     │                   │  (Converted) │
└─────────────┘                   └──────────────┘
       │                                 │
       │ creates                         │
       ▼                                 ▼
┌─────────────┐                   ┌──────────────┐
│ APPOINTMENT │                   │ TREATMENT    │
│ (Scheduled) │                   │ PLAN         │
└─────────────┘                   └──────────────┘
       │                                 │
       │ status='done'                   │
       │ auto converts                   ▼
       └──────────────────────>   ┌──────────────┐
                                   │ TREATMENT    │
                                   │ SESSION      │
                                   └──────────────┘
```

---

## 📋 Table Structure

### 1. **Core Entities**

#### 🏢 **branches** (48 KB)
Chi nhánh phòng khám
```sql
- id
- name
- address
- phone
- email
- manager_id (FK → users)
- timestamps, soft_deletes
```

#### 👥 **users** (48 KB)
Nhân viên (Admin, Doctor, Receptionist)
```sql
- id
- name
- email
- password
- phone
- specialty (for doctors)
- branch_id (FK → branches)
- two_factor columns (Breezy)
- timestamps
```

#### 🔐 **passkeys** (32 KB)
WebAuthn passwordless authentication
```sql
- id
- user_id (FK → users)
- name
- credential_id
- public_key
- timestamps
```

---

### 2. **Lead Management**

#### 📞 **customers** (80 KB)
**Khách hàng tiềm năng / Lead**
```sql
- id
- branch_id (FK → branches, nullable)
- full_name
- phone
- email
- source: ENUM['walkin','facebook','zalo','referral','appointment','other']
- status: ENUM['lead','contacted','confirmed','converted','lost']
- created_by (FK → users)
- updated_by (FK → users)
- notes
- timestamps, soft_deletes
```

**Status Flow:**
- `lead` → Initial contact
- `contacted` → First follow-up
- `confirmed` → Scheduled appointment
- `converted` → Became patient ✅
- `lost` → Did not convert

---

### 3. **Patient Management**

#### 🏥 **patients** (96 KB)
**Bệnh nhân thực tế**
```sql
- id
- customer_id (FK → customers, nullable) ← Links to original lead
- patient_code (unique, e.g., BN000001)
- first_branch_id (FK → branches)
- full_name
- birthday
- gender: ENUM['male','female','other']
- phone
- email (added via migration)
- address
- medical_history
- created_by, updated_by (FK → users)
- timestamps, soft_deletes
```

#### 📅 **appointments** (80 KB)
**Lịch hẹn**
```sql
- id
- customer_id (FK → customers, nullable) ← NEW: For leads
- patient_id (FK → patients, nullable) ← Now nullable
- doctor_id (FK → users)
- branch_id (FK → branches)
- date (datetime)
- status: 'pending' | 'done' | 'canceled'
- note
- timestamps, soft_deletes
```

**⚠️ CRITICAL CHANGE:**
- Old: `patient_id` REQUIRED
- New: Either `customer_id` OR `patient_id` (or both)
- When `status='done'` + `customer_id` exists → Auto-create Patient via Observer

---

### 4. **Treatment Workflow**

#### 📋 **treatment_plans** (96 KB)
**Kế hoạch điều trị**
```sql
- id
- patient_id (FK → patients, CASCADE)
- doctor_id (FK → users)
- branch_id (FK → branches)
- title
- notes
- total_cost (actual accumulated)
- total_estimated_cost (planned)
- status: ENUM['draft','approved','in_progress','completed','cancelled']
- approved_by (FK → users)
- approved_at
- created_by, updated_by
- timestamps, soft_deletes
```

#### 🦷 **plan_items** (32 KB)
**Chi tiết dịch vụ trong kế hoạch**
```sql
- id
- treatment_plan_id (FK → treatment_plans, CASCADE)
- service_id (FK → services, nullable)
- tooth_number (e.g., "11", "21-23")
- description
- estimated_cost
- actual_cost
- quantity
- timestamps, soft_deletes
```

#### ⚕️ **treatment_sessions** (96 KB)
**Buổi điều trị thực tế**
```sql
- id
- treatment_plan_id (FK → treatment_plans, CASCADE)
- plan_item_id (FK → plan_items, nullable)
- doctor_id (FK → users)
- start_at, end_at (datetime)
- performed_at
- diagnosis
- procedure
- images (JSON)
- notes
- status: ENUM['scheduled','done','follow_up']
- created_by, updated_by
- timestamps, soft_deletes
```

#### 🧪 **treatment_materials** (64 KB)
**Vật tư sử dụng trong buổi điều trị**
```sql
- id
- treatment_session_id (FK → treatment_sessions, CASCADE)
- material_id (FK → materials)
- quantity_used
- unit_price
- total_price
- notes
- timestamps, soft_deletes
```

---

### 5. **Inventory & Billing**

#### 📦 **materials** (32 KB)
**Vật tư, thuốc**
```sql
- id
- name
- sku
- unit (e.g., "hộp", "chai")
- quantity_in_stock
- unit_price
- branch_id (FK → branches)
- timestamps, soft_deletes
```

#### 📊 **inventory_transactions** (80 KB)
**Lịch sử nhập/xuất kho**
```sql
- id
- material_id (FK → materials)
- branch_id (FK → branches)
- type: 'in' | 'out'
- quantity
- notes
- created_by
- timestamps
```

#### 💰 **invoices** (64 KB)
**Hóa đơn**
```sql
- id
- treatment_session_id (FK → treatment_sessions, nullable)
- treatment_plan_id (FK → treatment_plans, nullable)
- patient_id (FK → patients, nullable)
- invoice_no (unique)
- total_amount
- status: ENUM['draft','issued','partial','paid','cancelled']
- timestamps, soft_deletes
```

#### 💳 **payments** (48 KB)
**Thanh toán**
```sql
- id
- invoice_id (FK → invoices)
- patient_id (FK → patients)
- amount
- payment_method: 'cash' | 'card' | 'transfer'
- payment_date
- notes
- timestamps, soft_deletes
```

---

### 6. **Supporting Tables**

#### 📝 **notes** (64 KB)
**Ghi chú**
```sql
- id
- notable_type (polymorphic: Patient, Customer, etc.)
- notable_id
- customer_id (FK → customers, added via migration)
- content
- created_by (FK → users)
- timestamps, soft_deletes
```

#### 📊 **branch_logs** (80 KB)
**Nhật ký hoạt động chi nhánh**
```sql
- id
- branch_id (FK → branches)
- user_id (FK → users)
- action
- details
- timestamps
```

#### 🩺 **services** (16 KB)
**Danh mục dịch vụ**
```sql
- id
- name
- code
- price
- duration (minutes)
- description
- timestamps, soft_deletes
```

---

## 🔄 Recent Migrations (Critical Changes)

### ✅ **2025_10_30_233553** - Add 'appointment' to customers source enum
```sql
ALTER TABLE customers 
MODIFY COLUMN source ENUM('walkin','facebook','zalo','referral','appointment','other');
```
**Purpose:** Track leads created during appointment scheduling

### ✅ **2025_10_30_234618** - Add customer_id to appointments
```sql
ALTER TABLE appointments ADD customer_id (FK → customers, nullable);
ALTER TABLE appointments MODIFY patient_id nullable;
```
**Purpose:** Support appointments for Leads (not yet patients)

---

## 🚨 Important Business Rules

### 1. **Appointment Creation**
- ✅ Can create with `customer_id` only (Lead)
- ✅ Can create with `patient_id` only (existing patient)
- ⚠️ When appointment `status='done'` → Auto-convert Customer to Patient

### 2. **Patient Conversion (AppointmentObserver)**
When `appointment.status` changes to `'done'`:
1. Check if `customer_id` exists and `patient_id` is null
2. Check if Customer already has a Patient record
3. If not → Create Patient with:
   - `customer_id` link
   - `patient_code` auto-generated (BN000001, BN000002, ...)
   - `first_branch_id` from appointment
   - Copy customer details
4. Update `appointment.patient_id`
5. Update `customer.status = 'converted'`
6. Send notification

### 3. **Treatment Plans**
- Only for Patients (not Leads)
- Required: `patient_id`, `doctor_id`
- Status flow: `draft` → `approved` → `in_progress` → `completed`

### 4. **Invoices**
- Can link to:
  - Specific `treatment_session_id`
  - Entire `treatment_plan_id`
  - General `patient_id`

---

## 🔍 Database Integrity Checks

### Foreign Key Constraints:
✅ All FK properly constrained with `cascadeOnDelete` or `nullOnDelete`
✅ Soft deletes enabled on critical tables (data retention)

### Enum Values:
✅ `customers.source`: 6 values including 'appointment' ✨ NEW
✅ `customers.status`: 5 states for lead lifecycle
✅ `appointments.status`: 3 states (pending/done/canceled)
✅ `treatment_plans.status`: 5 states
✅ `treatment_sessions.status`: 3 states
✅ `invoices.status`: 5 states

### Indexes:
⚠️ **Recommendation:** Add indexes on:
- `appointments.date` (query by date range)
- `customers.phone` (search by phone)
- `patients.phone` (search by phone)
- `patients.patient_code` (already unique)

---

## 📈 Scalability Considerations

### Current Design Strengths:
✅ Soft deletes for data retention
✅ Audit trail (`created_by`, `updated_by`)
✅ Polymorphic relationships (notes)
✅ Flexible lead-to-patient conversion

### Potential Improvements:
🔄 Add `assigned_to` to `appointments` for task management
🔄 Add `reminder_sent_at` to `appointments` for SMS/email tracking
🔄 Add `discount_amount` and `tax_amount` to `invoices`
🔄 Add `priority` field to `treatment_plans`
🔄 Create `customer_interactions` table for detailed lead tracking

---

## 🛠️ Next Steps

### Immediate:
- [ ] Test appointment creation with Customer (Lead)
- [ ] Test auto-conversion when marking appointment as 'done'
- [ ] Test manual conversion button
- [ ] Verify notification system

### Future Enhancements:
- [ ] Add SMS/Email notification system
- [ ] Create dashboard widgets for lead conversion rates
- [ ] Implement duplicate phone number detection
- [ ] Add advanced reporting for revenue per branch
- [ ] Create patient medical history timeline view

---

## 📞 Support Tables (Laravel/Filament)

- `migrations` - Migration history
- `cache`, `cache_locks` - Cache system
- `sessions` - User sessions
- `jobs`, `job_batches`, `failed_jobs` - Queue system
- `personal_access_tokens` - API tokens (Sanctum)
- `breezy_sessions` - 2FA sessions (Filament Breezy)
- `password_reset_tokens` - Password resets
- `permissions`, `roles`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` - Spatie Permission

---

**Generated by:** GitHub Copilot  
**Date:** October 31, 2025
