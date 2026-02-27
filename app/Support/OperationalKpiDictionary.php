<?php

namespace App\Support;

class OperationalKpiDictionary
{
    public const VERSION = 'operational_kpi_formula.v2';

    /**
     * @return array{
     *     version:string,
     *     event_definitions:array<string, string>,
     *     metrics:array<string, array{label:string,formula:string}>
     * }
     */
    public static function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'event_definitions' => [
                'booking_event' => 'appointments within reporting window',
                'visit_event' => 'visit_episodes with arrived_at or in_chair_at or status in_progress/completed',
                'no_show_event' => 'appointments with status no_show',
                'chair_event' => 'visit_episodes planned_duration_minutes and chair_minutes',
                'recall_event' => 'notes with care_type=recall_recare and care_status=done',
            ],
            'metrics' => [
                'booking_to_visit_rate' => [
                    'label' => 'Booking -> Visit (%)',
                    'formula' => 'visit_count / booking_count * 100',
                ],
                'no_show_rate' => [
                    'label' => 'No-show (%)',
                    'formula' => 'no_show_count / booking_count * 100',
                ],
                'chair_utilization_rate' => [
                    'label' => 'Chair utilization (%)',
                    'formula' => 'actual_chair_minutes / planned_chair_minutes * 100',
                ],
                'treatment_acceptance_rate' => [
                    'label' => 'Treatment acceptance (%)',
                    'formula' => 'approved_plan_items / proposed_or_approved_or_declined_plan_items * 100',
                ],
                'recall_rate' => [
                    'label' => 'Recall completion (%)',
                    'formula' => 'recall_done / recall_total * 100',
                ],
            ],
        ];
    }
}
