<?php

use App\Jobs\SendConversationMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Queue;

it('requeues only failed outbound conversation messages that are due for retry', function (): void {
    Queue::fake();

    $dueMessage = ConversationMessage::factory()->create([
        'conversation_id' => Conversation::factory(),
        'direction' => ConversationMessage::DIRECTION_OUTBOUND,
        'status' => ConversationMessage::STATUS_FAILED,
        'next_retry_at' => now()->subMinute(),
        'last_error' => 'Temporary provider outage',
    ]);

    $futureMessage = ConversationMessage::factory()->create([
        'conversation_id' => Conversation::factory(),
        'direction' => ConversationMessage::DIRECTION_OUTBOUND,
        'status' => ConversationMessage::STATUS_FAILED,
        'next_retry_at' => now()->addMinutes(10),
        'last_error' => 'Retry later',
    ]);

    $receivedMessage = ConversationMessage::factory()->create([
        'conversation_id' => Conversation::factory(),
        'direction' => ConversationMessage::DIRECTION_INBOUND,
        'status' => ConversationMessage::STATUS_RECEIVED,
        'next_retry_at' => now()->subMinute(),
    ]);

    $this->artisan('conversations:retry-failed-messages')
        ->expectsOutputToContain('retried=1')
        ->assertSuccessful();

    expect($dueMessage->fresh())
        ->status->toBe(ConversationMessage::STATUS_PENDING)
        ->and($dueMessage->fresh()?->next_retry_at)->toBeNull()
        ->and($dueMessage->fresh()?->last_error)->toBeNull()
        ->and($futureMessage->fresh()?->status)->toBe(ConversationMessage::STATUS_FAILED)
        ->and($receivedMessage->fresh()?->status)->toBe(ConversationMessage::STATUS_RECEIVED);

    Queue::assertPushed(SendConversationMessage::class, function (SendConversationMessage $job) use ($dueMessage): bool {
        return $job->conversationMessageId === $dueMessage->id;
    });
    Queue::assertPushed(SendConversationMessage::class, 1);
});
