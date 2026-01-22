<?php

namespace App\Livewire;

use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;
use MarcelWeidum\Passkeys\Livewire\Passkeys as BasePasskeys;
use Illuminate\View\View;

class PasskeysComponent extends MyProfileComponent
{
    protected string $view = 'livewire.passkeys-component';
    
    public static $sort = 50; // Position in My Profile page
    
    public function render(): View
    {
        return view($this->view);
    }
}
