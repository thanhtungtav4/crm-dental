<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Support\ConversationProvider;

interface InboundMessageNormalizer
{
    public function provider(): ConversationProvider;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(object $event, array $payload): ?ConversationMessage;
}
