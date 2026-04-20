<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Models\Patient;
use App\Services\PatientActivityTimelineReadModelService;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
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
     * @return array{
     *     title:string,
     *     count_label:string,
     *     empty_state:array{
     *         title:string,
     *         description:string,
     *     },
     *     activities:list<array<string, mixed>>,
     *     shows_max_activities_footer:bool,
     *     footer_label:string,
     * }
     */
    public function timelineViewState(): array
    {
        $activities = $this->getActivities();

        return [
            'title' => 'Hoạt động gần đây',
            'count_label' => $activities->count().' hoạt động',
            'empty_state' => [
                'title' => 'Chưa có hoạt động',
                'description' => 'Lịch sử hoạt động của bệnh nhân sẽ hiển thị ở đây.',
            ],
            'activities' => $this->renderedActivities($activities),
            'shows_max_activities_footer' => $activities->count() === 20,
            'footer_label' => 'Hiển thị 20 hoạt động gần nhất',
        ];
    }

    public function render(): View
    {
        return view($this->view, [
            'viewState' => $this->timelineViewState(),
        ]);
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
