<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class RuntimeMailerFactory
{
    public const WEB_LEAD_INTERNAL_MAILER = 'web_lead_internal_notification';

    public function webLeadInternalMailer(): Mailer
    {
        Config::set(
            'mail.mailers.'.self::WEB_LEAD_INTERNAL_MAILER,
            ClinicRuntimeSettings::webLeadInternalEmailMailerConfig(),
        );

        Mail::purge(self::WEB_LEAD_INTERNAL_MAILER);

        /** @var Mailer $mailer */
        $mailer = Mail::mailer(self::WEB_LEAD_INTERNAL_MAILER);

        return $mailer;
    }
}
