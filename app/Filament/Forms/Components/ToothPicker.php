<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class ToothPicker extends Field
{
    protected string $view = 'filament.forms.components.tooth-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
    }
}
