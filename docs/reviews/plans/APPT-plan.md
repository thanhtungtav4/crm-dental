# Metadata

- Module code: `APPT`
- Module name: `Appointments / Calendar`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-APPT-`
- Source review: `docs/reviews/modules/APPT-appointments-calendar.md`
- Source issues: `docs/reviews/issues/APPT-issues.md`
- Dependencies: `GOV, PAT, CLIN, TRT, CARE, ZNS, INT`
- Last updated: `2026-03-06`

# Objective

- Lam sach boundary scheduling / calendar cho CRM phong nha.
- Dong sensitive action bypass cua overbooking.
- Chuan hoa scheduling mutation, audit trail, search lead va regression protection truoc khi deep-fix `CLIN` va `TRT`.
- Trang thai hien tai: tat ca task APPT baseline da duoc implement, test, va re-audit.

# Foundation fixes

## [TASK-APPT-001] [Tao AppointmentSchedulingService cho create update reschedule]
- Based on issue(s): APPT-002
- Priority: Foundation
- Objective:
  - Dua scheduling mutation ve mot service boundary transaction duy nhat.
- Scope:
  - create appointment
  - update slot / doctor / branch / duration
  - reschedule from calendar
  - conflict evaluation / overbooking decision hook
- Why now:
  - Khong co boundary nay thi moi fix sau se tiep tuc bi tan man giua model/page.
- Suggested implementation:
  - Tao `AppointmentSchedulingService`
  - Page create/edit/calendar goi service thay vi mutate truc tiep
  - Service gom lock, conflict check, overbooking evaluation, audit hook callback
- Affected files or layers:
  - `app/Services`
  - `app/Filament/Resources/Appointments/Pages`
  - `app/Models/Appointment.php`
- Tests required:
  - create/edit/calendar dung chung service
  - concurrent create/reschedule regression
- Estimated effort: L
- Dependencies:
  - GOV
- Exit criteria:
  - Khong con page nao mutate scheduling critical fields truc tiep ma khong qua service

# Critical fixes

## [TASK-APPT-002] [Dong bypass Action:AppointmentOverride cho overbooking]
- Based on issue(s): APPT-001
- Priority: Critical
- Objective:
  - Bao dam moi truong hop chen lich deu di qua sensitive action gate.
- Scope:
  - model guard
  - service guard
  - form/calendar overbooking path
- Why now:
  - Day la security hole co the bi khai thac ngay trong van hanh.
- Suggested implementation:
  - Gate tai thoi diem xac dinh overlap vuot policy
  - Loai bo phu thuoc vao `overbooking_reason` dirty-check
  - Neu can, tach `force overbook` thanh action rieng
- Affected files or layers:
  - `app/Models/Appointment.php`
  - `app/Services/AppointmentSchedulingService.php`
  - appointment pages/actions
- Tests required:
  - unauthorized user khong the overbook khi `require_override_reason = false`
  - authorized role van overbook duoc
- Estimated effort: M
- Dependencies:
  - TASK-APPT-001
- Exit criteria:
  - khong con duong nao tao overbook ma bo qua `Action:AppointmentOverride`

## [TASK-APPT-003] [Chuan hoa reschedule reason va audit trail cho calendar/form]
- Based on issue(s): APPT-003
- Priority: Critical
- Objective:
  - Moi thay doi slot appointment phai co traceability co cau truc.
- Scope:
  - calendar drag/drop
  - edit form doi gio
  - audit metadata `from_at`, `to_at`, `reason`, `force`, `actor_id`
- Why now:
  - Scheduling la du lieu van hanh nhay cam, khong the de thay doi im lang.
- Suggested implementation:
  - Service `reschedule()` nhan reason bat buoc
  - Them audit event rieng cho slot change
  - Calendar UI mo modal nhap ly do truoc khi commit
- Affected files or layers:
  - calendar page
  - appointment edit flow
  - audit logging
- Tests required:
  - reschedule success co audit log dung
  - reschedule without reason bi chan
- Estimated effort: M
- Dependencies:
  - TASK-APPT-001
- Exit criteria:
  - drag/drop va form reschedule deu de lai audit trail co cau truc

# High priority fixes

## [TASK-APPT-004] [Sua search lead customer theo PII da ma hoa]
- Based on issue(s): APPT-004
- Priority: High
- Objective:
  - Khoi phuc luong receptionist tim lead/customer theo ten, phone, email sau PAT hardening.
- Scope:
  - Appointment form customer selector
  - Appointment table search
- Why now:
  - Day la regression van hanh ro rang tren hot-path dat lich.
- Suggested implementation:
  - Search theo `full_name` partial
  - phone/email dung hash-aware normalized lookup
  - thong nhat helper query/service voi module PAT
- Affected files or layers:
  - `AppointmentForm`
  - `AppointmentsTable`
  - co the can helper service chung
- Tests required:
  - feature test search bang phone/email sau ma hoa
- Estimated effort: M
- Dependencies:
  - PAT
- Exit criteria:
  - receptionist tim duoc lead/customer bang phone/email ma khong can plaintext query

## [TASK-APPT-005] [Giam fan-out dong bo trong AppointmentObserver]
- Based on issue(s): APPT-005
- Priority: High
- Objective:
  - Giam coupling va transaction time cua save appointment.
- Scope:
  - care ticket sync
  - visit episode sync
  - Google Calendar outbox
  - ZNS automation
  - patient conversion orchestration
- Why now:
  - Observer dang la diem regression lon nhat cua module.
- Suggested implementation:
  - Dispatch domain event / after-commit job
  - Observer chi kich hoat orchestration mong
- Affected files or layers:
  - `AppointmentObserver`
  - services lien quan
  - event/listener/job neu can
- Tests required:
  - side-effects van dung sau commit
  - integration outbox regression
- Estimated effort: L
- Dependencies:
  - CARE, CLIN, ZNS, INT
- Exit criteria:
  - appointment save khong con fan-out nhieu write side-effects dong bo trong observer

# Medium priority fixes

## [TASK-APPT-006] [Toi uu query va eager loading cho APPT hot-path]
- Based on issue(s): APPT-006
- Priority: Medium
- Objective:
  - Giam nguy co N+1 va toi uu list/calendar/mobile query shape.
- Scope:
  - AppointmentResource query
  - table/list relation access
  - calendar/mobile query review
- Why now:
  - Sau khi boundary scheduling on dinh, can khoa performance hot-path.
- Suggested implementation:
  - Them eager load explicit
  - Review lai filter/search columns va index usage
- Affected files or layers:
  - resource/table/controller
- Tests required:
  - feature tests cho query shape / result shape
- Estimated effort: S
- Dependencies:
  - TASK-APPT-001
- Exit criteria:
  - hot-path query khong con lazy relation access ro rang

## [TASK-APPT-007] [Tighten state transitions va guided UX cho status change]
- Based on issue(s): APPT-007
- Priority: Medium
- Objective:
  - Giam invalid states va thao tac sai cua user van hanh.
- Scope:
  - status field/form
  - dedicated actions cho cancel/reschedule/no_show/complete
  - confirm modal + reason khi can
- Why now:
  - Sau khi service scheduling va audit trail da on dinh, day la buoc UX/domain lam sach.
- Suggested implementation:
  - Chuyen mot so transition sang action rieng
  - Xem lai matrix transition theo SOP duoc chot
- Affected files or layers:
  - form, table actions, calendar, model state machine
- Tests required:
  - feature/browser tests cho guided transitions
- Estimated effort: M
- Dependencies:
  - TASK-APPT-003
- Exit criteria:
  - status khong con bi doi tuy tien theo kieu de thao tac sai

# Low priority fixes

- Chua de xuat task Low cho den khi hoan tat re-audit cac muc tren.

# Testing & regression protection

## [TASK-APPT-008] [Bo sung APPT regression suite]
- Based on issue(s): APPT-008
- Priority: High
- Objective:
  - Dung regression net cho scheduling, auth bypass, search regression va audit trail.
- Scope:
  - appointment override auth
  - encrypted lead search
  - concurrent create/reschedule
  - reschedule audit metadata
  - eager loading hot-path checks
- Why now:
  - Moi thay doi scheduling rat de hoi quy neu khong co test dac tri.
- Suggested implementation:
  - Them feature tests, co the bo sung browser test cho calendar modal neu can
- Affected files or layers:
  - `tests/Feature`
  - co the `tests/Browser`
- Tests required:
  - day chinh la backlog test
- Estimated effort: M
- Dependencies:
  - TASK-APPT-002, TASK-APPT-003, TASK-APPT-004
- Exit criteria:
  - issue critical/high cua APPT deu co regression test ro rang

# Re-audit checklist

- Khong con bypass `Action:AppointmentOverride` trong moi duong overbooking.
- Create/edit/calendar deu di qua service boundary scheduling chung.
- Moi reschedule deu co reason va audit log co cau truc.
- Search lead/customer bang phone/email van hoat dong sau PII encryption.
- Side-effects appointment da duoc giam coupling hoac chot ly do giu dong bo.
- GOV + PAT + APPT co branch / identity / scheduling boundary nhat quan.

# Execution order

- TASK-APPT-001
- TASK-APPT-002
- TASK-APPT-003
- TASK-APPT-004
- TASK-APPT-005
- TASK-APPT-006
- TASK-APPT-007
- TASK-APPT-008

# What can be done in parallel

- Sau khi `TASK-APPT-001` on dinh, `TASK-APPT-004` va mot phan `TASK-APPT-006` co the lam song song.
- `TASK-APPT-008` co the duoc viet dan song song voi tung task khi implementation ro rang.

# What must be done first

- `TASK-APPT-001` phai lam truoc vi no dat boundary chung cho scheduling.
- `TASK-APPT-002` phai rat som vi day la security hole dang mo.

# Suggested milestone breakdown

- Milestone 1: scheduling boundary + overbooking auth (`TASK-APPT-001`, `TASK-APPT-002`)
- Milestone 2: reschedule audit + encrypted search fix (`TASK-APPT-003`, `TASK-APPT-004`)
- Milestone 3: observer decoupling + performance cleanup (`TASK-APPT-005`, `TASK-APPT-006`)
- Milestone 4: UX state tightening + regression suite + re-audit (`TASK-APPT-007`, `TASK-APPT-008`)
