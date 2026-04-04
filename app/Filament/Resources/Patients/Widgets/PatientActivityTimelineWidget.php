<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Models\Patient;
use App\Services\PatientActivityTimelineReadModelService;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PatientActivityTimelineWidget extends Widget
{
    protected string $view = 'filament.resources.patients.widgets.patient-activity-timeline-widget';

    public ?Patient $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getActivities(): Collection
    {
        if (! $this->record) {
            return collect();
        }

        return app(PatientActivityTimelineReadModelService::class)
            ->timelineEntriesForPatient($this->record, 20);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $activities = $this->getActivities();

        return [
            'activities' => $this->renderedActivities($activities),
            'activityCount' => $activities->count(),
            'showsMaxActivitiesFooter' => $activities->count() === 20,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $activities
     * @return list<array<string, mixed>>
     */
    protected function renderedActivities(Collection $activities): array
    {
        return $activities
            ->map(function (array $activity): array {
                $type = (string) ($activity['type'] ?? '');
                $date = Arr::get($activity, 'date');

                return [
                    ...$activity,
                    'type_class' => $this->activityTypeClass($type),
                    'type_label' => $this->activityTypeLabel($type),
                    'description_excerpt' => Str::limit((string) ($activity['description'] ?? ''), 80),
                    'date_iso' => method_exists($date, 'toIso8601String') ? $date->toIso8601String() : null,
                    'date_label' => method_exists($date, 'format') ? $date->format('d/m/Y') : '-',
                    'time_label' => method_exists($date, 'format') ? $date->format('H:i') : '-',
                    'human_label' => method_exists($date, 'diffForHumans') ? $date->diffForHumans() : '',
                ];
            })
            ->values()
            ->all();
    }

    protected function activityTypeClass(string $type): string
    {
        return match ($type) {
            'appointment' => 'is-appointment',
            'treatment_plan' => 'is-treatment-plan',
            'invoice' => 'is-invoice',
            'payment' => 'is-payment',
            'branch_log' => 'is-branch-log',
            'note' => 'is-note',
            'audit' => 'is-default',
            default => 'is-default',
        };
    }

    protected function activityTypeLabel(string $type): string
    {
        return match ($type) {
            'appointment' => 'Lịch hẹn',
            'treatment_plan' => 'Kế hoạch điều trị',
            'invoice' => 'Hóa đơn',
            'payment' => 'Thanh toán',
            'branch_log' => 'Chuyển chi nhánh',
            'note' => 'Ghi chú',
            'audit' => 'Nhật ký hệ thống',
            default => $type,
        };
    }
}
