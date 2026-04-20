<?php

namespace App\Filament\Forms\Components;

use App\Support\ToothSelectionViewConfig;
use Filament\Forms\Components\Field;

class ToothPicker extends Field
{
    protected string $view = 'filament.forms.components.tooth-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
        $this->afterStateHydrated(function (ToothPicker $component, mixed $state): void {
            if (is_string($state) && $state !== '') {
                $component->state($this->hydrateToothSelectionState($state));
            }
        });
        $this->dehydrateStateUsing(fn (mixed $state): mixed => $this->dehydrateToothSelectionState($state));
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $viewConfig = app(ToothSelectionViewConfig::class);

        return [
            'tabs' => $viewConfig->pickerTabs(),
            'toothGroups' => $viewConfig->pickerToothGroups(),
            'childTeethFlat' => $viewConfig->childTeethFlat(),
            'selectedButtonClasses' => $viewConfig->selectedPickerButtonClasses(),
            'defaultButtonClasses' => $viewConfig->defaultPickerButtonClasses(),
            'selectedLabelClasses' => $viewConfig->selectedPickerLabelClasses(),
            'defaultLabelClasses' => $viewConfig->defaultPickerLabelClasses(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function hydrateToothSelectionState(string $state): array
    {
        return array_values(array_filter(array_map(
            static fn (string $toothNumber): string => trim($toothNumber),
            explode(',', $state),
        ), static fn (string $toothNumber): bool => $toothNumber !== ''));
    }

    protected function dehydrateToothSelectionState(mixed $state): mixed
    {
        if (! is_array($state)) {
            return $state;
        }

        return implode(',', array_values(array_filter(array_map(
            static fn (mixed $toothNumber): string => trim((string) $toothNumber),
            $state,
        ), static fn (string $toothNumber): bool => $toothNumber !== '')));
    }
}
