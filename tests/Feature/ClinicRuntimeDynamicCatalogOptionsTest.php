<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\ClinicSetting;
use App\Models\Payment;
use App\Support\ClinicRuntimeSettings;

it('returns default catalog options when custom settings are absent', function (): void {
    expect(ClinicRuntimeSettings::examIndicationOptions())
        ->toBe(ClinicRuntimeSettings::defaultExamIndicationOptions())
        ->and(ClinicRuntimeSettings::customerSourceOptions())
        ->toBe(ClinicRuntimeSettings::defaultCustomerSourceOptions())
        ->and(ClinicRuntimeSettings::customerStatusOptions())
        ->toBe(ClinicRuntimeSettings::defaultCustomerStatusOptions())
        ->and(ClinicRuntimeSettings::careTypeDisplayOptions())
        ->toBe(ClinicRuntimeSettings::defaultCareTypeOptions())
        ->and(ClinicRuntimeSettings::paymentSourceLabels())
        ->toBe(ClinicRuntimeSettings::defaultPaymentSourceLabels())
        ->and(ClinicRuntimeSettings::paymentDirectionLabels())
        ->toBe(ClinicRuntimeSettings::defaultPaymentDirectionLabels())
        ->and(ClinicRuntimeSettings::genderOptions())
        ->toBe(ClinicRuntimeSettings::defaultGenderOptions());
});

it('uses custom catalog options from clinic settings json values', function (): void {
    ClinicSetting::setValue('catalog.customer_sources', [
        'website' => 'Website',
        'tiktok' => 'TikTok',
    ], [
        'group' => 'catalog',
        'value_type' => 'json',
    ]);

    ClinicSetting::setValue('catalog.customer_statuses', [
        'new' => 'Mới',
        'lead' => 'Lead nóng',
    ], [
        'group' => 'catalog',
        'value_type' => 'json',
    ]);

    ClinicSetting::setValue('catalog.payment_sources', [
        'patient' => 'Khách lẻ',
        'insurance' => 'Bảo hiểm tư nhân',
        'other' => 'Nguồn khác',
    ], [
        'group' => 'catalog',
        'value_type' => 'json',
    ]);

    expect(ClinicRuntimeSettings::customerSourceOptions())
        ->toBe([
            'website' => 'Website',
            'tiktok' => 'TikTok',
        ])
        ->and(ClinicRuntimeSettings::defaultCustomerSource())
        ->toBe('website')
        ->and(ClinicRuntimeSettings::customerStatusLabel('lead'))
        ->toBe('Lead nóng')
        ->and(ClinicRuntimeSettings::defaultCustomerStatus())
        ->toBe('lead')
        ->and(ClinicRuntimeSettings::paymentSourceLabel('insurance'))
        ->toBe('Bảo hiểm tư nhân');
});

it('formats payment labels using dynamic catalog settings', function (): void {
    ClinicSetting::setValue('catalog.payment_directions', [
        'receipt' => 'Thu tiền',
        'refund' => 'Hoàn tiền',
    ], [
        'group' => 'catalog',
        'value_type' => 'json',
    ]);

    $payment = new Payment([
        'direction' => 'refund',
        'payment_source' => 'insurance',
        'method' => 'cash',
    ]);

    expect($payment->getDirectionLabel())->toBe('Hoàn tiền')
        ->and($payment->getSourceLabel())->toBe('Bảo hiểm')
        ->and($payment->getSourceBadgeColor())->toBe('info');
});

it('exposes dynamic catalog provider fields in integration settings', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());

    $catalogProvider = $providers->firstWhere('group', 'catalog');

    expect($catalogProvider)->not->toBeNull();

    $catalogKeys = collect($catalogProvider['fields'] ?? [])
        ->pluck('key')
        ->values()
        ->all();

    expect($catalogKeys)
        ->toContain('catalog.exam_indications')
        ->toContain('catalog.customer_sources')
        ->toContain('catalog.customer_statuses')
        ->toContain('catalog.care_types')
        ->toContain('catalog.payment_sources')
        ->toContain('catalog.payment_directions')
        ->toContain('catalog.gender_options');
});
