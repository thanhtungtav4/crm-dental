<?php

namespace App\Console\Commands;

use App\Jobs\SendConversationMessage;
use App\Models\ConversationMessage;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryConversationMessageFailures extends Command
{
    protected $signature = 'conversations:retry-failed-messages {--limit=100}';

    protected $description = 'Xếp lại các outbound conversation message đã fail và đến hạn retry vào queue gửi.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy retry conversation message.',
        );

        $limit = min(500, max(1, (int) $this->option('limit')));
        $messageIds = ConversationMessage::query()
            ->where('direction', ConversationMessage::DIRECTION_OUTBOUND)
            ->where('status', ConversationMessage::STATUS_FAILED)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $retried = 0;

        foreach ($messageIds as $messageId) {
            $wasRequeued = DB::transaction(function () use ($messageId): bool {
                $message = ConversationMessage::query()
                    ->lockForUpdate()
                    ->find($messageId);

                if (! $message instanceof ConversationMessage) {
                    return false;
                }

                if (
                    $message->direction !== ConversationMessage::DIRECTION_OUTBOUND
                    || $message->status !== ConversationMessage::STATUS_FAILED
                    || $message->next_retry_at === null
                    || $message->next_retry_at->isFuture()
                ) {
                    return false;
                }

                $message->forceFill([
                    'status' => ConversationMessage::STATUS_PENDING,
                    'processing_token' => null,
                    'processed_at' => null,
                    'next_retry_at' => null,
                    'last_error' => null,
                ])->save();

                SendConversationMessage::dispatch($message->id)->afterCommit();

                return true;
            }, 3);

            if ($wasRequeued) {
                $retried++;
            }
        }

        $this->info(sprintf(
            'Conversation retry queue processed. scanned=%d retried=%d',
            $messageIds->count(),
            $retried,
        ));

        return self::SUCCESS;
    }
}
