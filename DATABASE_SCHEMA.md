# 📊 Database Schema Map (Current)

> **Last updated:** 2026-02-28  
> **Source of truth:** `database/migrations/*` (ưu tiên migration mới nhất khi có khác biệt).

Tài liệu này mô tả schema theo **domain nghiệp vụ** để bám sát hệ thống hiện tại, thay vì liệt kê chi tiết cột theo từng bảng.

---

## 1) Multi-branch & Identity

- `branches`
- `users`
- `model_has_roles`, `model_has_permissions`, `roles`, `permissions`, `role_has_permissions`
- `passkeys`
- `personal_access_tokens`

**Mục tiêu:** tách ngữ cảnh chi nhánh + xác thực + phân quyền hành động.

---

## 2) CRM / Lead / Patient Master Data

- `customers`
- `patients`
- `customer_interactions`
- `appointment_reminders`
- `web_lead_ingestions`
- `master_patient_identities`
- `master_patient_duplicates`
- `master_patient_merges`
- `duplicate_detections`
- `record_merges`
- `identification_logs`

**Mục tiêu:** chuẩn hóa vòng đời lead → patient, chống trùng hồ sơ, hỗ trợ merge có truy vết.

---

## 3) Appointments & Clinical Runtime

- `appointments`
- `visit_episodes`
- `clinical_notes`
- `patient_medical_records`
- `patient_photos`
- `tooth_conditions`
- `patient_tooth_conditions`
- `disease_groups`
- `diseases`
- `consents`

**Mục tiêu:** quản lý khám/điều trị theo episode, biểu mẫu lâm sàng, consent gate và theo dõi tình trạng răng.

---

## 4) Treatment Planning & Execution

- `services`
- `service_categories`
- `treatment_plans`
- `plan_items`
- `treatment_sessions`
- `treatment_materials`

**Mục tiêu:** lập kế hoạch điều trị, approval lifecycle cho hạng mục, theo dõi thực thi thực tế theo phiên.

---

## 5) Inventory & Supply

- `materials`
- `material_batches`
- `suppliers`
- `inventory_transactions`

**Mục tiêu:** theo dõi tồn kho, lô hàng, hạn dùng, xuất dùng vào phiên điều trị.

---

## 6) Billing, Payment & Finance Control

- `invoices`
- `payments`
- `installment_plans`
- `payment_reminders`
- `receipts`
- `expenses`
- `insurance_claims`

**Mục tiêu:** vòng đời hóa đơn/thanh toán, trả góp, hoàn tiền, và hạch toán theo branch context.

---

## 7) Operations, Audit, Analytics, Automation

- `audit_logs`
- `branch_logs`
- `report_snapshots`
- `operational_kpi_alerts`
- `branch_overbooking_policies`
- `branch_transfer_requests`
- `doctor_branch_assignments`
- `clinic_settings`
- `clinic_setting_logs`
- `notes`
- `notifications`
- `recall_rules`
- `patient_loyalties`
- `patient_loyalty_transactions`
- `patient_risk_profiles`

**Mục tiêu:** kiểm soát vận hành, snapshot báo cáo có lineage, cấu hình runtime và automation chăm sóc.

---

## 8) Integration / EMR Sync

- `emr_sync_events`
- `emr_sync_logs`
- `emr_patient_maps`
- `master_data_sync_logs`

**Mục tiêu:** đồng bộ liên hệ thống, quan sát pipeline và truy vết mapping dữ liệu.

---

## 9) Framework/System Tables

- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`
- `breezy_sessions`
- `migrations`

---

## Ghi chú vận hành schema

- Có nhiều migration hardening trong 2026 cho: state machine, idempotency, branch attribution, approval lifecycle, lineage snapshot.
- Khi cần đối soát chi tiết field/index/foreign key, ưu tiên đọc migration mới nhất liên quan domain.
- Với thay đổi schema mới, cập nhật đồng thời:
  1. migration,
  2. test feature liên quan,
  3. tài liệu này.
