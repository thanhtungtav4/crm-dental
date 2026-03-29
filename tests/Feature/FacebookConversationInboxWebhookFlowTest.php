<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FacebookWebhookEvent;
use App\Models\User;
use App\Services\ConversationLeadBindingService;

function configureFacebookConversationInboxRuntime(Branch $branch): void
{
    $settings = [
        'facebook.enabled' => true,
        'facebook.page_id' => 'facebook-page-001',
        'facebook.app_id' => 'facebook-app-001',
        'facebook.app_secret' => 'facebook_app_secret_for_signature_001',
        'facebook.webhook_verify_token' => 'facebook-verify-token-123456789012345',
        'facebook.page_access_token' => 'facebook-page-access-token-001',
        'facebook.send_endpoint' => 'https://graph.facebook.com/v23.0/me/messages',
        'facebook.inbox_default_branch_code' => $branch->code,
    ];

    foreach ($settings as $key => $value) {
        ClinicSetting::setValue($key, $value, [
            'group' => 'facebook',
            'value_type' => $key === 'facebook.enabled' ? 'boolean' : 'text',
            'is_secret' => in_array($key, [
                'facebook.app_secret',
                'facebook.webhook_verify_token',
                'facebook.page_access_token',
            ], true),
        ]);
    }
}

/**
 * @param  array<string, mixed>  $payload
 */
function signFacebookConversationInboxPayload(array $payload): string
{
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return 'sha256='.hash_hmac('sha256', is_string($payloadJson) ? $payloadJson : '{}', 'facebook_app_secret_for_signature_001');
}

/**
 * @param  array<string, mixed>  $payload
 */
function postFacebookConversationInboxWebhook(array $payload): \Illuminate\Testing\TestResponse
{
    return test()->withHeaders([
        'X-Hub-Signature-256' => signFacebookConversationInboxPayload($payload),
    ])->postJson('/api/v1/integrations/facebook/webhook', $payload);
}

it('normalizes inbound facebook messenger text into a conversation and canonical message', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-FACEBOOK-INBOX',
        'active' => true,
    ]);
    configureFacebookConversationInboxRuntime($branch);

    $payload = [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'facebook-page-001',
                'time' => 1_735_689_600_000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'facebook_user_001'],
                        'recipient' => ['id' => 'facebook-page-001'],
                        'timestamp' => 1_735_689_600_000,
                        'message' => [
                            'mid' => 'mid.facebook.001',
                            'text' => 'Xin chao CRM tu Facebook',
                        ],
                    ],
                ],
            ],
        ],
    ];

    postFacebookConversationInboxWebhook($payload)
        ->assertSuccessful()
        ->assertJsonPath('duplicate', false);

    $conversation = Conversation::query()->firstOrFail();
    $message = ConversationMessage::query()->firstOrFail();
    $event = FacebookWebhookEvent::query()->firstOrFail();

    expect($conversation->provider)->toBe(Conversation::PROVIDER_FACEBOOK)
        ->and($conversation->channel_key)->toBe('facebook-page-001')
        ->and($conversation->external_user_id)->toBe('facebook_user_001')
        ->and($conversation->branch_id)->toBe($branch->id)
        ->and($conversation->unread_count)->toBe(1)
        ->and($conversation->latest_message_preview)->toBe('Xin chao CRM tu Facebook')
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($message->direction)->toBe(ConversationMessage::DIRECTION_INBOUND)
        ->and($message->status)->toBe(ConversationMessage::STATUS_RECEIVED)
        ->and($message->body)->toBe('Xin chao CRM tu Facebook')
        ->and($event->normalize_status)->toBe('normalized')
        ->and($event->conversation_id)->toBe($conversation->id)
        ->and($event->message_id)->toBe($message->id);
});

it('does not create duplicate canonical message records when the same facebook webhook is replayed', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-FACEBOOK-DUPE',
        'active' => true,
    ]);
    configureFacebookConversationInboxRuntime($branch);

    $payload = [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'facebook-page-001',
                'time' => 1_735_689_600_000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'facebook_user_dup'],
                        'recipient' => ['id' => 'facebook-page-001'],
                        'timestamp' => 1_735_689_600_000,
                        'message' => [
                            'mid' => 'mid.facebook.duplicate.001',
                            'text' => 'Noi dung duplicate Facebook',
                        ],
                    ],
                ],
            ],
        ],
    ];

    postFacebookConversationInboxWebhook($payload)->assertSuccessful()->assertJsonPath('duplicate', false);
    postFacebookConversationInboxWebhook($payload)->assertSuccessful()->assertJsonPath('duplicate', true);

    expect(Conversation::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(1)
        ->and(FacebookWebhookEvent::query()->count())->toBe(1);
});

it('marks unsupported facebook payloads as ignored without surfacing them into the inbox', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-FACEBOOK-IGNORED',
        'active' => true,
    ]);
    configureFacebookConversationInboxRuntime($branch);

    $payload = [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'facebook-page-001',
                'time' => 1_735_689_600_000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'facebook_user_sticker'],
                        'recipient' => ['id' => 'facebook-page-001'],
                        'timestamp' => 1_735_689_600_000,
                        'message' => [
                            'mid' => 'mid.facebook.sticker.001',
                            'attachments' => [
                                ['type' => 'image'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    postFacebookConversationInboxWebhook($payload)->assertSuccessful();

    $event = FacebookWebhookEvent::query()->firstOrFail();

    expect(Conversation::query()->count())->toBe(0)
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and($event->normalize_status)->toBe('ignored')
        ->and($event->error_message)->toContain('Chỉ hỗ trợ inbound text message Facebook Messenger');
});

it('keeps routing later inbound facebook messages into the same bound lead conversation', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-FACEBOOK-LEAD',
        'active' => true,
    ]);
    configureFacebookConversationInboxRuntime($branch);

    $firstPayload = [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'facebook-page-001',
                'time' => 1_735_689_600_000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'facebook_user_lead'],
                        'recipient' => ['id' => 'facebook-page-001'],
                        'timestamp' => 1_735_689_600_000,
                        'message' => [
                            'mid' => 'mid.facebook.lead.001',
                            'text' => 'Cho minh xin thong tin nieng rang',
                        ],
                    ],
                ],
            ],
        ],
    ];

    postFacebookConversationInboxWebhook($firstPayload)->assertSuccessful();

    $conversation = Conversation::query()->firstOrFail();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $customer = app(ConversationLeadBindingService::class)->createLead(
        $conversation,
        [
            'full_name' => 'Tran Thi Facebook',
            'branch_id' => $branch->id,
        ],
        $manager,
    );

    $secondPayload = [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'facebook-page-001',
                'time' => 1_735_689_660_000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'facebook_user_lead'],
                        'recipient' => ['id' => 'facebook-page-001'],
                        'timestamp' => 1_735_689_660_000,
                        'message' => [
                            'mid' => 'mid.facebook.lead.002',
                            'text' => 'Minh co the den thu bay khong',
                        ],
                    ],
                ],
            ],
        ],
    ];

    postFacebookConversationInboxWebhook($secondPayload)->assertSuccessful();

    expect(Conversation::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(2)
        ->and($conversation->fresh()?->customer_id)->toBe($customer->id)
        ->and($customer->source)->toBe('facebook')
        ->and($customer->source_detail)->toBe('facebook_page_inbox');
});
