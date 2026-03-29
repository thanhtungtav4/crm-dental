<?php

use App\Jobs\SendConversationMessage;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Http;

function configureConversationDeliveryRuntime(): void
{
    $settings = [
        'zalo.access_token' => 'zalo-access-token-001',
        'zalo.send_endpoint' => 'https://openapi.zalo.me/v3.0/oa/message/cs',
    ];

    foreach ($settings as $key => $value) {
        ClinicSetting::setValue($key, $value, [
            'group' => 'zalo',
            'value_type' => 'text',
            'is_secret' => $key === 'zalo.access_token',
        ]);
    }
}

it('delivers a pending outbound message through the zalo oa client', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://openapi.zalo.me/v3.0/oa/message/cs' => Http::response([
            'error' => 0,
            'data' => [
                'message_id' => 'provider-msg-001',
            ],
        ], 200),
    ]);

    configureConversationDeliveryRuntime();

    $branch = Branch::factory()->create();
    $conversation = Conversation::factory()->create([
        'branch_id' => $branch->id,
        'channel_key' => 'oa-inbox-001',
        'external_conversation_key' => 'zalo:oa-inbox-001:user-delivery-001',
        'external_user_id' => 'user-delivery-001',
    ]);

    $message = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => ConversationMessage::DIRECTION_OUTBOUND,
        'status' => ConversationMessage::STATUS_PENDING,
        'provider_message_id' => null,
        'body' => 'Cam on ban da nhan tin',
    ]);

    SendConversationMessage::dispatchSync($message->id);

    $freshMessage = $message->fresh();

    expect($freshMessage)->not->toBeNull()
        ->and($freshMessage?->status)->toBe(ConversationMessage::STATUS_SENT)
        ->and($freshMessage?->attempts)->toBe(1)
        ->and($freshMessage?->provider_message_id)->toBe('provider-msg-001')
        ->and($freshMessage?->last_error)->toBeNull();

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return $request->url() === 'https://openapi.zalo.me/v3.0/oa/message/cs'
            && $request['recipient']['user_id'] === 'user-delivery-001'
            && $request['message']['text'] === 'Cam on ban da nhan tin';
    });
});

it('marks an outbound message as failed when the provider rejects the send request', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://openapi.zalo.me/v3.0/oa/message/cs' => Http::response([
            'error' => -216,
            'message' => 'Recipient blocked the OA.',
        ], 422),
    ]);

    configureConversationDeliveryRuntime();

    $conversation = Conversation::factory()->create([
        'external_conversation_key' => 'zalo:oa-inbox-001:user-delivery-002',
        'external_user_id' => 'user-delivery-002',
    ]);

    $message = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => ConversationMessage::DIRECTION_OUTBOUND,
        'status' => ConversationMessage::STATUS_PENDING,
        'provider_message_id' => null,
        'body' => 'Follow-up tu CRM',
    ]);

    SendConversationMessage::dispatchSync($message->id);

    $freshMessage = $message->fresh();

    expect($freshMessage)->not->toBeNull()
        ->and($freshMessage?->status)->toBe(ConversationMessage::STATUS_FAILED)
        ->and($freshMessage?->attempts)->toBe(1)
        ->and($freshMessage?->next_retry_at)->not->toBeNull()
        ->and($freshMessage?->last_error)->toContain('Recipient blocked the OA.');
});
