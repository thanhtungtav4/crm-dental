<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Support\ConversationProvider;

interface OutboundMessageClient
{
    public function provider(): ConversationProvider;

    /**
     * @return array{
     *     success:bool,
     *     status:int|null,
     *     provider_message_id:string|null,
     *     provider_status_code:string|null,
     *     error:string|null,
     *     response:array<string, mixed>|null
     * }
     */
    public function send(ConversationMessage $message): array;
}
