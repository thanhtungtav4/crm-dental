<?php

namespace App\Support;

enum ConversationProvider: string
{
    case Zalo = 'zalo';
    case Facebook = 'facebook';

    public function label(): string
    {
        return match ($this) {
            self::Zalo => 'Zalo OA',
            self::Facebook => 'Facebook Messenger',
        };
    }

    public function customerSource(): string
    {
        return $this->value;
    }

    public function customerSourceDetail(): string
    {
        return match ($this) {
            self::Zalo => 'zalo_oa_inbox',
            self::Facebook => 'facebook_page_inbox',
        };
    }

    public function fallbackCustomerLabel(string $suffix): string
    {
        return match ($this) {
            self::Zalo => 'Khách Zalo '.$suffix,
            self::Facebook => 'Khách Facebook '.$suffix,
        };
    }

    public function inboxDefaultBranchCode(): string
    {
        return match ($this) {
            self::Zalo => ClinicRuntimeSettings::zaloInboxDefaultBranchCode(),
            self::Facebook => ClinicRuntimeSettings::facebookInboxDefaultBranchCode(),
        };
    }

    public static function tryFromNullable(?string $provider): ?self
    {
        if (! is_string($provider)) {
            return null;
        }

        return self::tryFrom(trim(strtolower($provider)));
    }
}
