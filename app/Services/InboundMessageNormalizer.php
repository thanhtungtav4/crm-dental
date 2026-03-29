<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\ZaloWebhookEvent;

interface InboundMessageNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(ZaloWebhookEvent $event, array $payload): ?ConversationMessage;
}
