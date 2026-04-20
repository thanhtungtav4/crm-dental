<?php

namespace App\Filament\Forms\Components;

use App\Support\ToothSelectionViewConfig;
use Filament\Forms\Components\Field;

class ToothSelector extends Field
{
    protected string $view = 'filament.forms.components.tooth-selector';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $viewConfig = app(ToothSelectionViewConfig::class);

        return [
            'selectorRows' => $viewConfig->selectorRows(),
            'legendItems' => $viewConfig->selectorLegendItems(),
            'selectedButtonClasses' => $viewConfig->selectedSelectorButtonClasses(),
            'defaultButtonClasses' => $viewConfig->defaultSelectorButtonClasses(),
            'emptySelectionLabel' => $viewConfig->emptySelectionLabel(),
        ];
    }
}
