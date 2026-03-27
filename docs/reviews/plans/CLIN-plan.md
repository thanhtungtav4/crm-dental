# Metadata

- Module code: `CLIN`
- Module name: `Clinical Records / Consent`
- Current status: `Clean Baseline Reached`
- Current verdict: `B`
- Task ID prefix: `TASK-CLIN-`
- Source review: `docs/reviews/modules/CLIN-clinical-records.md`
- Source issues: `docs/reviews/issues/CLIN-issues.md`
- Dependencies: `GOV, PAT, APPT, TRT, FIN, INT`
- Last updated: `2026-03-06`

# Objective

- Dat clean baseline cho clinical records/consent bang cach khoa PHI at-rest, lam day revision trail, dong goi encounter/session lifecycle va loai destructive UI surface.

# Foundation fixes

## [TASK-CLIN-001] Harden patient medical record PHI encryption
- Based on issue(s): `CLIN-001`
- Priority: Foundation
- Objective:
  - Dua `PatientMedicalRecord` len cung baseline privacy voi `Patient`/`Customer` sau PAT hardening.
- Scope:
  - Encrypt PHI fields, bo sung search/hash surrogate neu thuc su can, giam raw PII tren table.
- Why now:
  - Day la blocker an toan cao nhat cua module.
- Suggested implementation:
  - Them casts encrypted cho insurance/emergency contact fields.
  - Tao migration backfill/hash cho search fields neu can lookup theo phone/email.
  - Dieu chinh Filament table/form de khong expose PII khong can thiet.
- Affected files or layers:
  - `app/Models/PatientMedicalRecord.php`
  - `database/migrations/*patient_medical_records*`
  - `app/Filament/Resources/PatientMedicalRecords/*`
  - tests feature EMR privacy
- Tests required:
  - Encryption-at-rest regression tests
  - UI render/search tests sau cast
- Estimated effort: M
- Dependencies:
  - PAT encryption patterns
- Exit criteria:
  - PHI nhay cam khong con luu plaintext trong DB va EMR UI van hoat dong binh thuong.

# Critical fixes

## [TASK-CLIN-002] Hoan chinh revision integrity cho clinical notes
- Based on issue(s): `CLIN-002`
- Priority: Critical
- Objective:
  - Dam bao moi thay doi clinical quan trong deu co revision/audit dung nghia.
- Scope:
  - Mo rong tracked fields/payload, xem xet khoa sua khi session locked, bo sung tests stale/locked.
- Why now:
  - Day la legal + clinical safety blocker sau privacy.
- Suggested implementation:
  - Cap nhat `ClinicalNote::trackedRevisionFields()` va `revisionPayload()`.
  - Dieu chinh version observer/service neu can.
  - Xem xet rule khong cho sua mot so field sau khi exam session locked.
- Affected files or layers:
  - `app/Models/ClinicalNote.php`
  - `app/Observers/ClinicalNoteVersionObserver.php`
  - `app/Services/ClinicalNoteVersioningService.php`
  - tests `ClinicalNoteVersioningTest`
- Tests required:
  - Revision coverage cho `examination_note`, `recommendation_notes`, `diagnoses`
  - Locked session guard test
- Estimated effort: M
- Dependencies:
  - `TASK-CLIN-001` khong bat buoc, co the song song sau migration planning
- Exit criteria:
  - Cac field clinical quan trong deu tao revision chinh xac khi sua.

## [TASK-CLIN-003] Dong goi encounter va exam session provisioning theo transaction boundary ro rang
- Based on issue(s): `CLIN-003`
- Priority: Critical
- Objective:
  - Loai duplicate encounter/session va chot lifecycle boundary cho note/result.
- Scope:
  - `EncounterService`, `ClinicalNote` provisioning, `ExamSessionLifecycleService`, side-effects tu result/order.
- Why now:
  - Duplicate encounter/session se lam vo toan bo timeline clinical.
- Suggested implementation:
  - Tao service orchestration co transaction + lock/idempotency.
  - Giam auto-create trong model event; chi giu invariant don gian o model.
  - Bo sung index/unique guard neu nghiep vu cho phep.
- Affected files or layers:
  - `app/Services/EncounterService.php`
  - `app/Models/ClinicalNote.php`
  - `app/Services/ExamSessionLifecycleService.php`
  - observer lien quan
  - migrations/indexes neu can
- Tests required:
  - Concurrency regression tests cho duplicate encounter/session
  - Flow test APPT-linked encounter va standalone encounter
- Estimated effort: L
- Dependencies:
  - APPT, PAT
- Exit criteria:
  - Khong con tao duplicate encounter/session trong thao tac dong thoi co the lap lai.

# High priority fixes

## [TASK-CLIN-004] Tao consent lifecycle service va signed snapshot immutable
- Based on issue(s): `CLIN-004`
- Priority: High
- Objective:
  - Bien consent thanh artifact phap ly co state machine ro rang.
- Scope:
  - Transition rules, signed snapshot, audit context, revoke/expire rules.
- Why now:
  - Consent gate lien quan truc tiep treatment progression va dispute handling.
- Suggested implementation:
  - Tao `ConsentLifecycleService`.
  - Khoa sua payload quan trong sau signed.
  - Bo sung metadata ky: actor, time, checksum/context neu co san.
- Affected files or layers:
  - `app/Models/Consent.php`
  - `app/Observers/ConsentObserver.php`
  - service moi + tests consent gate
  - migration neu can them signature context/checksum
- Tests required:
  - Transition tests
  - Immutability tests sau signed
- Estimated effort: L
- Dependencies:
  - TRT, GOV
- Exit criteria:
  - Consent co state machine hop le va signed consent khong bi sua trai phep.

## [TASK-CLIN-005] Khoa doctor selector theo branch scope trong clinical note UX
- Based on issue(s): `CLIN-005`
- Priority: High
- Objective:
  - Chan assign sai doctor/branch ngay tai UI va server-side.
- Scope:
  - Clinical note relation manager form, option query, sanitize server-side neu can.
- Why now:
  - Day la bug branch isolation de lap lai neu khong khoa som.
- Suggested implementation:
  - Scope `examiningDoctor` va `treatingDoctor` options theo branch duoc phep/branch dang chon.
  - Them validation reject ID ngoai scope.
- Affected files or layers:
  - `app/Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php`
  - co the them service/helper branch-aware doctor options
- Tests required:
  - Livewire/feature tests cho option visibility va forged payload
- Estimated effort: M
- Dependencies:
  - GOV
- Exit criteria:
  - Khong the chon hay submit doctor ngoai scope branch hop le.

## [TASK-CLIN-006] Loai destructive delete surface khoi EMR va clinical note baseline
- Based on issue(s): `CLIN-006`
- Priority: High
- Objective:
  - Bao toan traceability cho ho so y te va phiếu kham.
- Scope:
  - Resource/relation manager actions, policy, archive strategy neu can.
- Why now:
  - UI delete dang la risk van hanh ro rang.
- Suggested implementation:
  - Bo delete/bulk delete khoi Filament baseline.
  - Neu can soft-delete thi dua vao flow co audit va role rieng.
- Affected files or layers:
  - `app/Filament/Resources/PatientMedicalRecords/*`
  - `app/Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php`
  - policies/models lien quan
- Tests required:
  - Feature tests xac nhan khong con delete surface
- Estimated effort: M
- Dependencies:
  - GOV
- Exit criteria:
  - EMR/clinical note khong con delete surface trong baseline clinical UI.

# Medium priority fixes

## [TASK-CLIN-007] Toi uu query va form context cho EMR resource
- Based on issue(s): `CLIN-007`
- Priority: Medium
- Objective:
  - Giam query noise/N+1 tren EMR pages.
- Scope:
  - Resource eager loading, patient context resolution, table render.
- Why now:
  - Nen lam sau khi privacy/delete surface da on dinh.
- Suggested implementation:
  - Them eager loading `patient`, `updatedBy`; giam query lap lai trong placeholder context.
- Affected files or layers:
  - `PatientMedicalRecordResource`
  - `PatientMedicalRecordForm`
  - tests/perf smoke
- Tests required:
  - Feature regression test table render va page open thanh cong
- Estimated effort: S
- Dependencies:
  - `TASK-CLIN-001`
- Exit criteria:
  - EMR pages render dung voi query shape toi uu hon.

## [TASK-CLIN-008] Chuan hoa CLIN audit reporting boundary
- Based on issue(s): `CLIN-008`
- Priority: Medium
- Objective:
  - Giam phan manh audit trail trong module CLIN.
- Scope:
  - Adapter/query facade/reporting abstraction cho `AuditLog` va `EmrAuditLog`.
- Why now:
  - Khong chan baseline fix, nhung can de re-audit clean hon.
- Suggested implementation:
  - Xac dinh source of truth cho tung entity hoac tao unified reader cho CLIN audit.
- Affected files or layers:
  - audit services/observers/report widgets/doc
- Tests required:
  - Regression test cho unified audit query neu duoc bo sung
- Estimated effort: M
- Dependencies:
  - GOV, OPS
- Exit criteria:
  - Co mot cach doc audit CLIN nhat quan cho re-audit va reporting.

# Low priority fixes

- Chua mo them low-priority task cho den khi xong re-audit baseline.

# Testing & regression protection

- Them test encryption-at-rest cho EMR fields.
- Them test revision coverage cho toan bo clinical note tracked fields quan trong.
- Them concurrency test encounter/session provisioning.
- Them tests consent transition va signed immutability.
- Them Livewire/feature tests cho doctor branch scope trong relation manager.
- Them tests xac nhan khong con delete surface trong EMR/clinical note UI.

# Re-audit checklist

- PHI EMR da duoc encrypt/backfill va khong vo UI.
- Clinical note revision log ghi nhan day du field clinical.
- Khong the tao duplicate encounter/session trong test dong thoi.
- Consent signed khong sua trai phep, transition hop le.
- Doctor selector chi hien/submit duoc actor trong branch hop le.
- EMR va clinical note khong con delete surface baseline.
- GOV/PAT/APPT regression lien quan CLIN deu xanh.

# Execution order

1. `TASK-CLIN-001`
2. `TASK-CLIN-002`
3. `TASK-CLIN-003`
4. `TASK-CLIN-004`
5. `TASK-CLIN-005`
6. `TASK-CLIN-006`
7. `TASK-CLIN-007`
8. `TASK-CLIN-008`

# What can be done in parallel

- `TASK-CLIN-002` co the chay song song voi phan migration planning cua `TASK-CLIN-001` neu tach branch ro rang.
- `TASK-CLIN-005` va `TASK-CLIN-006` co the thuc hien song song sau khi da chot branch/authorization assumptions.
- `TASK-CLIN-007` co the de sau nhung co the gom vao batch UI/resource cleanup.

# What must be done first

- `TASK-CLIN-001` phai lam truoc de khoa privacy baseline.
- `TASK-CLIN-003` nen hoan tat truoc khi lam sau vao encounter/result UX vi no la lifecycle boundary.

# Suggested milestone breakdown

- Milestone 1: Privacy + revision integrity (`TASK-CLIN-001`, `TASK-CLIN-002`)
- Milestone 2: Encounter/session concurrency + consent lifecycle (`TASK-CLIN-003`, `TASK-CLIN-004`)
- Milestone 3: UI safety + performance + audit normalization (`TASK-CLIN-005` -> `TASK-CLIN-008`)

# Execution Status

- `TASK-CLIN-001` -> Resolved
- `TASK-CLIN-002` -> Resolved
- `TASK-CLIN-003` -> Resolved
- `TASK-CLIN-004` -> Resolved
- `TASK-CLIN-005` -> Resolved
- `TASK-CLIN-006` -> Resolved
- `TASK-CLIN-007` -> Resolved
- `TASK-CLIN-008` -> Resolved
