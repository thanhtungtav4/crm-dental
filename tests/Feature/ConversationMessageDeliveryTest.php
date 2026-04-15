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

function configureFacebookConversationDeliveryRuntime(): void
{
    $settings = [
        'facebook.page_access_token' => 'facebook-page-access-token-001',
        'facebook.send_endpoint' => 'https://graph.facebook.com/v23.0/me/messages',
    ];

    foreach ($settings as $key => $value) {
        ClinicSetting::setValue($key, $value, [
            'group' => 'facebook',
            'value_type' => 'text',
            'is_secret' => $key === 'facebook.page_access_token',
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

it('delivers a pending outbound message through the facebook messenger client', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/v23.0/me/messages*' => Http::response([
            'recipient_id' => 'facebook-user-001',
            'message_id' => 'facebook-provider-msg-001',
        ], 200),
    ]);

    configureFacebookConversationDeliveryRuntime();

    $branch = Branch::factory()->create();
    $conversation = Conversation::factory()
        ->facebook()
        ->create([
            'branch_id' => $branch->id,
            'channel_key' => 'page-001',
            'external_conversation_key' => 'facebook:page-001:facebook-user-001',
            'external_user_id' => 'facebook-user-001',
        ]);

    $message = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => ConversationMessage::DIRECTION_OUTBOUND,
        'status' => ConversationMessage::STATUS_PENDING,
        'provider_message_id' => null,
        'body' => 'Cam on ban da nhan tin Facebook',
    ]);

    SendConversationMessage::dispatchSync($message->id);

    $freshMessage = $message->fresh();

    expect($freshMessage)->not->toBeNull()
        ->and($freshMessage?->status)->toBe(ConversationMessage::STATUS_SENT)
        ->and($freshMessage?->attempts)->toBe(1)
        ->and($freshMessage?->provider_message_id)->toBe('facebook-provider-msg-001')
        ->and($freshMessage?->last_error)->toBeNull();

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return str_starts_with($request->url(), 'https://graph.facebook.com/v23.0/me/messages')
            && str_contains($request->url(), 'access_token=facebook-page-access-token-001')
            && $request['messaging_type'] === 'RESPONSE'
            && $request['recipient']['id'] === 'facebook-user-001'
            && $request['message']['text'] === 'Cam on ban da nhan tin Facebook';
    });
});

it('returns transitioned message models from send failure and ignore boundaries', function (): void {
    $conversation = Conversation::factory()->create();

    $message = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => ConversationMessage::DIRECTION_OUTBOUND,
        'status' => ConversationMessage::STATUS_PENDING,
        'attempts' => 1,
        'body' => 'Boundary contract check',
    ]);

    $sentMessage = $message->markSent([
        'provider_message_id' => 'boundary-msg-001',
        'provider_status_code' => '200',
        'response' => ['ok' => true],
    ]);
    $failedMessage = $sentMessage->markFailed([
        'provider_status_code' => '500',
        'error' => 'Provider timeout',
    ]);

    expect($sentMessage)->toBeInstanceOf(ConversationMessage::class)
        ->and($sentMessage->is($message))->toBeTrue()
        ->and($failedMessage)->toBeInstanceOf(ConversationMessage::class)
        ->and($failedMessage->is($message))->toBeTrue()
        ->and($failedMessage->status)->toBe(ConversationMessage::STATUS_FAILED)
        ->and($failedMessage->next_retry_at)->not->toBeNull();

    $ignoredMessage = $failedMessage->markIgnored('Skipped by operator');

    expect($ignoredMessage)->toBeInstanceOf(ConversationMessage::class)
        ->and($ignoredMessage->is($message))->toBeTrue()
        ->and($ignoredMessage->status)->toBe(ConversationMessage::STATUS_IGNORED)
        ->and($ignoredMessage->last_error)->toBe('Skipped by operator')
        ->and($ignoredMessage->processed_at)->not->toBeNull();
});
