# Metadata

- Module code: `CLIN`
- Module name: `Clinical Records / Consent`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Issue ID prefix: `CLIN-`
- Task ID prefix: `TASK-CLIN-`
- Review file: `docs/reviews/modules/CLIN-clinical-records.md`
- Plan file: `docs/planning/CLIN-plan.md`
- Dependencies: `GOV, PAT, APPT, TRT, FIN, INT`
- Last updated: `2026-03-06`

# Issue Backlog

## [CLIN-001] Patient medical record van luu PHI dang plaintext
- Severity: Critical
- Category: Security
- Module: CLIN
- Description:
  - `PatientMedicalRecord` hien chi encrypt `additional_notes`. Cac truong PHI nhay cam nhu `insurance_number`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_email` van luu raw trong DB va duoc render/search truc tiep tren Filament table/form.
- Why it matters:
  - Day la du lieu y te va PII cua nguoi than; plaintext-at-rest la gap baseline nghiem trong cho production dental CRM.
- Evidence:
  - [PatientMedicalRecord.php](/Users/macbook/Herd/crm/app/Models/PatientMedicalRecord.php)
  - [PatientMedicalRecordForm.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/Schemas/PatientMedicalRecordForm.php)
  - [PatientMedicalRecordsTable.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/Tables/PatientMedicalRecordsTable.php)
- Suggested fix:
  - Encrypt cac truong PHI can thiet; them search/hash surrogate neu can lookup; giam display raw PII tren list view.
- Affected areas:
  - Model, migration, Filament form/table, tests EMR privacy
- Tests needed:
  - Feature test xac nhan gia tri luu DB da encrypted va UI van render dung sau cast
  - Regression test cho search/hash neu duoc bo sung
- Dependencies:
  - PAT baseline PHI hardening
- Suggested order: 1
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-001`

## [CLIN-002] Revision trail cua clinical note khong theo doi day du truong lam sang
- Severity: Critical
- Category: Domain Logic
- Module: CLIN
- Current status: Resolved
- Description:
  - `ClinicalNote::trackedRevisionFields()` va `revisionPayload()` bo sot nhieu truong clinical quan trong nhu `examination_note`, `recommendation_notes`, `diagnoses`. Staff co the sua cac noi dung nay ma revision log khong ghi nhan day du.
- Why it matters:
  - Audit trail EMR khong day du la vi pham nghiem trong ve traceability va an toan y khoa.
- Evidence:
  - [ClinicalNote.php](/Users/macbook/Herd/crm/app/Models/ClinicalNote.php)
  - [ClinicalNoteVersionObserver.php](/Users/macbook/Herd/crm/app/Observers/ClinicalNoteVersionObserver.php)
  - [ClinicalNoteVersioningService.php](/Users/macbook/Herd/crm/app/Services/ClinicalNoteVersioningService.php)
- Suggested fix:
  - Mo rong tracked fields/payload; xem xet khoa sua cac field nhat dinh sau khi session locked; bo sung regression tests cho cac truong bo sot.
- Affected areas:
  - Clinical note model/service/observer, revision table semantics, tests
- Tests needed:
  - Feature test xac nhan sua `recommendation_notes`, `diagnoses`, `examination_note` tao revision dung
  - Failure test cho stale version va locked session
- Dependencies:
  - GOV audit baseline
- Suggested order: 2
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-002`

## [CLIN-003] Encounter va exam session provisioning chua idempotent
- Severity: High
- Category: Concurrency
- Module: CLIN
- Description:
  - `EncounterService::resolveForPatientOnDate()` check-then-create ngoai transaction. `ClinicalNote` creating/saved co the auto-provision `ExamSession` va sync snapshot nhieu lan. Cac flow nay de tao duplicate encounter/session khi thao tac dong thoi.
- Why it matters:
  - Duplicate encounter/session lam vo patient timeline, clinical result linkage va treatment progression.
- Evidence:
  - [EncounterService.php](/Users/macbook/Herd/crm/app/Services/EncounterService.php)
  - [ClinicalNote.php](/Users/macbook/Herd/crm/app/Models/ClinicalNote.php)
  - [ExamSessionLifecycleService.php](/Users/macbook/Herd/crm/app/Services/ExamSessionLifecycleService.php)
- Suggested fix:
  - Tao service transactional cho encounter/session provisioning voi lock/idempotency boundary ro rang; giam logic create trong model events.
- Affected areas:
  - Encounter service, clinical note lifecycle, exam session lifecycle, migrations/indexes neu can
- Tests needed:
  - Concurrency test cho duplicate encounter/session creation
  - Regression test cho standalone encounter auto-create va APPT-linked encounter
- Dependencies:
  - APPT, PAT
- Suggested order: 3
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-003`

## [CLIN-004] Consent lifecycle chua co state machine va immutable signed snapshot
- Severity: High
- Category: Domain Logic
- Module: CLIN
- Description:
  - `Consent` hien chua co explicit service/state transition guard. Signed consent chi duoc suy ra tu `status`, `signed_by`, `signed_at`; khong co snapshot hash/context tai thoi diem ky va khong khoa sua noi dung sau khi da signed.
- Why it matters:
  - Consent la artifact phap ly. Neu signed consent con sua duoc hoac khong co trace context thi dispute handling rat yeu.
- Evidence:
  - [Consent.php](/Users/macbook/Herd/crm/app/Models/Consent.php)
  - [ConsentObserver.php](/Users/macbook/Herd/crm/app/Observers/ConsentObserver.php)
  - [ConsentClinicalGateTest.php](/Users/macbook/Herd/crm/tests/Feature/ConsentClinicalGateTest.php)
- Suggested fix:
  - Tao `ConsentLifecycleService`, define transition hop le, signed snapshot immutable, audit them context ky.
- Affected areas:
  - Consent model/service/observer, migrations, treatment gate tests
- Tests needed:
  - Feature test cho `pending -> signed -> revoked/expired`
  - Test chan sua consent sau khi signed neu khong co privileged action
- Dependencies:
  - TRT, GOV
- Suggested order: 4
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-004`

## [CLIN-005] Selector bac si trong clinical note chua branch-scoped
- Severity: High
- Category: Security
- Module: CLIN
- Description:
  - `ClinicalNotesRelationManager` scope branch selector nhung `examining_doctor_id` va `treating_doctor_id` lai dung relationship mac dinh, khong loc theo branch accessible.
- Why it matters:
  - De gan nham bac si khac chi nhanh hoac expose danh sach user ngoai scope.
- Evidence:
  - [ClinicalNotesRelationManager.php](/Users/macbook/Herd/crm/app/Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php)
- Suggested fix:
  - Scope doctor query theo branch duoc phep va branch dang chon/patient branch; bo sung helper text va server-side sanitize.
- Affected areas:
  - Filament relation manager, authorization, tests
- Tests needed:
  - Feature/Livewire test cho option scoping va reject forged doctor ids ngoai scope
- Dependencies:
  - GOV
- Suggested order: 5
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-005`

## [CLIN-006] EMR va clinical note van mo destructive delete surface
- Severity: High
- Category: Data Integrity
- Module: CLIN
- Description:
  - `PatientMedicalRecordsTable` van co `DeleteBulkAction`; `ClinicalNotesRelationManager` van co `DeleteAction` va `DeleteBulkAction`. Day la destructive surface khong phu hop baseline ho so y te.
- Why it matters:
  - Ho so y te va phiếu kham khong nen bi xoa vat ly de tranh mat traceability va clinical evidence.
- Evidence:
  - [PatientMedicalRecordsTable.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/Tables/PatientMedicalRecordsTable.php)
  - [ClinicalNotesRelationManager.php](/Users/macbook/Herd/crm/app/Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php)
  - [PatientMedicalRecordPolicy.php](/Users/macbook/Herd/crm/app/Policies/PatientMedicalRecordPolicy.php)
- Suggested fix:
  - Loai delete/bulk delete khoi UI baseline; neu nghiep vu can thi chuyen sang archive/soft-delete co audit.
- Affected areas:
  - Filament table/actions, policy/model strategy, tests
- Tests needed:
  - Feature test xac nhan resource/relation manager khong con delete surface
- Dependencies:
  - GOV
- Suggested order: 6
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-006`

## [CLIN-007] EMR resource co nguy co N+1 va query lap lai o form context
- Severity: Medium
- Category: Performance
- Module: CLIN
- Description:
  - EMR table dung `patient` va `updatedBy` nhung resource query chua eager load. Form context goi lai query patient de hien summary.
- Why it matters:
  - Khi record EMR tang, patient list se ton query va patient profile se cham hon.
- Evidence:
  - [PatientMedicalRecordResource.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/PatientMedicalRecordResource.php)
  - [PatientMedicalRecordForm.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/Schemas/PatientMedicalRecordForm.php)
- Suggested fix:
  - Eager load `patient`, `updatedBy`; giam query lap lai trong form context; can nhac async search cho patient selector.
- Affected areas:
  - Resource query, form schema, tests/perf smoke
- Tests needed:
  - Feature test query shape hoac regression test table render khong loi quan he
- Dependencies:
  - PAT
- Suggested order: 7
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-007`

## [CLIN-008] Audit trail CLIN bi phan manh giua AuditLog va EmrAuditLog
- Severity: Medium
- Category: Maintainability
- Module: CLIN
- Description:
  - Consent/treatment side dung `AuditLog`, trong khi clinical order/result dung `EmrAuditLog`. Hai he log khac nhau lam tang do phuc tap forensic va report.
- Why it matters:
  - Khi dieu tra su co clinical workflow, can doc 2 nguon audit khac nhau va kho chot source of truth.
- Evidence:
  - [ConsentObserver.php](/Users/macbook/Herd/crm/app/Observers/ConsentObserver.php)
  - [ClinicalOrderObserver.php](/Users/macbook/Herd/crm/app/Observers/ClinicalOrderObserver.php)
  - [ClinicalResultObserver.php](/Users/macbook/Herd/crm/app/Observers/ClinicalResultObserver.php)
- Suggested fix:
  - Chuan hoa schema/context hoac tao adapter/query facade thong nhat cho CLIN audit reporting.
- Affected areas:
  - Audit observers/services, reporting, docs
- Tests needed:
  - Regression test cho clinical audit reporting/query facade neu duoc them
- Dependencies:
  - GOV, OPS
- Suggested order: 8
- Current status: Resolved
- Linked task IDs: `TASK-CLIN-008`

# Summary

- Open critical count: `0`
- Open high count: `0`
- Open medium count: `0`
- Open low count: `0`
- Next recommended action: `Chot CLIN baseline va chuyen sang review TRT.`
