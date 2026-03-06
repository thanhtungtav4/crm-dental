# Metadata

- Module code: `PAT`
- Module name: `Customers / Patients / MPI`
- Current status: `In Fix`
- Current verdict: `D`
- Review file: `docs/reviews/modules/PAT-customers-patients.md`
- Issue file: `docs/issues/PAT-issues.md`
- Plan file: `docs/planning/PAT-plan.md`
- Issue ID prefix: `PAT-`
- Task ID prefix: `TASK-PAT-`
- Dependencies: `GOV, APPT, CLIN, FIN, CARE, ZNS`
- Last updated: `2026-03-06`

# Scope

- Review module `PAT` theo 4 lop: architecture, database, domain logic, UI/UX.
- Trong pham vi review: `customers`, `patients`, `master_patient_identities`, `master_patient_duplicates`, `master_patient_merges`, `patient_contacts`, `patient_medical_records`, `patient_wallets`, `emr_patient_maps`, observer/service/resource/page/form/test lien quan.
- Flow chinh duoc xem xet:
  - tao lead/customer
  - convert customer -> patient
  - tao patient truc tiep
  - MPI sync / duplicate detection / merge / rollback
  - truy cap patient workspace va EMR entry point

# Context

- He thong la CRM phong nha khoa Laravel 12 + Filament 4, da chi nhanh, du lieu PII va PHI rat nhay cam.
- `PAT` la module nen cho patient ownership, patient identity, customer-to-patient conversion va MPI duplicate control.
- GOV da dat clean baseline, nen review nay danh gia `PAT` tren gia dinh branch scoping / RBAC nen da duoc siet lai.
- Thong tin con thieu lam giam do chinh xac review:
  - chua co quy trinh nghiep vu chinh thuc cho lead bi dedupe vao patient da ton tai
  - chua co retention/chinh sach masking cho customer PII truoc khi convert
  - chua thay Filament UI rieng cho queue MPI duplicate review

# Executive Summary

🚨 CANH BAO NGHIEM TRONG

- `customers` dang luu PII o dang plaintext va validate/search truc tiep tren cot raw, trong khi `patients` da duoc harden bang encrypted cast + search hash.
- `PatientConversionService::convert()` co race window ro rang truoc transaction, co the tao duplicate patient hoac va cham unique theo customer khong duoc idempotent hoa.
- Cac selector nhan su quan trong (`assigned_to`, `owner_staff_id`, `primary_doctor_id`) chua branch-scoped, mo ra nguy co gan sai nguoi giua cac chi nhanh.

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`, chua dat baseline an toan cho patient identity boundary
- Diem tot dang ke:
  - `patients` da co encrypted cast cho PII va search hash cho phone/email
  - patient/customer resource da co branch-scoped query va policy co branch awareness
  - MPI merge/rollback da co transaction, row lock va audit trail

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - `app/Models/Customer.php:16-44`
  - `app/Filament/Schemas/SharedSchemas.php:24-41`
  - `app/Filament/Resources/Patients/Schemas/PatientForm.php:204-216`
  - `app/Filament/Resources/Customers/Schemas/CustomerForm.php:75-79`
- Findings:
  - `Customer` khong co encrypted cast cho `phone`, `email`, `address`, mac du day van la PII nhay cam truoc giai doan convert thanh patient.
  - `SharedSchemas::customerProfileFields()` validate uniqueness tren cot raw `customers.phone`, phu thuoc vao du lieu plaintext o DB.
  - `assigned_to`, `primary_doctor_id`, `owner_staff_id` chua duoc loc theo branch scope, chi dua vao relationship chung.
- Suggested direction:
  - Hardening `Customer` giong `Patient`: encrypted cast + search hash/normalized hash cho lookup.
  - Tach search/index strategy khoi raw PII columns.
  - Loc selector nhan su theo `BranchAccess` + doctor branch assignment service, va sanitize server-side.

## Data Integrity & Database

- Danh gia: `Trung binh`
- Evidence:
  - Schema `patients`, `customers`, `master_patient_identities`, `master_patient_duplicates`, `master_patient_merges`
  - `app/Models/Patient.php:256-324`
  - `app/Services/MasterPatientIndexService.php:17-315`
- Findings:
  - Diem tot:
    - `patients.customer_id` unique
    - `patients.patient_code` unique
    - `master_patient_identities` co unique `(patient_id, identity_type, identity_hash)`
    - `master_patient_duplicates` co unique `(identity_type, identity_hash, status)`
  - Diem yeu:
    - `customers` khong co DB-level invariant cho identity quan trong nhu `phone_normalized` theo branch hoac `identification_hash`.
    - Mien du lieu customer va patient su dung 2 chien luoc identity khac nhau: customer dung raw/normalized, patient dung encrypted + hash.
    - Rule uniqueness phone patient theo branch dang chi duoc enforce o validation layer, khong co unique/composite guard o DB.
- Suggested direction:
  - Chuan hoa identity strategy customer/patient qua hash columns va normalized value policy.
  - Xem xet composite uniqueness hop ly cho `customers` va/hoac idempotency lock table cho conversion.
  - Tach customer identity integrity khoi validation UI.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - `app/Services/PatientConversionService.php:21-91`
  - `tests/Feature/CustomerConversionTest.php:33-112`
  - `app/Observers/AppointmentObserver.php:118-138`
- Findings:
  - `convert()` check `customer->patient`, `Patient::where(customer_id)` va dedupe theo phone truoc khi vao transaction.
  - 2 lead cung phone trong cung branch co the cung convert dong thoi va tao 2 patient vi khong co row lock hay DB invariant cho phone hash + branch.
  - Flow auto-convert tu `AppointmentObserver` co the va cham voi convert tay tu UI, nhung khong co idempotency guard ro rang.
- Suggested direction:
  - Bao boi toan bo conversion flow trong transaction + `lockForUpdate()` tren `customers` va candidate `patients`.
  - Them idempotency strategy cho convert theo `customer_id` va theo identity hash/branch.
  - Giu logic link appointment nam trong service sau khi lock xong.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Services/PatientConversionService.php:143-170`
  - `app/Filament/Resources/Patients/Pages/ViewPatient.php:86-220`
- Findings:
  - `findByPhoneAndClinic()` co fallback load tat ca patient co phone trong branch roi normalize so sanh bang PHP. Day la O(n) theo branch, va voi encrypted cast se ton chi phi giai ma tren tung record.
  - Patient workspace co nhieu counter va relation load, nhung da gioi han mot so query bang `loadCount()` va `limit()`; do do chua phai bottleneck nghiem trong nhat cua PAT.
- Suggested direction:
  - Bo fallback scan branch-level trong conversion service; dua lookup ve hash/normalized columns co index.
  - Neu can tolerant matching, dua thanh background duplicate detection/MPI, khong de tren hot path convert.

## Maintainability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Models/Patient.php:268-293`
  - `app/Observers/PatientObserver.php:13-82`
  - `app/Services/MasterPatientMergeService.php:60-260`
- Findings:
  - Diem tot:
    - MPI merge service co transaction, lock, audit, rollback history.
    - `PatientObserver` da dong bo MPI va EMR sync co y thuc event boundary.
  - Diem yeu:
    - `Patient::creating()` auto-create `Customer` ngay trong model event, lam boundary giua onboarding patient va lead management tro nen mo ho.
    - Customer/PAT identity logic dang bi phan tan giua model event, observer, form validation, service va web lead ingestion.
- Suggested direction:
  - Tao `PatientOnboardingService` / `CustomerPatientLinkService` lam boundary duy nhat cho create patient / convert lead / auto-create customer.
  - Giam side effect trong model event, dua ve service transaction ro rang.

## Better Architecture Proposal

- Tach `PAT` thanh 4 boundary ro rang:
  - `CustomerPiiProtection` cho encryption, hash, search strategy
  - `PatientOnboardingService` cho create patient / convert customer / link appointment
  - `MasterPatientIndexService` cho duplicate detection bat dong bo hoac explicit sync
  - `MasterPatientReviewWorkflow` cho merge/rollback + UI queue review
- Muc tieu kien truc:
  - 1 source of truth cho identity hash
  - 1 transaction boundary cho conversion/onboarding
  - 1 delegated review flow cho MPI thay vi dua nhieu vao artisan command

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Trung binh`
- Workflow hien tai:
  - tao `Customer` lead
  - tao lich hen / cham soc lead
  - convert thanh `Patient` thu cong hoac auto-convert khi appointment completed
  - MPI sync identity khi patient thay doi
  - merge / rollback duplicate patient qua command
- Nhan xet:
  - flow patient workspace va MPI merge da co nen tang tot.
  - flow `Customer -> Patient` chua co behavior nhat quan khi lead moi bi dedupe vao patient cu.

## State transitions

- Danh gia: `Trung binh`
- Hien co:
  - `Customer.status`: `lead`, `contacted`, `confirmed`, `converted`, `lost`
  - `Patient.status`: `active`, `inactive`, `blocked`
  - `MasterPatientDuplicate.status`: `open`, `resolved`, `ignored`
  - `MasterPatientMerge.status`: `applied`, `rolled_back`
- Van de:
  - Khong co state machine ro rang cho `Customer -> Patient conversion`.
  - Neu customer bi dedupe vao patient khac, status customer co the van la `lead`, nhung UX/ops khong noi ro tiep theo phai lam gi.

## Missing business rules

- Chua co quy tac chinh thuc cho `lead bi dedupe vao patient hien co`:
  - co doi status sang `converted` / `linked` / `duplicate` hay khong
  - co can khoa chinh sua lead goc hay khong
- Chua co quy tac branch ownership ro rang khi customer branch khac patient branch trong luong convert qua appointment.
- Chua co quy tac ai review MPI duplicate case qua UI; hien chi thay command va service permission nhay cam.

## Invalid states / forbidden transitions

- Hai customer cung phone trong cung branch cung convert dong thoi -> co the tao duplicate patient.
- Auto-convert tu appointment va convert tay tu UI cung luc -> link patient/appointment khong idempotent.
- Staff/doctor ngoai branch duoc gan vao patient/customer -> state nghiep vu sai scope.
- Patient tao truc tiep khong qua service onboarding -> side effect tao customer xay ra ma khong ro workflow owner.

## Service / action / state machine / transaction boundary de xuat

- Tao `CustomerToPatientConversionService` version harden:
  - lock customer
  - lock candidate patient theo identity hash/branch
  - idempotent on `customer_id`
  - link appointment trong cung transaction
- Tao `CustomerIdentityService`:
  - normalize/hash/search cho `phone`, `email`, `cccd`, `identification_hash`
- Tao `PatientOnboardingService`:
  - xu ly create patient truc tiep va tao customer companion mot cach transaction-safe
- Tao `MpiReviewService` / UI queue:
  - review duplicate case
  - merge
  - rollback
  - audit actor ro rang

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Diem tot:
  - Patient workspace trong `ViewPatient` co tabs ro, CTA medical record hop ly, va da co PHI access audit cho tab nhay cam.
  - Customer/Patient list deu sort theo `created_at desc`, hop voi van hanh le tan.
- Friction chinh:
  - Action `convertToPatient` co the tra ve patient da ton tai, nhung customer nguon van de o status `lead`, de gay nham cho le tan.
  - Chua thay queue UI ro rang cho MPI duplicate review; operational flow hien nghieng ve command/tooling.

## Filament UX

- Danh gia: `Trung binh`
- Diem tot:
  - Patient workspace giau thong tin va co tab counters.
  - Medical record CTA trong patient workspace da co test va branch-scoped access.
- Diem yeu:
  - Customer form dung profile fields plaintext, khong cho user biet muc do nhay cam hay uniqueness strategy theo branch.
  - `assigned_to`, `owner_staff_id`, `primary_doctor_id` khong scope theo branch tren dropdown.
  - Conversion UX chua noi ro truong hop `da lien ket vao ho so benh nhan hien co` se xu ly lead goc ra sao.

## Edge cases quan trong

- 2 le tan cung convert cung 1 customer gan nhu dong thoi.
- 2 lead khac nhau cung so dien thoai trong cung branch cung convert dong thoi.
- Appointment auto-complete trigger convert trong luc user dang bam `Xac nhan thanh benh nhan`.
- Lead branch A duoc convert thanh patient branch B qua appointment branch context.
- Soft-deleted patient co cung phone/email gay block create patient moi, nhung UX khong huong dan restore/merge.
- Gan `primary_doctor_id` hoac `assigned_to` sang user ngoai branch dang phu trach.
- Patient tao truc tiep that bai sau khi model event da tao customer companion.
- MPI merge rollback sau khi phat sinh them record moi tren canonical patient.

## Diem de thao tac sai

- Dropdown nhan su khong branch-scoped de gay nham nguoi phu trach.
- Lead dedupe thanh patient cu nhung status lead van de nguyen, de nguoi dung tiep tuc cham soc sai doi tuong.
- Validation customer phone unique tren raw phone de gay nham voi cac so co format khac nhau nhung cung gia tri normalize.

## De xuat cai thien UX

- Hien thong diep ro rang trong action convert:
  - `Da tao benh nhan moi`
  - `Da lien ket vao ho so benh nhan hien co`
  - `Lead nay da duoc xu ly, mo ho so nao tiep theo`
- Scope dropdown nhan su theo branch va them helper text ve pham vi.
- Neu patient/lead trung identity, dua user toi queue review hoac modal xac nhan thay vi silent dedupe.
- Bo sung MPI review page/read model cho role duoc cap phep, thay vi dua vao artisan command.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| PAT-001 | Critical | Security | Customer PII dang luu plaintext va searchable tren cot raw | Resolved | `Customer` da duoc harden voi encrypted cast + search hash; lookup/validation da tach khoi cot raw. |
| PAT-002 | Critical | Concurrency | Customer->Patient conversion khong idempotent duoi concurrent traffic | Resolved | Conversion da vao transaction + row lock + idempotent retry path cho same customer va duplicate leads cung identity. |
| PAT-003 | High | Security | Staff/doctor selector trong customer/patient form chua branch-scoped | Resolved | Form options va save guards da scope theo branch cho assignee/owner/doctor, request forged cung bi chan. |
| PAT-004 | High | Data Integrity | Customer va Patient dang dung 2 chien luoc identity khac nhau | Resolved | Customer, Patient, MPI va web lead ingestion da dung chung normalizer/hash contract cho identity hot path. |
| PAT-005 | High | Performance | Conversion dedupe fallback scan toan bo patient trong branch bang PHP | Resolved | Hot path conversion da bo full-scan bang PHP, chi con hash/index lookup. |
| PAT-006 | Medium | Maintainability | Patient model auto-create Customer trong model event | Open | Side effect onboarding nam o model layer, kho audit transaction boundary va kho test rollback/orphan path. |
| PAT-007 | Medium | Domain Logic | MPI duplicate review workflow chua co UI nghiep vu ro rang | Open | Hien chi thay command/service, de tao do tre van hanh va kho xu ly queue duplicate cho user khong ky thuat. |
| PAT-008 | Medium | Maintainability | Test coverage chua khoa regression cho PII, conversion race va branch-scoped selectors | Partial | Regression da khoa cho PII, conversion, branch-scoped assignment va shared normalizer; onboarding/MPI UI flow van chua co coverage day du. |

# Dependencies

- `GOV`: branch scoping va RBAC nen da la baseline cho PAT.
- `APPT`: auto-convert khi appointment completed va patient ownership theo branch.
- `CLIN`: medical record / PHI access phu thuoc patient identity boundary dung.
- `FIN`: wallet, invoice, payment deu dua tren patient canonical identity.
- `CARE`, `ZNS`: follow-up va outbound messaging can dung customer/patient ownership va identity.

# Open Questions

- Sau khi lead bi dedupe vao patient da ton tai, nghiep vu muon lead do chuyen thanh status nao?
- Co can unique patient identity theo toan he thong hay theo branch trong tung giai doan?
- Co muon MPI duplicate review co Filament UI rieng hay tiep tuc giu command-only cho admin?
- Customer PII co can cung muc do ma hoa/hardening nhu patient ngay tu lead stage hay co policy khac?

# Recommended Next Steps

- `TASK-PAT-001` den `TASK-PAT-004` da pass full suite; tiep tuc `TASK-PAT-005` de dua patient onboarding ve service boundary ro rang.
- Sau do lam `TASK-PAT-006` de co MPI review workflow cho operator truoc khi goi PAT la clean baseline.
- Co the bat dau review `APPT` song song, nhung chua nen re-audit chot PAT truoc khi xong `TASK-PAT-005` va `TASK-PAT-006`.

# Current Status

- In Fix
