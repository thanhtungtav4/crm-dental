<?php

use Illuminate\Support\Facades\File;

it('does not apply global anchor color to filament buttons', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)->toContain('a:not(.fi-btn):not(.fi-icon-btn):not(.crm-btn):not(.crm-patient-phone-chip):not(.crm-patient-info-link):not(.crm-table-icon-btn)');
    expect($css)->not->toContain("\na {\n  color: var(--crm-primary);\n}");
});

it('keeps primary button label and icon colors readable', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)->toContain('.fi-btn:not(.fi-outlined).fi-color.fi-color-primary,');
    expect($css)->toContain('--text: var(--crm-brand-button-text);');
    expect($css)->toContain('color: var(--crm-brand-button-text) !important;');
    expect($css)->toContain('.fi-btn:not(.fi-outlined).fi-color.fi-color-primary .fi-icon,');
});

it('keeps info button text and icon readable with blue background', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)->toContain('.fi-btn:not(.fi-outlined).fi-color.fi-color-info,');
    expect($css)->toContain('--bg: var(--crm-brand-button-bg);');
    expect($css)->toContain('--hover-bg: var(--crm-brand-button-bg-hover);');
    expect($css)->toContain('--crm-brand-button-text: #ffffff;');
});

it('keeps custom crm primary buttons aligned with brand button palette', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)->toContain('.crm-btn-primary {');
    expect($css)->toContain('background: var(--crm-brand-button-bg);');
    expect($css)->toContain('color: var(--crm-brand-button-text) !important;');
    expect($css)->toContain('.crm-btn-primary:hover {');
    expect($css)->toContain('background: var(--crm-brand-button-bg-hover);');
});

it('keeps patient header phone chip and info links from inheriting global anchor color', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)->toContain('.crm-patient-phone-chip,');
    expect($css)->toContain('.crm-patient-phone-chip:visited {');
    expect($css)->toContain('color: #fff !important;');
    expect($css)->toContain('.crm-patient-phone-chip *,');
    expect($css)->toContain('-webkit-text-fill-color: #fff !important;');
    expect($css)->toContain('.crm-patient-info-link {');
    expect($css)->toContain('color: inherit !important;');
});

it('keeps patient header action colors unified for non-danger buttons', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)->toContain('.fi-page.fi-resource-patients.fi-resource-view-record-page .fi-header-actions-ctn .fi-color-success.fi-btn {');
    expect($css)->toContain('.fi-page.fi-resource-patients.fi-resource-view-record-page .fi-header-actions-ctn .fi-color-warning.fi-btn {');
    expect($css)->toContain('.fi-page.fi-resource-patients.fi-resource-view-record-page .fi-header-actions-ctn .fi-color-info.fi-btn {');
    expect($css)->toContain('.fi-page.fi-resource-patients.fi-resource-view-record-page .fi-header-actions-ctn .fi-color-primary.fi-btn {');
    expect($css)->toContain('background: var(--crm-brand-button-bg);');
    expect($css)->toContain('color: var(--crm-brand-button-text);');
});
