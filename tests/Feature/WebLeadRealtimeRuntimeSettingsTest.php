<?php

use App\Models\ClinicSetting;
use App\Support\ClinicRuntimeSettings;

it('uses default web lead realtime notification settings when custom settings are absent', function (): void {
    expect(ClinicRuntimeSettings::webLeadRealtimeNotificationEnabled())->toBeFalse()
        ->and(ClinicRuntimeSettings::webLeadRealtimeNotificationRoles())->toBe(['CSKH']);
});

it('reads web lead realtime notification settings from clinic settings', function (): void {
    ClinicSetting::setValue('web_lead.realtime_notification_enabled', true, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);
    ClinicSetting::setValue('web_lead.realtime_notification_roles', ['CSKH', 'Manager', 'CSKH'], [
        'group' => 'web_lead',
        'value_type' => 'json',
    ]);

    expect(ClinicRuntimeSettings::webLeadRealtimeNotificationEnabled())->toBeTrue()
        ->and(ClinicRuntimeSettings::webLeadRealtimeNotificationRoles())->toBe(['CSKH', 'Manager']);
});

it('uses default web lead internal email settings when custom settings are absent', function (): void {
    expect(ClinicRuntimeSettings::webLeadInternalEmailEnabled())->toBeFalse()
        ->and(ClinicRuntimeSettings::webLeadInternalEmailRecipientRoles())->toBe(['CSKH'])
        ->and(ClinicRuntimeSettings::webLeadInternalEmailRecipientEmails())->toBe([])
        ->and(ClinicRuntimeSettings::webLeadInternalEmailQueue())->toBe('web-lead-mail')
        ->and(ClinicRuntimeSettings::webLeadInternalEmailMaxAttempts())->toBe(5)
        ->and(ClinicRuntimeSettings::webLeadInternalEmailRetryDelayMinutes())->toBe(10)
        ->and(ClinicRuntimeSettings::webLeadInternalEmailSmtpScheme())->toBe('tls');
});

it('parses web lead internal email runtime settings from clinic settings', function (): void {
    ClinicSetting::setValue('web_lead.internal_email_enabled', true, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_recipient_roles', ['CSKH', 'Manager', 'CSKH'], [
        'group' => 'web_lead',
        'value_type' => 'json',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_recipient_emails', " LEAD@CLINIC.TEST \nops@clinic.test;lead@clinic.test ", [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_queue', 'mail-leads', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_max_attempts', 8, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_retry_delay_minutes', 25, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_smtp_scheme', 'none', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_smtp_host', 'smtp.internal.test', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_from_address', 'lead-bot@clinic.test', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);
    ClinicSetting::setValue('web_lead.internal_email_from_name', 'Lead Bot', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);

    expect(ClinicRuntimeSettings::webLeadInternalEmailEnabled())->toBeTrue()
        ->and(ClinicRuntimeSettings::webLeadInternalEmailRecipientRoles())->toBe(['CSKH', 'Manager'])
        ->and(ClinicRuntimeSettings::webLeadInternalEmailRecipientEmails())->toBe(['lead@clinic.test', 'ops@clinic.test'])
        ->and(ClinicRuntimeSettings::webLeadInternalEmailQueue())->toBe('mail-leads')
        ->and(ClinicRuntimeSettings::webLeadInternalEmailMaxAttempts())->toBe(8)
        ->and(ClinicRuntimeSettings::webLeadInternalEmailRetryDelayMinutes())->toBe(25)
        ->and(ClinicRuntimeSettings::webLeadInternalEmailSmtpScheme())->toBeNull()
        ->and(ClinicRuntimeSettings::webLeadInternalEmailMailerConfig())->toMatchArray([
            'transport' => 'smtp',
            'host' => 'smtp.internal.test',
            'port' => 587,
            'username' => '',
            'password' => '',
            'scheme' => null,
            'timeout' => 10,
        ]);
});

it('fails mailer config resolution when required runtime values are missing', function (): void {
    ClinicSetting::setValue('web_lead.internal_email_enabled', true, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    expect(fn (): array => ClinicRuntimeSettings::webLeadInternalEmailMailerConfig())
        ->toThrow(RuntimeException::class, 'Missing web lead internal email SMTP host.');

    ClinicSetting::setValue('web_lead.internal_email_smtp_host', 'smtp.internal.test', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);

    expect(fn (): array => ClinicRuntimeSettings::webLeadInternalEmailMailerConfig())
        ->toThrow(RuntimeException::class, 'from address');
});
