<?php

return [
    'medication_reminder_offset_days' => (int) env('CARE_MEDICATION_OFFSET_DAYS', 0),
    'post_treatment_follow_up_offset_days' => (int) env('CARE_FOLLOW_UP_OFFSET_DAYS', 3),
    'recall_default_offset_days' => (int) env('CARE_RECALL_DEFAULT_OFFSET_DAYS', 180),
    'no_show_recovery_delay_hours' => (int) env('CARE_NO_SHOW_RECOVERY_DELAY_HOURS', 2),
    'plan_follow_up_delay_days' => (int) env('CARE_PLAN_FOLLOW_UP_DELAY_DAYS', 2),
    'invoice_aging_reminder_delay_days' => (int) env('CARE_INVOICE_AGING_REMINDER_DELAY_DAYS', 1),
    'invoice_aging_reminder_min_interval_hours' => (int) env('CARE_INVOICE_AGING_REMINDER_MIN_INTERVAL_HOURS', 24),
    'report_snapshot_sla_hours' => (int) env('CARE_REPORT_SNAPSHOT_SLA_HOURS', 6),
    'report_snapshot_stale_after_hours' => (int) env('CARE_REPORT_SNAPSHOT_STALE_AFTER_HOURS', 24),
];
