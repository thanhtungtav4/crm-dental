<?php

namespace App\Livewire;

use Illuminate\View\View;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class PasskeysComponent extends MyProfileComponent
{
    protected string $view = 'livewire.passkeys-component';

    public static $sort = 50;

    public function render(): View
    {
        return view($this->view);
    }
}
