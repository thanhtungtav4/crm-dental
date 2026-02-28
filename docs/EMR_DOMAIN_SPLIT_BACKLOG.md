# EMR Domain Split Backlog (CRM Monolith)

Updated: 2026-02-28
Owner: Product + Tech Lead
Scope: Same Laravel codebase, split CRM vs EMR domain boundaries with shared Auth/Org/Branch/User/Permission.

## Objectives

- Keep one deployment unit, but separate business domain responsibilities:
  - CRM: Lead, Customer, Booking, CSKH, Sales pipeline.
  - EMR: Patient, Encounter, Clinical note, Orders, Results, Prescription.
- Enforce production gates: branch isolation, EMR-dedicated audit trail, PHI encryption-at-rest, clinical versioning.

## Ticket Board

| Ticket | Priority | Status | Scope | Acceptance (short) | Git Ref |
|---|---|---|---|---|---|
| EMR-00 | P0 | Done | Backlog and execution governance | Có backlog riêng, có status theo ticket, có mapping commit | 2b41a09 |
| EMR-01 | P0 | Done | Branch isolation for PlanItem/TreatmentSession | Policy + resource query chặn cross-branch, test `EmrBranchIsolationPlanItemTreatmentSessionTest` pass | b3fa932 |
| EMR-02 | P0 | Done | Schedule `emr:sync-events` via hardened wrapper | Có event schedule lock-safe, test scheduler pass | 67a81ad |
| EMR-03 | P0 | Done | EMR action-level permission matrix | Có permission constants + seeder + authorize points cho clinical write/export/sync push | 02802fa |
| EMR-04 | P1 | Done | Encounter aggregate model | Dùng `visit_episodes` làm encounter aggregate; clinical notes/prescriptions đã link `visit_episode_id`; có test pass | cedbd07 |
| EMR-05 | P1 | Done | Clinical Orders/Results domain | Order -> Result flow với trạng thái rõ ràng, aggregate payload đầy đủ | a083478 |
| EMR-06 | P1 | Done | EMR dedicated audit log | `emr_audit_logs` immutable + query được theo patient/encounter + hook sync/order/result | 9efc44d |
| EMR-07 | P1 | Done | PHI encryption rollout | Cast encrypted + migration/backfill an toàn cho PHI text fields EMR | 43f6855 |
| EMR-08 | P1 | Done | Clinical versioning | Revision history + optimistic lock + amend flow cho clinical note | 3dc4b31 |
| EMR-09 | P2 | Done | Internal EMR API v1 | Idempotent mutation endpoint amend clinical note + authz + tests | a52c936 |
| EMR-10 | P2 | Done | Reconciliation & observability | Command `emr:reconcile-integrity` + alert audit log + schedule + test | 88e5788 |

## Execution Rules

1. Implement per ticket and commit per ticket.
2. Update this backlog status and Git Ref right after each ticket.
3. Minimum checks per ticket:
   - `vendor/bin/pint --dirty`
   - targeted `php artisan test --filter=...`
4. End of wave checks:
   - `php artisan migrate:status`
   - `php artisan schema:assert-no-pending-migrations`
   - full `php artisan test`

## EMR Reconcile Runbook (v1)

1. Dry check integrity:
   - `php artisan emr:reconcile-integrity`
2. Gate mode (CI/release):
   - `php artisan emr:reconcile-integrity --strict`
3. Nếu có mismatch:
   - `missing_initial_revision` hoặc `note_revision_version_mismatch`: đối soát `clinical_notes.lock_version` và `clinical_note_revisions`.
   - `stale_pending_mutation`: kiểm tra `emr_api_mutations` chưa `processed_at`, re-run mutation hoặc clean request treo.
   - `order_result_state_mismatch`: kiểm tra trạng thái `clinical_results` final/amended có order completed tương ứng.
4. Audit trail:
   - Command luôn ghi `audit_logs` với `entity_type=automation`, `metadata.command=emr:reconcile-integrity`.

## Notes

- Current baseline: EMR outbound sync tables/services already exist (`emr_sync_events`, `emr_sync_logs`, `emr_patient_maps`).
- Current known gap: branch isolation not fully enforced for PlanItem/TreatmentSession.
