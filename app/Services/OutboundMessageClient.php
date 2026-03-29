<?php

namespace App\Services;

use App\Models\ConversationMessage;

interface OutboundMessageClient
{
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
