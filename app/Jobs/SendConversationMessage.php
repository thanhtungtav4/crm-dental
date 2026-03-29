<?php

namespace App\Jobs;

use App\Models\ConversationMessage;
use App\Services\ConversationProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendConversationMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public int $conversationMessageId,
    ) {}

    public function handle(ConversationProviderManager $conversationProviderManager): void
    {
        $claim = DB::transaction(function (): ?array {
            $message = ConversationMessage::query()
                ->with('conversation')
                ->lockForUpdate()
                ->find($this->conversationMessageId);

            if (! $message instanceof ConversationMessage) {
                return null;
            }

            if (! in_array($message->status, [
                ConversationMessage::STATUS_PENDING,
                ConversationMessage::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $processingToken = (string) Str::uuid();

            $message->forceFill([
                'status' => ConversationMessage::STATUS_PENDING,
                'attempts' => (int) $message->attempts + 1,
                'processing_token' => $processingToken,
                'processed_at' => null,
                'next_retry_at' => null,
                'last_error' => null,
            ])->save();

            return [
                'message_id' => $message->id,
                'processing_token' => $processingToken,
            ];
        }, 3);

        if ($claim === null) {
            return;
        }

        $message = ConversationMessage::query()
            ->with('conversation')
            ->find($this->conversationMessageId);

        if (! $message instanceof ConversationMessage) {
            return;
        }

        $result = $conversationProviderManager
            ->outboundClientForMessage($message)
            ->send($message);

        DB::transaction(function () use ($claim, $result): void {
            $message = ConversationMessage::query()
                ->lockForUpdate()
                ->find($this->conversationMessageId);

            if (! $message instanceof ConversationMessage) {
                return;
            }

            if ($message->processing_token !== $claim['processing_token']) {
                return;
            }

            if ((bool) ($result['success'] ?? false)) {
                $message->markSent($result);

                return;
            }

            $message->markFailed($result);
        }, 3);
    }
}
