<?php

use Illuminate\Support\Facades\File;

it('renders shared patient workspace action partials from the page view', function (): void {
    $view = File::get(resource_path('views/filament/resources/patients/pages/view-patient.blade.php'));
    $copyButtonPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/copy-button.blade.php'));
    $detailActionPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/detail-action.blade.php'));
    $infoCardPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/info-card.blade.php'));
    $sectionHeaderPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/section-header.blade.php'));

    expect($view)
        ->toContain("@include('filament.resources.patients.pages.partials.copy-button'")
        ->toContain("@include('filament.resources.patients.pages.partials.detail-action'")
        ->toContain("@include('filament.resources.patients.pages.partials.info-card'")
        ->toContain("@include('filament.resources.patients.pages.partials.section-header'")
        ->toContain('@foreach($this->renderedPaymentBlocks as $block)')
        ->toContain('@foreach($this->renderedFormSections as $section)')
        ->toContain("@forelse(\$section['links'] as \$link)");

    expect($copyButtonPartial)
        ->toContain('copyToClipboard(@js($copyValue), @js($copyLabel))')
        ->toContain('heroicon-o-square-2-stack');

    expect($detailActionPartial)
        ->toContain('@if($url)')
        ->toContain('<a href="{{ $url }}" class="{{ $actionClass }}">')
        ->toContain('<span class="{{ $actionClass }}">{{ $label }}</span>');

    expect($infoCardPartial)
        ->toContain("@include('filament.resources.patients.pages.partials.copy-button'")
        ->toContain("x-filament::icon :icon=\"\$card['icon']\"")
        ->toContain("@elseif(\$card['href'])")
        ->toContain('crm-patient-info-age');

    expect($sectionHeaderPartial)
        ->toContain('<h3 class="crm-feature-card-title">{{ $title }}</h3>')
        ->toContain('<p class="crm-feature-card-description">{{ $description }}</p>')
        ->toContain('@if($action !== null)')
        ->toContain("class=\"crm-btn {{ \$action['button_class'] }} crm-btn-md\"");
});
