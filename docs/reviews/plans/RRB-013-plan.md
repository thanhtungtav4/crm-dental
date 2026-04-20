# RRB-013 Plan — Integration Contract Tests and Provider Catalog Cross-Reference

**status**: Completed
**last_updated**: 2026-04-16
**owner**: codex

---

## Objective

Verify integration provider catalog completeness (all 7 providers in health read-model), action service readiness coverage (5 providers), and dead-letter backlog reader correctness for EMR and Google Calendar lanes.

---

## Scope

| Module | Service | Description |
|---|---|---|
| `IntegrationProviderHealthReadModelService` | `cards()`, `provider()`, `counts()` | 7-provider catalog, structural key contract |
| `IntegrationProviderActionService` | `readinessReport()`, `readinessNotification()` | 5-provider readiness label + notification payload |
| `IntegrationOperationalReadModelService` | `emrDeadBacklogCount()`, `googleCalendarDeadBacklogCount()` | Dead-letter count correctness with seeded events |

---

## Test Files

### New (written this session)

| File | Tests | Assertions | Coverage |
|---|---|---|---|
| `IntegrationProviderCatalogCrossReferenceTest.php` | 11 | 189 | 7-provider catalog; structural keys; counts sum; unknown-key exception; readiness accessible; report keys; notification keys; dead-letter readers; seeded EMR dead events; seeded GCal dead events; scoped dead-letter counts |

### Pre-existing (all pass, 67 tests)

| File | Notes |
|---|---|
| `IntegrationOperationalReadModelServiceTest.php` | Web lead, webhook, EMR, GCal, ZNS operational counts |
| `IntegrationProviderActionServiceTest.php` | Readiness notifications, GCal + EMR action reports |
| `IntegrationProviderHealthReadModelServiceTest.php` | Health cards, web-lead degraded, snapshot cards |
| `IntegrationProviderRuntimeGateTest.php` | Skip states, ingress gate failures |
| `IntegrationSettingsAuditReadModelServiceTest.php` | Rendered audit log payloads |
| `ZnsOperationalReadModelServiceTest.php` | ZNS backlog, retention, dead-letter |
| `IntegrationOperationalPayloadGovernanceTest.php` | Encrypted payload columns |
| `IntegrationSecretRotationWorkflowTest.php` | Grace window rotation flow |
| `IntegrationSettingsConcurrencyTest.php` | Stale revision rejection |

---

## Key Findings

- `readinessReport()` returns `['success', 'title', 'body', 'score']` — NOT `['label', 'issues', 'recommendations']`
- `readinessLabel()` is a protected method; the public surface is `readinessReport()` and `readinessNotification()`
- `emr_sync_events` column is `attempts` (not `attempt_count`); requires `event_key`, `event_type`, `payload_checksum`
- `google_calendar_sync_events` column is `attempts`; requires `event_key`, `event_type`, `payload_checksum`, non-null `appointment_id`
- `IntegrationProviderActionService::readinessReport()` supports only 5 providers — no `google_calendar` or `emr`
- `IntegrationProviderHealthReadModelService::provider()` throws `InvalidArgumentException` for unknown keys

---

## Results

**Total (new + existing)**: 78 tests | 766 assertions | 0 failures
