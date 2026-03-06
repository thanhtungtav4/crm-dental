# Metadata

- Module code: `APPT`
- Module name: `Appointments / Calendar`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Review file: `docs/reviews/modules/APPT-appointments-calendar.md`
- Issue file: `docs/issues/APPT-issues.md`
- Plan file: `docs/planning/APPT-plan.md`
- Issue ID prefix: `APPT-`
- Task ID prefix: `TASK-APPT-`
- Dependencies: `GOV, PAT, CLIN, TRT, CARE, ZNS, INT`
- Last updated: `2026-03-06`

# Scope

- Appointment scheduling
- Calendar drag/drop reschedule flow
- Overbooking and operational override rules
- Appointment auditability
- Appointment observer side-effects to care / visit / integrations
- Mobile appointment listing

# Context

- Module nay nam tren boundary da duoc lam sach cua `GOV` va `PAT`, nhung van la diem nong nhat ve race-condition va scheduling cho CRM phong nha.
- Evidence duoc tong hop tu `Appointment`, `AppointmentObserver`, `AppointmentPolicy`, Filament resource/page/schema/table, migrations lien quan va test scheduling / overbooking / audit / Google Calendar.
- Thong tin con thieu lam giam do chinh xac review: chua co SOP nghiep vu chinh thuc cho state flow `scheduled -> completed`, chua co quy tac van hanh ro rang ve viec ai duoc phep overbook khi branch policy `require_override_reason = false`.

# Re-audit Summary

- `AppointmentSchedulingService` da tro thanh boundary scheduling chung cho create, edit, calendar reschedule va status transition quan trong.
- Overbooking da di qua `Action:AppointmentOverride` dung diem conflict thay vi phu thuoc dirty-check.
- Reschedule tu calendar va form deu bat buoc ly do, ghi audit trail co cau truc, va dong bo `status = rescheduled`.
- Search lead/customer trong APPT da chuyen sang hash-aware lookup tuong thich PAT PII hardening.
- `AppointmentObserver` da duoc lam mong, chi con dispatch mot orchestration job after-commit thay vi fan-out truc tiep.
- Hot-path query cua `AppointmentResource` da eager load ro rang, UI status khong con sua thang ma dung guided actions.
- Re-audit code + regression suite + full suite deu xanh.

# Executive Summary

- Muc do an toan hien tai: `Tot`
- Muc do rui ro nghiep vu: `Trung binh thap`
- Muc do san sang production: `Tot`, da dat clean baseline cho scheduling, auditability va hot-path APPT.
- Cac canh bao nghiem trong:
  - Khong con open blocker baseline cho module APPT.
  - Residual risk van hanh can theo doi: queue worker health cho orchestration side-effects sau commit.

# Architecture Findings

## Security

- Danh gia: `Trung binh`
- Evidence:
  - `app/Models/Appointment.php`
  - `app/Policies/AppointmentPolicy.php`
  - `app/Filament/Resources/Appointments/AppointmentResource.php`
  - `tests/Feature/P1OpsPlatformAndRbacTest.php`
- Findings:
  - `AppointmentPolicy` va `AppointmentResource::getEloquentQuery()` da branch-aware, day la baseline tot.
  - `applyOperationalOverride()` co gate `ActionPermission::APPOINTMENT_OVERRIDE`, nhung `assertOperationalOverridePermission()` khong cover `is_overbooked`, `overbooking_override_by`, `overbooking_override_at`.
  - `assertOperationalOverridePermission()` chay truoc `assertOverbookingPolicy()`. Neu branch policy bat overbooking va `require_override_reason = false`, user co quyen tao/sua lich hen co the chen lich ma khong di qua sensitive action gate.
- Suggested direction:
  - Chuyen overbooking thanh explicit scheduling override path.
  - Gate phai duoc kiem tra sau khi xac dinh projected overlap, hoac bo sung gate truc tiep trong `assertOverbookingPolicy()`.

## Data Integrity & Database

- Danh gia: `Trung binh`
- Evidence:
  - `database/migrations/2025_10_21_171640_create_appointments_table.php`
  - `database/migrations/2025_11_02_102247_enhance_appointments_add_type_and_duration.php`
  - `database/migrations/2026_02_27_111238_add_ops_hotpath_indexes_to_notes_and_appointments_tables.php`
  - `app/Observers/AppointmentObserver.php`
- Findings:
  - FK co day du cho `customer_id`, `patient_id`, `doctor_id`, `assigned_to`, `branch_id`.
  - Co index `appointments_branch_doctor_status_date_index`, phu hop cho conflict query va lich van hanh.
  - Khong co structured audit event rieng cho thay doi `date`/reschedule neu user drag-drop tren calendar ma khong doi `status`.
  - `AppointmentObserver` dang la noi phan tan write side-effects sang care ticket, visit episode, Google Calendar, ZNS va patient conversion.
- Suggested direction:
  - Dua scheduling mutation vao service boundary tap trung.
  - Ghi audit event rieng cho `reschedule`/`slot change`, co `from_at`, `to_at`, `reason`, `actor_id`, `branch_id`.

## Concurrency / Race-condition

- Danh gia: `Kha yeu`
- Evidence:
  - `app/Models/Appointment.php`
  - `app/Filament/Resources/Appointments/Pages/CalendarAppointments.php`
  - `tests/Feature/AppointmentOverbookingPolicyTest.php`
  - `tests/Feature/ConcurrencyHardeningStressTest.php`
  - `tests/Feature/Pm52ToPm60ModulesTest.php`
- Findings:
  - `Appointment::save()` boc transaction va `assertOverbookingPolicy()` co `lockForUpdate()`, day la no luc tot.
  - Tuy nhien create flow, edit flow va calendar reschedule van tan man o page/model, chua co `AppointmentSchedulingService` lam transaction boundary duy nhat.
  - `CalendarAppointments::rescheduleAppointmentFromCalendar()` pre-check conflict ngoai transaction, roi moi save. Save co re-check, nhung logic hien tai van de phan tan va kho audit/cover concurrency thuc su.
  - Test stress hien tai chi lap lai sequential validation guard; chua co test concurrency dong thoi cho `create`/`reschedule` de chung minh conflict guard khong bi race.
- Suggested direction:
  - Tao `AppointmentSchedulingService` gom create/update/reschedule/override.
  - Gom conflict detection + gate + audit vao mot transaction boundary.
  - Them test concurrency cho 2 request cung tao/doi cung slot bac si.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php`
  - `app/Filament/Resources/Appointments/Pages/CalendarAppointments.php`
  - `app/Http/Controllers/Api/V1/MobileAppointmentController.php`
- Findings:
  - Calendar page co eager load `patient`, `doctor`, `branch`, day la tot.
  - Mobile API co pagination va branch scope, day la tot.
  - `AppointmentsTable` dung nhieu closure truy cap `customer`, `patient`, `doctor`, `branch`, `overbookingOverrideBy`, `operationOverrideBy` nhung resource query khong eager load ro rang; nguy co N+1 o danh sach lon.
  - Search lead/customer trong form/table van query `customer.phone/email` bang `LIKE`, khong con phu hop sau PAT PII hardening.
- Suggested direction:
  - Them eager load explicit cho resource query.
  - Chuyen customer/patient search sang hash-aware lookup + name search.

## Maintainability

- Danh gia: `Trung binh`
- Evidence:
  - `app/Observers/AppointmentObserver.php`
  - `app/Services/GoogleCalendarSyncEventPublisher.php`
  - `app/Services/VisitEpisodeService.php`
  - `app/Services/CareTicketService.php`
- Findings:
  - Module da co state machine, overbooking policy, visit episode service, outbox integration idempotent. Nen tang khong te.
  - Observer appointment dang lam qua nhieu viec dong bo trong model event. Moi thay doi appointment co the cham toi care, visit, Google Calendar, ZNS, patient conversion.
  - Logic scheduling/overbooking/reschedule bi chia giua model, page, table action, observer, lam tang regression risk.
- Suggested direction:
  - Dung service orchestration + domain event after-commit.
  - Observer chi nen ghi nhan event / enqueue side-effects, khong dong bo fan-out qua nhieu xu ly.

## Better Architecture Proposal

- Tao `AppointmentSchedulingService` lam entrypoint duy nhat cho:
  - create appointment
  - reschedule from form/calendar
  - force overbook
  - mark no-show / cancel / complete co reason
- Tao `AppointmentRescheduled` / `AppointmentStatusChanged` domain events va dispatch after commit.
- Chuan hoa audit trail cho moi slot mutation va override.
- Tach `lead search` thanh service/hash-aware query de khong phu thuoc vao PII plaintext.

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Trung binh`
- Module da ho tro flow lead -> appointment -> confirm/in progress/completed/no-show/rescheduled, kem visit episode va care reminder.
- Tuy nhien luong drag/drop calendar va luong sua form chua dung chung boundary scheduling, nen cung mot nghiep vu nhung co 2-3 duong mutate khac nhau.

## State transitions

- Danh gia: `Trung binh`
- `Appointment` co state machine ro rang va test cho invalid transition.
- Nhung transition hien tai van cho phep mot so buoc rat rong, vi du `scheduled -> completed`, `confirmed -> cancelled`, `cancelled -> scheduled`, ma UI chua buoc nguoi dung giai trinh hoac xac nhan day du theo luong van hanh.

## Missing business rules

- Chua co rule ro rang cho overbooking nhu mot sensitive action duy nhat bat ke branch policy co bat buoc ly do hay khong.
- Chua co rule bat buoc audit reason khi doi gio hen tu calendar.
- Chua co rule hash-aware cho tra lead theo phone/email sau khi PAT ma hoa PII.
- Chua co rule proofed-by-test cho concurrent create/reschedule cung slot.

## Invalid states / forbidden transitions

- User khong co `Action:AppointmentOverride` van co the tao lich overbook neu policy chi nhanh khong bat buoc ly do.
- Calendar drag/drop co the doi gio hen thanh cong nhung khong luu `reschedule_reason` hay audit reschedule co cau truc.
- UI van cho phep sua `status` truc tiep thay vi dung guided actions cho cancel/no_show/reschedule/completed.

## Service / action / state machine / transaction boundary de xuat

- `AppointmentSchedulingService`
  - `create()`
  - `updateDetails()`
  - `reschedule()`
  - `forceOverbook()`
  - `transitionStatus()`
- `AppointmentAuditRecorder`
  - ghi `reschedule`, `override`, `status_change` co metadata co cau truc
- `AppointmentSearchService`
  - name search + phone/email hash lookup cho customer/patient

# QA/UX Findings

## User flow

- Danh gia: `Trung binh - kem`
- Flow dat lich co day du truong thong tin, nhung luong receptionist tim lead theo phone/email co nguy co fail ngam sau PAT hardening.
- Calendar drag/drop nhanh va tien, nhung thieu modal reason/audit nen de gay tranh chap van hanh.
- Lead booking duoc ho tro, nhung calendar event title hien tai uu tien `patient` va co the hien `Chua ro benh nhan` du appointment chi gan `customer_id`.

## Filament UX

- Danh gia: `Trung binh`
- Diem tot:
  - co calendar page rieng
  - co operational flags va action nhanh
  - branch/doctor option da co scope nhat dinh
- Diem yeu:
  - `status` la select thang, de nhay state sai quy trinh
  - `overbooking_reason` luon hien, nhung gate lai nam trong model theo cach kho doan cho user
  - relation manager va calendar chua thong nhat cach tao/sua/reschedule

## Edge cases quan trong

- Hai le tan dong thoi dat cung bac si, cung khung gio.
- Drag-drop doi gio trong luc nguoi khac vua confirm/doi bac si.
- Branch policy cho overbooking nhung user khong co sensitive action permission.
- Lead booking sau PAT PII hardening, receptionist tim theo phone ma khong ra ket qua.
- Doi gio hen tren calendar nhung khong ghi ly do, khach khi khieu nai khong truy vet duoc.
- Lich hen lead chua convert patient, calendar lai hien ten mo ho hoac khong ro.
- Side-effect integration (care/ZNS/google) fail hoac cham khi appointment save dang giao dich.

## Diem de thao tac sai

- Nhap `status` truc tiep thay vi guided action.
- Calendar force move co semantics nghiep vu lon nhung UI qua nhe.
- Form create/edit co the tao cam giac overbooking la field thong thuong, khong phai override nhay cam.
- Search lead/customer theo phone/email co the tra ve rong du lieu ma khong co thong bao vi sao.

## De xuat cai thien UX

- Doi `status` quan trong thanh action co confirm modal + ly do bat buoc.
- Calendar drag/drop neu doi slot thi mo modal xac nhan + `reschedule_reason`.
- Hien badge ro rang khi ca hen dang `Lead booking` thay vi de title mo ho.
- Search customer trong form/table can ho tro:
  - name partial search
  - phone/email hash-aware exact/normalized search
- Overbooking chi hien surface override cho role co quyen va khi conflict thuc su xay ra.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| APPT-001 | Critical | Security | Overbooking co the bypass Action:AppointmentOverride | Resolved | Gate override da duoc chot tai scheduling boundary va co regression test |
| APPT-002 | High | Concurrency | Scheduling chua co service boundary transaction tap trung | Resolved | Create/edit/calendar da di qua service chung co transaction boundary |
| APPT-003 | High | Data Integrity | Calendar reschedule thieu reason va audit trail co cau truc | Resolved | Calendar va form deu bat buoc reason, ghi audit co cau truc, dong bo status |
| APPT-004 | High | UX | Search customer trong appointment bi regression sau PAT PII hardening | Resolved | APPT da search hash-aware theo phone/email va name partial |
| APPT-005 | High | Maintainability | Observer appointment fan-out qua nhieu side-effect dong bo | Resolved | Observer dispatch mot orchestration job after-commit, side-effects giu nguyen semantics |
| APPT-006 | Medium | Performance | Resource/table chua eager load ro rang cho closure-based relation access | Resolved | Resource query da eager load hot relations va co test guard |
| APPT-007 | Medium | Domain Logic | State transition va UI status select qua rong | Resolved | Status form bi khoa o edit, table/page dung guided actions chuyen trang thai |
| APPT-008 | Medium | Maintainability | Test coverage con thieu cho auth bypass, search regression va concurrent reschedule | Resolved | Regression suite APPT da cover scheduling/auth/search/audit/query/observer |

# Dependencies

- GOV
- PAT
- CLIN
- TRT
- CARE
- ZNS
- INT

# Open Questions

- Co chap nhan nghiep vu `scheduled -> completed` khong qua `confirmed`/`in_progress` hay khong?
- Overbooking co phai luc nao cung la sensitive action, hay co role van hanh nao duoc auto-overbook theo policy branch?
- Calendar drag/drop co can bat buoc ly do doi gio moi lan hay chi khi doi khac ngay/khac branch?

# Recommended Next Steps

- Khong con fix blocker trong APPT baseline.
- Tiep tuc sang `CLIN` de review/fix consent + clinical record boundary tren nen scheduling da on dinh.
- Theo doi queue worker health va do tre orchestration side-effect trong production rollout dau tien.

# Current Status

- In Fix
