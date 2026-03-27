# Metadata

- Module code: `CARE`
- Module name: `Customer Care / Automation`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Review file: `docs/reviews/modules/CARE-customer-care-automation.md`
- Issue file: `docs/reviews/issues/CARE-issues.md`
- Plan file: `docs/reviews/plans/CARE-plan.md`
- Issue ID prefix: `CARE-`
- Task ID prefix: `TASK-CARE-`
- Dependencies: `PAT, APPT, FIN, ZNS, KPI`
- Last updated: `2026-03-06`

# Scope

- Module `CARE` xoay quanh care tickets tren `notes`, page `CustomerCare`, quan he `PatientNotesRelationManager`, cac command scheduler/automation, va report `CustomsCareStatistical`.
- Review nay khong di sau vao lifecycle ZNS delivery provider; phan do thuoc module `ZNS`.

# Context

- `CARE` la lop dieu phoi ticket CSKH sau appointment, treatment, prescription, invoice, recall, risk scoring va birthday flow.
- Module dung chung data nhay cam patient/customer, van hanh da chi nhanh va actor role `Manager`, `CSKH`, `Doctor`, `Admin`, `AutomationService`.
- Evidence chinh den tu:
  - `app/Models/Note.php`
  - `app/Services/CareTicketService.php`
  - `app/Filament/Pages/CustomerCare.php`
  - `app/Filament/Resources/Patients/Relations/PatientNotesRelationManager.php`
  - `app/Filament/Resources/Notes/*`
  - `app/Console/Commands/GenerateBirthdayCareTickets.php`
  - `app/Console/Commands/GenerateRecallTickets.php`
  - `app/Console/Commands/RunPlanFollowUpPipeline.php`
  - `app/Console/Commands/RunInvoiceAgingReminders.php`
  - `app/Filament/Pages/Reports/CustomsCareStatistical.php`
  - `app/Services/HotReportAggregateService.php`
- Thong tin con thieu lam giam do chinh xac review:
  - Chua co ma tran role/page chinh thuc cho `CustomerCare` va `CustomsCareStatistical`.
  - Chua co SOP SLA nghiep vu de biet queue nao duoc phep edit/delete thu cong sau khi scheduler tao ticket.

# Executive Summary

- Muc do an toan hien tai: `Trung binh`, da dat clean baseline cho module.
- Muc do rui ro nghiep vu: `Trung binh`, cac blocker auth/idempotency/canonical datasource da duoc khoa.
- Muc do san sang production: `Dat clean baseline`, can tiep tuc rollout migration `ticket_key` va review module `ZNS`/`KPI` lien quan.
- Cac canh bao nghiem trong:
  - Khong con open blocker cap `Critical` trong baseline hien tai.
  - Follow-up con lai la rollout du lieu cu cho `notes.ticket_key` va tiep tuc khoa outbound side-effects/provider semantics o module `ZNS`.

# Re-audit Summary

- `CARE-001` va `CARE-002`: da khoa `CustomerCare`/`CustomsCareStatistical` bang page auth rieng, branch scope va hard-deny global snapshot cho non-admin.
- `CARE-003` va `CARE-004`: da dua upsert/cancel ticket vao `CareTicketWorkflowService`, them `notes.ticket_key`, va chi publish birthday greeting khi ticket moi duoc tao.
- `CARE-005`: assignee selector va payload manual care da duoc scope/sanitize theo branch qua `PatientAssignmentAuthorizer`.
- `CARE-006`: cac tab reminder/follow-up/birthday tren `CustomerCare` da doc datasource canonical `Note` thay vi raw source records.
- `CARE-007`: workflow-managed care tickets da bi khoa edit/delete surface o `NoteResource`, `PatientNotesRelationManager` va `NotePolicy`.
- `CARE-008`: phone search hot path da doi sang `Patient::phoneSearchHash()`.
- `CARE-009`: module da co regression suite rieng cho auth, workflow, birthday dedupe, canonical source, assignee scope va phone search.
- Xac nhan kiem thu:
  - targeted CARE/ZNS/installment suite xanh
  - `php artisan test` xanh: `684 passed`, `3656 assertions`

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - `CustomerCare` khong co `HasPageShield`, khong co `canAccess()` va khong co gate page-level tai `app/Filament/Pages/CustomerCare.php:31-90`.
  - `User::canAccessPanel()` mo panel admin cho `Admin`, `Manager`, `Doctor`, `CSKH` tai `app/Models/User.php:84-99`.
  - `CustomsCareStatistical` khong co actor scope; branch filter list tat ca chi nhanh va query aggregate/raw deu dua vao filter thay vi accessible branches tai `app/Filament/Pages/Reports/CustomsCareStatistical.php:34-81` va `:133-166`.
  - `PatientNotesRelationManager::getCareStaffOptions()` load tat ca users, khong scope branch tai `app/Filament/Resources/Patients/Relations/PatientNotesRelationManager.php:394-399`.
- Findings:
  - Custom page `CustomerCare` hien khong co boundary authorization rieng. Trong he thong da chi nhanh, day la surface de doc PII, export CSV va theo doi queue CSKH. Khong nen phu thuoc vao viec user co vao duoc panel hay khong.
  - Bao cao `CustomsCareStatistical` cho phep mo global scope `branch_scope_id = 0` khi khong chon branch. Voi non-admin, day la mo hinh deny-by-default bi thieu.
  - Filter/selector nhan vien va chi nhanh tren CARE page/manager co nguy co lo option ngoai scope nghiep vu.
- Suggested direction:
  - Them guard page-level ro rang cho `CustomerCare` va `CustomsCareStatistical`.
  - Branch-scope tat ca filter option, selector option, query path va export path.
  - Tach permission page/report rieng thay vi dua vao panel access chung.

## Data Integrity & Database

- Danh gia: `Trung binh`
- Evidence:
  - `notes` co FK `patient_id`, `customer_id`, `user_id`, `branch_id`; co index `notes_source_index`, `notes_care_type_status_care_at_index`, `notes_branch_care_status_care_at_index`.
  - `notes` khong co unique/composite invariant cho `source_type + source_id + care_type` trong schema hien tai.
  - `ReportCareQueueDailyAggregate` co unique `snapshot_date + branch_scope_id + care_type + care_status`.
- Findings:
  - Schema `notes` thieu rang buoc canonical cho care ticket de phan biet mot source event chi duoc co toi da mot active ticket tren tung `care_type`.
  - `source_type/source_id` index khong du cho `updateOrCreate()`/`firstOrNew()` tranh duplicate duoi concurrent load.
  - Care report snapshot co unique dung, nhung actor scope doc snapshot chua dung o page layer.
- Suggested direction:
  - Them unique/composite key canonical cho care ticket hoac sequence idempotency boundary tuong ung voi workflow.
  - Neu cho phep history ticket nhieu lan cho cung source, can them `ticket_key`/`generation_key` ro rang thay vi dua vao 3 cot hien tai.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - `CareTicketService::upsertTicket()` dung `Note::firstOrNew()` roi `save()` tai `app/Services/CareTicketService.php:137-156`.
  - `GenerateRecallTickets`, `RunPlanFollowUpPipeline`, `RunInvoiceAgingReminders` deu dung `updateOrCreate()` tren key `{source_type, source_id, care_type}` ma schema khong co unique key phu hop tai cac file command.
  - `GenerateBirthdayCareTickets` check `exists()` roi `create()`, va goi `publishBirthdayGreeting()` truoc khi dedupe ticket tai `app/Console/Commands/GenerateBirthdayCareTickets.php:45-84`.
- Findings:
  - Day la module automation co nhieu retry path, nhung invariant ticket chua duoc khoa bang transaction + unique index + idempotent publisher.
  - Birthday flow dac biet nguy hiem vi side effect ZNS xay ra truoc khi biet ticket da ton tai hay chua.
  - `updateOrCreate()` khong du safe neu DB khong co unique constraint dung query shape.
- Suggested direction:
  - Gom toan bo create/cancel care ticket vao `CareTicketWorkflowService` co transaction retry va unique invariant.
  - Birthday flow phai dedupe ticket/publish event bang mot boundary idempotent duy nhat.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - `CustomerCare` co `with()` cho relation hot-path, nhung van dung `.searchable()` truc tiep tren `patient.phone`/`phone` o nhieu tab tai `app/Filament/Pages/CustomerCare.php:400-452`, `:513-624`, `:671-680`.
  - `PatientsTable` va `CustomersTable` da co custom hash search query cho phone sau PAT hardening tai `app/Filament/Resources/Patients/Tables/PatientsTable.php:40-48` va `app/Filament/Resources/Customers/Tables/CustomersTable.php:40-49`.
  - `CustomerCare::getSlaSummaryProperty()` chay nhieu aggregate query rieng re tren cung `baseCareTicketQuery()` tai `app/Filament/Pages/CustomerCare.php:118-196`.
- Findings:
  - Search phone tren CARE page dang khong theo encrypted/hash strategy moi, nen co nguy co sai ket qua hoac query rat te khi data tang.
  - SLA summary hien dang tao nhieu query clone lien tiep; chua phai blocker, nhung se tang cost tren branch co queue lon.
  - Report aggregate layer da co, nhung page `CustomerCare` chua tan dung pre-aggregate cho summary card.
- Suggested direction:
  - Chuyen search phone tren CARE sang hash-based query giong `PAT`.
  - Xem xet aggregate summary cache cho dashboard CSKH.

## Maintainability

- Danh gia: `Kem`
- Evidence:
  - `CareTicketService` chi bao phu mot so source (`Appointment`, `Prescription`, `TreatmentSession`, `PlanItem`) trong khi nhieu command khac viet thang vao `Note`.
  - `CustomerCare` page query du lieu tu `Note`, `Appointment`, `Prescription`, `TreatmentSession`, `Patient` tuy tab tai `app/Filament/Pages/CustomerCare.php:199-233`.
  - `NoteResource` va relation manager van la surfaces song song de mutate care tickets.
- Findings:
  - CARE hien dang co nhieu write surfaces va nhieu source of truth: `Note`, raw domain records, command-specific `updateOrCreate()`, patient relation manager.
  - Khi thay doi nghiep vu ticket, kha nang drift rat cao vi logic tan man tren nhieu file.
- Suggested direction:
  - Chot `Note`/care ticket thanh canonical workflow boundary.
  - Moi automation / manual action chi di qua mot service chung.

## Better Architecture Proposal

- Tao `CareTicketWorkflowService`:
  - `upsertFromSource()`
  - `scheduleManualTicket()`
  - `complete()` / `followUp()` / `fail()` / `cancelBySource()`
  - transaction + lock + idempotency key
- Tach page permissions:
  - `View:CustomerCarePage`
  - `View:CustomsCareStatistical`
  - chi role dung scope moi truy cap duoc
- Chot datasource canonical:
  - `CustomerCare` page doc tu `notes` cho cac queue/ticket
  - source table chi dung de build ticket, khong dung lam UI state chinh

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Kem`
- Workflow hien tai:
  - automation tao ticket tu appointment, treatment, prescription, invoice, recall, risk, birthday
  - patient profile cho phep tao/sua/xoa lich cham soc thu cong
  - page `CustomerCare` hien thi tong hop queue va mot so tab derived tu raw domain records
- Van de:
  - khong co boundary chung giua ticket tao tu automation va ticket tao thu cong
  - UI page va raw ticket state khong phai luc nao cung la mot

## State transitions

- Danh gia: `Trung binh`
- `Note` da co canonical status map va transition guard tai `app/Models/Note.php:17-88` va `:113-138`.
- Tuy nhien:
  - transition chi khoa `care_status`, khong khoa delete/force delete
  - khong co workflow service de enforce transition qua mot cho duy nhat
  - page `CustomerCare` o nhieu tab khong doc status tu `Note` ma doc status tu source record hoac value hard-coded

## Missing business rules

- Chua co rule page-level ai duoc vao `CustomerCare` va `CustomsCareStatistical`.
- Chua co rule assignee selector theo branch/team cho care ticket thu cong.
- Chua co idempotency rule chinh thuc cho tung care source event.
- Chua co rule phan biet ticket tu automation co duoc xoa/sua thu cong hay khong.
- Chua co rule canonical giua `appointment_reminder`, `medication_reminder`, `post_treatment_follow_up`, `birthday_care` va tab UI tuong ung.

## Invalid states / forbidden transitions

- Co the tao duplicate ticket cho cung `source_type + source_id + care_type` duoi concurrent run.
- Co the gui birthday greeting truoc khi biet ticket nam nay da ton tai.
- Co the gan ticket cho nhan vien ngoai branch tren relation manager.
- Co the xem bao cao care o global scope `branch_scope_id = 0` neu page khong scope actor.
- Co the xoa/sua ticket dang mo bang surface thu cong, cat dut provenance cua automation.

## Service / action / state machine / transaction boundary de xuat

- `CareTicketWorkflowService`
  - transaction retry
  - unique invariant tren ticket key
  - methods ro rang: `upsertForAppointment`, `upsertForInvoice`, `scheduleManual`, `complete`, `fail`, `resolveSource`
- `CareStaffAuthorizer`
  - tra ve assignee options theo branch va role
  - sanitize server-side payload `user_id`
- `CarePageAuthorizer`
  - gate cho `CustomerCare` va `CustomsCareStatistical`

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Diem tot:
  - co page tong hop SLA queue
  - co patient relation manager cho thao tac nhanh tren ho so benh nhan
- Diem yeu:
  - mot so tab trong `CustomerCare` khong phai ticket that ma la du lieu derived tu raw record, nen nguoi dung co the thay "trang thai cham soc" khac voi thuc te ticket.
  - page co export CSV nhung chua co role/page warning ro rang.

## Filament UX

- Danh gia: `Trung binh`
- `PatientNotesRelationManager` co modal flow kha ro, nhung selector nhan vien va destructive actions chua du an toan.
- `CustomerCare` tabs hien thong tin tot, nhung search phone chua theo hash search va status tren nhieu tab la gia lap/hard-coded.
- Report page `CustomsCareStatistical` de branch filter o dang global, khong phu hop multi-branch production.

## Edge cases quan trong

- Scheduler retry hoac chay 2 worker cung luc tao duplicate ticket.
- Birthday command re-run trong cung nam gui trung greeting vi side effect xay ra truoc dedupe.
- CSKH branch A gan ticket cho nhan vien branch B.
- Ticket automation da `done` nhung tab derived tu appointment/prescription van hien nhu con mo.
- Search so dien thoai tren `CustomerCare` khong ra ket qua sau khi PII duoc ma hoa.
- Non-admin vao report care ma xem duoc global aggregate scope.

## Diem de thao tac sai

- Nut export tren `CustomerCare` khong di kem role/page restriction ro rang.
- Quan ly/CSKH de xoa ticket dang mo lam mat provenance thay vi chuyen status.
- Tab reminder/follow-up hien nhan vien/channels mac dinh, khong cho thay ticket thuc su dang assigned cho ai.

## De xuat cai thien UX

- Hien chi tiet ticket canonical tren tat ca tab care thay vi mix raw source records.
- Khi ticket duoc tao tu automation, UI nen cho phep `done/follow_up/failed` thay vi delete.
- Selector nhan vien can chi hien staff cung branch/co role phu hop.
- Search phone tren CARE can dung hash search helper giong `PAT`.
- Report care can tu dong scope branch theo actor va helper text ve `global snapshot` chi danh cho admin.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| CARE-001 | Critical | Security | CustomerCare page chua co auth gate rieng | Resolved | `CustomerCare` va `CustomsCareStatistical` da co auth gate rieng va branch scope baseline |
| CARE-002 | Critical | Security | CustomsCareStatistical co nguy co lo global cross-branch scope | Resolved | Report page da hard-deny global snapshot cho non-admin va scope filter theo actor |
| CARE-003 | Critical | Concurrency | Care ticket invariant chua duoc khoa bang unique/idempotent boundary | Resolved | `CareTicketWorkflowService` + `notes.ticket_key` da chot canonical upsert boundary |
| CARE-004 | High | Concurrency | Birthday automation publish side effect truoc dedupe ticket | Resolved | Birthday flow da upsert ticket truoc va chi publish khi ticket moi duoc tao |
| CARE-005 | High | Security | Manual care assignee selector chua branch-scoped | Resolved | Assignee options va payload da scope/sanitize theo branch qua `PatientAssignmentAuthorizer` |
| CARE-006 | High | Domain Logic | CustomerCare dang mix raw source tables voi note tickets | Resolved | Cac tab reminder/follow-up/birthday da doc datasource canonical `Note` |
| CARE-007 | High | Data Integrity | Care ticket destructive/edit surfaces van mo ngoai workflow canonical | Resolved | Ticket workflow-managed da bi khoa edit/delete surface va hard-deny o policy |
| CARE-008 | Medium | UX | Search phone trong CARE chua theo encrypted/hash strategy | Resolved | Hot path search da doi sang `Patient::phoneSearchHash()` |
| CARE-009 | Medium | Maintainability | Coverage chua khoa auth, duplicate automation va selector scope | Resolved | CARE da co suite rieng cho auth, workflow, birthday, canonical source, assignee scope va phone search |

# Dependencies

- PAT: patient/customer identity, phone hash search, branch ownership
- APPT: no-show/reschedule, appointment reminder source events
- FIN: payment reminder source events
- ZNS: birthday greeting va care automation side effects
- KPI: care queue aggregate/report consumption

# Open Questions

- Role nao duoc phep vao `CustomerCare` va `CustomsCareStatistical` o production baseline?
- Ticket do automation tao co duoc phep edit noi dung/assignee thu cong hay chi duoc doi status?
- Co can giu lich su nhieu ticket cung mot source event theo chu ky khac nhau, hay 1 source event = 1 canonical ticket theo `care_type`?

# Recommended Next Steps

1. Chay `php artisan migrate` tren moi truong that de ap dung migration `notes.ticket_key` va backfill canonical key.
2. Review module `ZNS` tiep theo de khoa provider retry semantics, dead-letter policy va outbound ownership cho birthday/automation side-effects.
3. Neu muon mo delegated access vuot baseline hien tai, formalize ma tran role/page cho `CustomerCare` va `CustomsCareStatistical`.

# Current Status

- Clean Baseline Reached
