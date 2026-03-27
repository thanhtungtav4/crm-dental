# Metadata

- Module code: `CLIN`
- Module name: `Clinical Records / Consent`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Review file: `docs/reviews/modules/CLIN-clinical-records.md`
- Issue file: `docs/reviews/issues/CLIN-issues.md`
- Plan file: `docs/reviews/plans/CLIN-plan.md`
- Issue ID prefix: `CLIN-`
- Task ID prefix: `TASK-CLIN-`
- Dependencies: `GOV, PAT, APPT, TRT, FIN, INT`
- Last updated: `2026-03-06`

# Scope

- Patient medical records (EMR baseline)
- Clinical notes and revisioning
- Consent lifecycle for treatment gating
- Clinical orders and clinical results lifecycle
- Encounter and exam session linkage
- Filament surfaces for EMR and clinical note entry

# Context

- Module nay quan ly du lieu nhay cam bac cao: EMR, consent, chi dinh, ket qua can lam sang.
- Module phu thuoc truc tiep vao patient ownership (`PAT`), branch access (`GOV`), appointment/visit boundary (`APPT`) va treatment progression (`TRT`).
- Clinical records can dat baseline cao hon module CRUD thong thuong vi lien quan traceability, legal consent va patient safety.
- Thong tin con thieu lam giam do chinh xac review:
  - Chua thay Filament resource/page rieng cho `Consent`, `ClinicalOrder`, `ClinicalResult`, `ClinicalMediaAsset` de danh gia het UI flow.
  - Chua co SOP chinh thuc cho retention/legal hold cua consent tai lieu va EMR attachments.
  - Chua co luong ky consent dien tu da xac minh actor/device/IP o muc nghiep vu.

# Executive Summary

- Muc do an toan hien tai: `Kem`
- Muc do rui ro nghiep vu: `Cao`
- Muc do san sang production: `Kem`, chua dat baseline cho module clinical records.
- Cac canh bao nghiem trong:
  - `PatientMedicalRecord` van luu PII/next-of-kin/insurance nhay cam dang plaintext trong DB.
  - Revision trail cua `ClinicalNote` khong theo doi day du nhung truong lam sang quan trong, de thieu audit trail y khoa.
  - `EncounterService` co the auto-tao `VisitEpisode` trung lap khong co transaction/idempotency guard.
  - `PatientMedicalRecordResource` va `ClinicalNotesRelationManager` van mo destructive actions cho EMR/clinical note.

# Architecture Findings

## Security

- Danh gia: `Kem`
- Evidence:
  - [PatientMedicalRecord.php](/Users/macbook/Herd/crm/app/Models/PatientMedicalRecord.php) chi encrypt `additional_notes`; `insurance_number`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_email` van plaintext.
  - [PatientMedicalRecordForm.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/Schemas/PatientMedicalRecordForm.php) render truc tiep toan bo thong tin lien he khan cap va bao hiem.
  - [PatientMedicalRecordsTable.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/Tables/PatientMedicalRecordsTable.php) cho search/display raw `emergency_contact_name` va `insurance_provider`.
  - [Consent.php](/Users/macbook/Herd/crm/app/Models/Consent.php) chi encrypt `note`; khong co signature snapshot, IP, device, hash tai lieu hay immutable guard.
- Findings:
  - EMR baseline hien tai khong dong deu voi PAT hardening da lam truoc do.
  - Consent duoc coi la du lieu phap ly nhung model chua enforce immutability sau khi da signed/revoked.
  - Clinical media co policy branch-aware nhung chua thay enforcement chain day du cho consent-linked media access trong UX.
- Suggested direction:
  - Encrypt toan bo PII/PHI field nhay cam trong `PatientMedicalRecord` va bo sung hash/search surrogate neu can tim kiem.
  - Tach consent state machine + signed snapshot (`signed_by`, `signed_at`, `document_checksum`, `signature_context`).
  - Giam surface search/display cua thong tin lien he khan cap tren table/list view.

## Data Integrity & Database

- Danh gia: `Trung binh`
- Evidence:
  - `patient_medical_records.patient_id` unique la diem tot, schema da co FK day du.
  - `clinical_note_revisions` co unique `(clinical_note_id, version)` va index theo patient/visit/operation.
  - `clinical_notes` khong co unique guard cho `patient_id + exam_session_id + date`, de de sinh duplicate phieu kham cung session.
  - `consents` chi co index `(patient_id, service_id, status)`; khong co guard de ngan nhieu signed consent hoat dong cung plan item/version.
- Findings:
  - Revision table duoc thiet ke kha dung, nhung payload/tracked fields hien tai khong day du nen database layer co cau truc tot nhung khong du gia tri forensic.
  - Consent va encounter chua co composite uniqueness phu hop voi nghiep vu thuc te.
  - Patient medical record khong co soft delete/immutable strategy nhung UI van cho delete bulk.
- Suggested direction:
  - Bo sung unique/composite guard cho consent active snapshot theo `patient_id + plan_item_id/service_id + consent_type + consent_version + status` neu nghiep vu cho phep.
  - Can nhac guard cho clinical note theo session/date de tranh duplicate record song song.
  - EMR resource nen read-preserving, khong cho xoa vat ly o baseline.

## Concurrency / Race-condition

- Danh gia: `Kem`
- Evidence:
  - [EncounterService.php](/Users/macbook/Herd/crm/app/Services/EncounterService.php) `resolveForPatientOnDate()` check existing roi `create()` ngoai transaction.
  - [ClinicalNote.php](/Users/macbook/Herd/crm/app/Models/ClinicalNote.php) `creating` auto-provision `ExamSession`, sau do `saved` tiep tuc `syncExamSessionSnapshot()` va co the `saveQuietly()` them mot lan nua.
  - [ClinicalResult.php](/Users/macbook/Herd/crm/app/Models/ClinicalResult.php) `saved` tu dong `markCompleted()` cho order, observer lai refresh exam session, coupling nhieu tang.
  - [ClinicalNoteVersioningService.php](/Users/macbook/Herd/crm/app/Services/ClinicalNoteVersioningService.php) co pessimistic lock tot cho update note, nhung luong create/provision session van chua co transaction boundary thong nhat.
- Findings:
  - Co optimistic locking cho update clinical note, nhung boundary around note/session/encounter chua dong nhat.
  - Auto-create standalone encounter voi default `09:00` / `30 min` de de sinh duplicate hoac mismatch voi appointment thuc.
  - Clinical result lifecycle hien dua vao model event + observer, kho dam bao idempotency khi retry/save nhieu lan.
- Suggested direction:
  - Dua `Encounter` provisioning vao service transactional co `lockForUpdate()` va idempotency key theo `patient + branch + date + source`.
  - Dong goi note/session/result side-effects vao orchestration service after-commit thay vi chain model events phan tan.
  - Them regression test cho duplicate encounter, duplicate note/session provisioning, finalize result retry.

## Performance / Scalability

- Danh gia: `Trung binh`
- Evidence:
  - [PatientMedicalRecordResource.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/PatientMedicalRecordResource.php) khong eager load `patient`, `updatedBy` du table su dung ca hai quan he.
  - [PatientMedicalRecordForm.php](/Users/macbook/Herd/crm/app/Filament/Resources/PatientMedicalRecords/Schemas/PatientMedicalRecordForm.php) `resolvePatient()` query lai patient context trong khi form da co relationship state.
  - [ClinicalEvidenceGateService.php](/Users/macbook/Herd/crm/app/Services/ClinicalEvidenceGateService.php) dem evidence bang count query + flatten payload tren moi finalize.
- Findings:
  - N+1 nguy co ro o EMR list.
  - Form relation manager clinical note co preload nhieu option va multi image uploads, can de y memory / browser performance khi patient co lich su lon.
  - Evidence gate co the chap nhan duoc o baseline, nhung can cache/optimize query shape khi quy mo ket qua/media tang.
- Suggested direction:
  - Eager load mac dinh cho EMR resource table.
  - Tach doctor option query branch-scoped va co search async thay vi preload toan bo.
  - Theo doi query count cho patient detail page co relation manager clinical notes.

## Maintainability

- Danh gia: `Trung binh`
- Evidence:
  - Audit trail dang tach thanh `AuditLog` va `EmrAuditLog` giua consent/treatment vs clinical orders/results.
  - `ClinicalNote` mang qua nhieu trach nhiem: versioning hints, exam session provisioning, snapshot sync, status inference.
  - `ExamSessionLifecycleService` va observer `ClinicalResultObserver` phoi hop chat voi nhau nhung khong co orchestration boundary ro rang.
- Findings:
  - Module co nhieu domain object tot, nhung business boundary dang tan man giua model booted events, observers va services.
  - Chi can refactor nhe o sai vi tri la de regression traceability.
- Suggested direction:
  - Chuan hoa `ClinicalRecordLifecycleService` / `ConsentLifecycleService` de gom state changes va audit.
  - Giam logic trong model event, uu tien application service transactional.
  - Chot mot he audit duy nhat cho CLIN neu khong co ly do manh de tach doi.

## Better Architecture Proposal

- Dua `PatientMedicalRecord` ve mo hinh `encrypted fields + read-preserving updates + no destructive bulk delete`.
- Tao `ConsentLifecycleService` voi transition ro: `pending -> signed -> revoked/expired`; signed consent tro thanh immutable snapshot.
- Tao `EncounterProvisioningService` va `ClinicalRecordLifecycleService` de quan ly note/session/result side-effects trong transaction boundary ro rang.
- Chuan hoa audit trail CLIN theo mot schema co `patient_id`, `visit_episode_id`, `branch_id`, `actor_id`, `occurred_at`, `context`.

# Domain Logic Findings

## Workflow chinh

- Danh gia: `Trung binh`
- Workflow hien tai:
  - Tao/cap nhat EMR cho benh nhan.
  - Bac si ghi `ClinicalNote`, he thong auto-provision `ExamSession` neu thieu.
  - Tao `ClinicalOrder`, nhap `ClinicalResult`, finalize va dong bo status.
  - Consent duoc dung nhu gate cho treatment progression.
- Nhan xet:
  - Huong domain co phan tach order/result/note/consent la dung.
  - Tuy nhien luong create note -> session -> encounter -> result chua co transaction boundary thong nhat.

## State transitions

- Danh gia: `Trung binh`
- Diem tot:
  - `ClinicalResult` co transition table ro rang.
  - `ExamSession` co transition table ro rang.
  - `ClinicalNoteVersioningService` enforce optimistic lock khi update.
- Van de:
  - `Consent` khong co explicit transition guard; set `status` truc tiep van duoc.
  - `ClinicalOrder` / `ClinicalResult` / `ExamSession` lien ket voi nhau qua observer side-effects, khong co state coordinator duy nhat.
  - `ClinicalNote` co revisioning nhung khong ghi nhan day du cac field clinical quan trong.

## Missing business rules

- EMR khong nen bi xoa bulk trong flow van hanh thong thuong.
- Signed consent nen khoa noi dung va signature context sau khi hoan tat.
- Clinical note doctor assignment can branch-scoped theo branch dang thao tac/patient branch.
- Standalone encounter auto-create can co quy tac idempotent theo ngay/branch/source.
- Clinical result finalize/amend nen co rule ro ve so lan amend va ai duoc amend sau khi locked session.

## Invalid states / forbidden transitions

- Consent co the bi cap nhat status qua lai ma khong co service boundary ngan `signed -> pending` hoac sua nguoi ky.
- EMR resource van cho delete bulk du lieu benh an.
- Clinical note co the sua nhieu field quan trong ma revision trail khong ghi nhan day du, tao “audit trail gia”.
- Relation manager clinical notes cho delete truc tiep trong patient profile, de xoa nham du lieu lam sang dang dang thao tac.

## Service / action / state machine / transaction boundary de xuat

- `PatientMedicalRecordPrivacyService`:
  - migrate/encrypt PHI fields
  - hash/search surrogate neu can lookup
- `ConsentLifecycleService`:
  - `createPending`, `sign`, `revoke`, `expire`
  - signed snapshot immutable
  - audit va signature context day du
- `EncounterProvisioningService`:
  - resolve/create encounter idempotent trong transaction
- `ClinicalRecordLifecycleService`:
  - dong goi note/session/result synchronization va audit after-commit

# QA/UX Findings

## User flow

- Danh gia: `Trung binh`
- Flow nhap EMR khong te, co summary/checklist va link tu patient profile.
- Tuy nhien, relation manager `ClinicalNotes` hien qua “power-user”: vua nhap note, vua upload image, vua chon branch/doctor trong mot modal lon, rat de thao tac sai.
- Khi patient co nhieu encounter/note, view relation manager hien tai de roi vao overload thong tin.

## Filament UX

- Danh gia: `Trung binh`
- Diem tot:
  - EMR form co context benh nhan va safety checklist.
  - Patient profile da co CTA mo/tao EMR ro rang.
- Diem yeu:
  - `PatientMedicalRecordsTable` van co `DeleteBulkAction` du day la ho so y te.
  - `ClinicalNotesRelationManager` preload doctor list khong scope, de chon nham bac si/chi nhanh.
  - File upload cho indication images gioi han 10MB/file, nhung chua thay huong dan cho X-ray lon hoac fail-safe khi upload dang do.
  - Chua thay guided actions cho consent signing/revocation.

## Edge cases quan trong

- Hai nguoi dong thoi tao clinical note cho cung patient/session va he thong tao hai exam session khac nhau.
- Bac si finalize ket qua trong khi ky thuat vien van dang upload clinical media, gay override khong can thiet.
- Signed consent het han giua luc plan item chuyen phase.
- Benh nhan chuyen branch sau khi da co EMR va consent, nhung selector/visibility khong cap nhat scope dung.
- Bac si sua `recommendation_notes` hoac `diagnoses` nhung revision log khong ghi nhan.
- Le tan/doctor xoa nham EMR qua bulk action.
- Standalone encounter auto-create cho ngay kham khong co appointment roi sau do APPT sync them visit episode that, gay duplicate.
- Upload X-ray lon tren mobile/tablet bi fail giua chung nhung form khong co guidance retry.

## Diem de thao tac sai

- Modal `ClinicalNotesRelationManager` qua nhieu section, nhieu field dynamic va upload cung luc.
- Delete action dat ngay trong relation manager, khong co warning clinical-grade.
- EMR list show emergency contact o table, de lo thong tin nhay cam khong can thiet cho vai tro chi can nhin tong quan.
- Consent flow khong co surface UI ro rang trong module nay, de gay trang thai “co gate nhung khong thay ai xu ly”.

## De xuat cai thien UX

- Bien EMR thanh read-preserving: khong bulk delete, thay bang archive/disable neu can.
- Tach modal clinical note thanh step logic hon hoac it nhat them helper text branch/doctor scope ro rang.
- Them upload guidance va validation message ro cho image/X-ray lon.
- Neu co consent UI, them action ro: `Ky consent`, `Thu hoi`, `Het han`, kem log actor/time.
- Giam display PII tren list view; chi hien khi mo chi tiet ho so.

# Issue Summary

| Issue ID | Severity | Category | Title | Status | Short note |
| --- | --- | --- | --- | --- | --- |
| CLIN-001 | Critical | Security | Patient medical record van luu PHI dang plaintext | Resolved | PHI EMR da duoc encrypt/backfill va list view khong con hien raw PII |
| CLIN-002 | Critical | Domain Logic | Revision trail cua clinical note khong theo doi day du truong lam sang | Resolved | Revision payload da cover them field clinical va khoa sua khi session locked |
| CLIN-003 | High | Concurrency | Encounter/session provisioning chua idempotent va co race-condition | Resolved | Encounter va exam session provisioning da dua vao service transactional/idempotent |
| CLIN-004 | High | Domain Logic | Consent lifecycle chua co state machine va immutable signed snapshot | Resolved | Consent da co lifecycle service, transition guard va signature context immutable |
| CLIN-005 | High | Security | Selector bac si trong clinical note chua branch-scoped | Resolved | Relation manager va domain layer da scope/validate doctor theo branch |
| CLIN-006 | High | Data Integrity | EMR va clinical note van mo destructive delete surface | Resolved | Delete surface da bi go bo o UI/policy baseline |
| CLIN-007 | Medium | Performance | EMR resource co nguy co N+1 va form context query lap lai | Resolved | Resource query da eager load va form summary tai su dung relation da load |
| CLIN-008 | Medium | Maintainability | Audit trail CLIN bi phan manh giua AuditLog va EmrAuditLog | Resolved | Da co `ClinicalAuditTimelineService` hop nhat doc audit cho CLIN timeline |

# Re-audit Update

- Updated verdict: `B`
- Clean baseline status: `Yes`
- Resolved issues:
  - `CLIN-001` -> `CLIN-008`
- Residual follow-up:
  - UX ky consent production-grade va huong dan upload X-ray lon van nen refine tiep khi review `TRT` va `INT`.
- Evidence:
  - EMR PHI hardening: [PatientMedicalRecord.php](/Users/macbook/Herd/crm/app/Models/PatientMedicalRecord.php), [2026_03_06_153534_harden_patient_medical_record_sensitive_fields.php](/Users/macbook/Herd/crm/database/migrations/2026_03_06_153534_harden_patient_medical_record_sensitive_fields.php)
  - Clinical note integrity + doctor scoping: [ClinicalNote.php](/Users/macbook/Herd/crm/app/Models/ClinicalNote.php), [ClinicalNotesRelationManager.php](/Users/macbook/Herd/crm/app/Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php), [ClinicalNotePolicy.php](/Users/macbook/Herd/crm/app/Policies/ClinicalNotePolicy.php)
  - Consent lifecycle: [Consent.php](/Users/macbook/Herd/crm/app/Models/Consent.php), [ConsentLifecycleService.php](/Users/macbook/Herd/crm/app/Services/ConsentLifecycleService.php)
  - Unified CLIN audit reader: [ClinicalAuditTimelineService.php](/Users/macbook/Herd/crm/app/Services/ClinicalAuditTimelineService.php)

# Dependencies

- GOV: branch scoping va authorization baseline
- PAT: patient identity / PHI boundary
- APPT: visit episode / encounter linkage
- TRT: consent gate va treatment progression
- FIN: lien quan legal evidence, invoice dispute traceability
- INT: media/document signing or external storage integration neu co

# Open Questions

- Consent duoc ky bang cach nao tren production: staff xac nhan ho, ky OTP, hay ky tay scan/upload?
- Co can cho phep xoa mem EMR/clinical note hay bat buoc chi deactive/archive?
- Clinical media co resource/page rieng de review access log, retention va download authorization hay khong?
- Clinical note co can mot note duy nhat moi exam session hay co the nhieu note phu theo cung session?

# Recommended Next Steps

- Chot batch CLIN va commit/push sau re-audit.
- Chuyen sang review `TRT` de tiep tuc tren nen consent/session/material usage da on dinh hon.
- Theo doi sau baseline: UX ky consent production-grade va upload imaging lon.

# Current Status

- Clean Baseline Reached
