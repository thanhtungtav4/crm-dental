<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Models\ZaloWebhookEvent;
use App\Services\ConversationLeadBindingService;

function configureConversationInboxWebhookRuntime(Branch $branch): void
{
    $settings = [
        'zalo.enabled' => true,
        'zalo.oa_id' => 'oa-inbox-001',
        'zalo.app_id' => 'app-inbox-001',
        'zalo.app_secret' => 'app_secret_for_webhook_signature_001',
        'zalo.access_token' => 'zalo-access-token-001',
        'zalo.webhook_token' => 'secure-token-12345678901234567890',
        'zalo.send_endpoint' => 'https://openapi.zalo.me/v3.0/oa/message/cs',
        'zalo.inbox_default_branch_code' => $branch->code,
        'zalo.inbox_polling_seconds' => 3,
    ];

    foreach ($settings as $key => $value) {
        ClinicSetting::setValue($key, $value, [
            'group' => 'zalo',
            'value_type' => match ($key) {
                'zalo.enabled' => 'boolean',
                'zalo.inbox_polling_seconds' => 'integer',
                default => 'text',
            },
            'is_secret' => in_array($key, [
                'zalo.app_secret',
                'zalo.access_token',
                'zalo.webhook_token',
            ], true),
        ]);
    }
}

/**
 * @param  array<string, mixed>  $payload
 */
function signConversationInboxWebhookPayload(array $payload): string
{
    $normalize = static function (mixed $value) use (&$normalize): mixed {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            $value[$key] = $normalize($child);
        }

        ksort($value);

        return $value;
    };

    $payloadJson = json_encode($normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return hash_hmac('sha256', is_string($payloadJson) ? $payloadJson : '{}', 'app_secret_for_webhook_signature_001');
}

/**
 * @param  array<string, mixed>  $payload
 */
function postConversationInboxWebhook(array $payload): \Illuminate\Testing\TestResponse
{
    return test()->withHeaders([
        'X-Zalo-Signature' => signConversationInboxWebhookPayload($payload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $payload);
}

it('normalizes inbound zalo webhook text into a conversation and canonical message', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-ZALO-INBOX',
        'active' => true,
    ]);
    configureConversationInboxWebhookRuntime($branch);

    $payload = [
        'event_id' => 'evt_001',
        'event_name' => 'user_send_text',
        'oa_id' => 'oa-inbox-001',
        'timestamp' => 1_735_689_600,
        'sender' => [
            'id' => 'zalo_user_001',
            'name' => 'Nguyen Van A',
        ],
        'message' => [
            'msg_id' => 'msg_001',
            'text' => 'Xin chao CRM',
        ],
    ];

    postConversationInboxWebhook($payload)
        ->assertSuccessful()
        ->assertJsonPath('duplicate', false);

    $conversation = Conversation::query()->firstOrFail();
    $message = ConversationMessage::query()->firstOrFail();
    $event = ZaloWebhookEvent::query()->firstOrFail();

    expect($conversation->provider)->toBe(Conversation::PROVIDER_ZALO)
        ->and($conversation->channel_key)->toBe('oa-inbox-001')
        ->and($conversation->external_user_id)->toBe('zalo_user_001')
        ->and($conversation->external_display_name)->toBe('Nguyen Van A')
        ->and($conversation->branch_id)->toBe($branch->id)
        ->and($conversation->unread_count)->toBe(1)
        ->and($conversation->latest_message_preview)->toBe('Xin chao CRM')
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($message->direction)->toBe(ConversationMessage::DIRECTION_INBOUND)
        ->and($message->status)->toBe(ConversationMessage::STATUS_RECEIVED)
        ->and($message->body)->toBe('Xin chao CRM')
        ->and($event->normalize_status)->toBe('normalized')
        ->and($event->conversation_id)->toBe($conversation->id)
        ->and($event->message_id)->toBe($message->id);
});

it('does not create duplicate canonical message records when the same webhook is replayed', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-ZALO-DUPE',
        'active' => true,
    ]);
    configureConversationInboxWebhookRuntime($branch);

    $payload = [
        'event_id' => 'evt_duplicate_001',
        'event_name' => 'user_send_text',
        'oa_id' => 'oa-inbox-001',
        'timestamp' => 1_735_689_600,
        'sender' => ['id' => 'zalo_user_dup'],
        'message' => [
            'msg_id' => 'msg_duplicate_001',
            'text' => 'Noi dung duplicate',
        ],
    ];

    postConversationInboxWebhook($payload)->assertSuccessful()->assertJsonPath('duplicate', false);
    postConversationInboxWebhook($payload)->assertSuccessful()->assertJsonPath('duplicate', true);

    expect(Conversation::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(1)
        ->and(ZaloWebhookEvent::query()->count())->toBe(1);
});

it('marks unsupported payloads as ignored without surfacing them into the inbox', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-ZALO-IGNORED',
        'active' => true,
    ]);
    configureConversationInboxWebhookRuntime($branch);

    $payload = [
        'event_id' => 'evt_ignored_001',
        'event_name' => 'user_send_sticker',
        'oa_id' => 'oa-inbox-001',
        'timestamp' => 1_735_689_600,
        'sender' => ['id' => 'zalo_user_sticker'],
        'message' => [
            'msg_id' => 'msg_sticker_001',
            'attachments' => [
                ['type' => 'sticker'],
            ],
        ],
    ];

    postConversationInboxWebhook($payload)->assertSuccessful();

    $event = ZaloWebhookEvent::query()->firstOrFail();

    expect(Conversation::query()->count())->toBe(0)
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and($event->normalize_status)->toBe('ignored')
        ->and($event->error_message)->toContain('Chỉ hỗ trợ inbound text message');
});

it('keeps routing later inbound messages into the same bound lead conversation', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-ZALO-LEAD',
        'active' => true,
    ]);
    configureConversationInboxWebhookRuntime($branch);

    $firstPayload = [
        'event_id' => 'evt_lead_001',
        'event_name' => 'user_send_text',
        'oa_id' => 'oa-inbox-001',
        'timestamp' => 1_735_689_600,
        'sender' => [
            'id' => 'zalo_user_lead',
            'name' => 'Tran Thi B',
        ],
        'message' => [
            'msg_id' => 'msg_lead_001',
            'text' => 'Cho minh xin gia nieng rang',
        ],
    ];

    postConversationInboxWebhook($firstPayload)->assertSuccessful();

    $conversation = Conversation::query()->firstOrFail();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $customer = app(ConversationLeadBindingService::class)->createLead(
        $conversation,
        [
            'full_name' => 'Tran Thi B',
            'branch_id' => $branch->id,
        ],
        $manager,
    );

    $secondPayload = [
        'event_id' => 'evt_lead_002',
        'event_name' => 'user_send_text',
        'oa_id' => 'oa-inbox-001',
        'timestamp' => 1_735_689_660,
        'sender' => [
            'id' => 'zalo_user_lead',
            'name' => 'Tran Thi B',
        ],
        'message' => [
            'msg_id' => 'msg_lead_002',
            'text' => 'Minh co the den thu bay khong',
        ],
    ];

    postConversationInboxWebhook($secondPayload)->assertSuccessful();

    expect(Conversation::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(2)
        ->and($conversation->fresh()?->customer_id)->toBe($customer->id)
        ->and($customer->source)->toBe('zalo')
        ->and($customer->source_detail)->toBe('zalo_oa_inbox');
});
