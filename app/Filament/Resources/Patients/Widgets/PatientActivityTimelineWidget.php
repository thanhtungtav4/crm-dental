<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Models\Patient;
use App\Services\PatientActivityTimelineReadModelService;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

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
}
