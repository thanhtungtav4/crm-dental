<?php

use App\Models\ClinicSetting;
use App\Support\ClinicRuntimeSettings;

it('returns configured clinic branding profile', function (): void {
    ClinicSetting::setValue('branding.clinic_name', 'Nha Khoa I-Dent', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.logo_url', 'https://example.com/logo.png', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.address', '123 Nguyen Van Linh, Da Nang', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.phone', '0909123456', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.email', 'contact@example.com', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.button_bg_color', '#3366ff', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.button_bg_hover_color', '#2244aa', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.button_text_color', '#fefefe', ['value_type' => 'text']);

    $profile = ClinicRuntimeSettings::brandingProfile();

    expect($profile['clinic_name'])->toBe('Nha Khoa I-Dent')
        ->and($profile['logo_url'])->toBe('https://example.com/logo.png')
        ->and($profile['address'])->toBe('123 Nguyen Van Linh, Da Nang')
        ->and($profile['phone'])->toBe('0909123456')
        ->and($profile['email'])->toBe('contact@example.com')
        ->and($profile['button_bg_color'])->toBe('#3366FF')
        ->and($profile['button_bg_hover_color'])->toBe('#2244AA')
        ->and($profile['button_text_color'])->toBe('#FEFEFE');
});

it('derives hover background color when hover setting is empty', function (): void {
    ClinicSetting::setValue('branding.button_bg_color', '#3366ff', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.button_bg_hover_color', '', ['value_type' => 'text']);

    $hoverColor = ClinicRuntimeSettings::brandingButtonHoverBackgroundColor();

    expect($hoverColor)->toBe('#2D5AE0');
});
