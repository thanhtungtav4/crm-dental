<?php

return [
    'medication_reminder_offset_days' => (int) env('CARE_MEDICATION_OFFSET_DAYS', 0),
    'post_treatment_follow_up_offset_days' => (int) env('CARE_FOLLOW_UP_OFFSET_DAYS', 3),
];
