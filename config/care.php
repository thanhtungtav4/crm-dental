<?php

return [
    'automation_actor_user_id' => env('CARE_AUTOMATION_ACTOR_USER_ID'),
    'scheduler_automation_actor_user_id' => env(
        'SCHEDULER_AUTOMATION_ACTOR_USER_ID',
        env('CARE_AUTOMATION_ACTOR_USER_ID'),
    ),
    'scheduler_automation_actor_required_role' => env('SCHEDULER_AUTOMATION_ACTOR_REQUIRED_ROLE', 'AutomationService'),
    'medication_reminder_offset_days' => (int) env('CARE_MEDICATION_OFFSET_DAYS', 0),
    'post_treatment_follow_up_offset_days' => (int) env('CARE_FOLLOW_UP_OFFSET_DAYS', 3),
    'recall_default_offset_days' => (int) env('CARE_RECALL_DEFAULT_OFFSET_DAYS', 180),
    'no_show_recovery_delay_hours' => (int) env('CARE_NO_SHOW_RECOVERY_DELAY_HOURS', 2),
    'plan_follow_up_delay_days' => (int) env('CARE_PLAN_FOLLOW_UP_DELAY_DAYS', 2),
    'invoice_aging_reminder_delay_days' => (int) env('CARE_INVOICE_AGING_REMINDER_DELAY_DAYS', 1),
    'invoice_aging_reminder_min_interval_hours' => (int) env('CARE_INVOICE_AGING_REMINDER_MIN_INTERVAL_HOURS', 24),
    'report_snapshot_sla_hours' => (int) env('CARE_REPORT_SNAPSHOT_SLA_HOURS', 6),
    'report_snapshot_stale_after_hours' => (int) env('CARE_REPORT_SNAPSHOT_STALE_AFTER_HOURS', 24),
    'scheduler_command_timeout_seconds' => (int) env('CARE_SCHEDULER_COMMAND_TIMEOUT_SECONDS', 180),
    'scheduler_command_max_attempts' => (int) env('CARE_SCHEDULER_COMMAND_MAX_ATTEMPTS', 2),
    'scheduler_command_retry_delay_seconds' => (int) env('CARE_SCHEDULER_COMMAND_RETRY_DELAY_SECONDS', 15),
    'scheduler_command_alert_after_seconds' => (int) env('CARE_SCHEDULER_COMMAND_ALERT_AFTER_SECONDS', 120),
    'security_mfa_required_roles' => explode(',', (string) env('CARE_SECURITY_MFA_REQUIRED_ROLES', 'Admin,Manager')),
    'security_session_idle_timeout_minutes' => (int) env('CARE_SECURITY_SESSION_IDLE_TIMEOUT_MINUTES', 30),
    'security_login_max_attempts' => (int) env('CARE_SECURITY_LOGIN_MAX_ATTEMPTS', 5),
    'security_login_lockout_minutes' => (int) env('CARE_SECURITY_LOGIN_LOCKOUT_MINUTES', 15),
    'security_enforce_in_tests' => (bool) env('CARE_SECURITY_ENFORCE_IN_TESTS', false),
    'ops_alert_runbook' => [
        'backup_health' => [
            'owner_role' => 'Admin',
            'threshold' => 'latest_backup_age_hours<=26',
            'runbook' => 'Backup health gate + manual verification',
        ],
        'restore_drill' => [
            'owner_role' => 'Admin',
            'threshold' => 'restore_drill=pass_daily',
            'runbook' => 'Restore drill command and checksum verification',
        ],
        'scheduler_runtime' => [
            'owner_role' => 'AutomationService',
            'threshold' => 'runtime_seconds<=alert_after_seconds',
            'runbook' => 'Retry policy + alert on failure/sla breach',
        ],
        'kpi_snapshot_sla' => [
            'owner_role' => 'Manager',
            'threshold' => 'snapshot_sla_status=on_time',
            'runbook' => 'Snapshot SLA check + ownership follow-up',
        ],
        'security_login_lockout' => [
            'owner_role' => 'Admin',
            'threshold' => 'failed_login_attempts<=security.login_max_attempts',
            'runbook' => 'Investigate suspicious login attempts and unblock workflow',
        ],
    ],
];
