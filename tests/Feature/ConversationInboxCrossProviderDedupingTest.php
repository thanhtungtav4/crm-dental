<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;

function configureCrossProviderConversationRuntime(Branch $branch): void
{
    $settings = [
        'zalo.enabled' => true,
        'zalo.oa_id' => 'oa-cross-provider-001',
        'zalo.app_secret' => 'zalo-cross-provider-secret-001',
        'zalo.webhook_token' => 'zalo-cross-provider-token-12345678901234567890',
        'zalo.inbox_default_branch_code' => $branch->code,
        'facebook.enabled' => true,
        'facebook.app_secret' => 'facebook-cross-provider-secret-001',
        'facebook.webhook_verify_token' => 'facebook-cross-provider-token-123456789012345',
        'facebook.inbox_default_branch_code' => $branch->code,
    ];

    foreach ($settings as $key => $value) {
        ClinicSetting::setValue($key, $value, [
            'group' => str_starts_with($key, 'facebook.') ? 'facebook' : 'zalo',
            'value_type' => str_ends_with($key, '.enabled') ? 'boolean' : 'text',
            'is_secret' => in_array($key, [
                'zalo.app_secret',
                'zalo.webhook_token',
                'facebook.app_secret',
                'facebook.webhook_verify_token',
            ], true),
        ]);
    }
}

/**
 * @param  array<string, mixed>  $payload
 */
function signCrossProviderZaloPayload(array $payload): string
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

    return hash_hmac('sha256', is_string($payloadJson) ? $payloadJson : '{}', 'zalo-cross-provider-secret-001');
}

/**
 * @param  array<string, mixed>  $payload
 */
function signCrossProviderFacebookPayload(array $payload): string
{
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return 'sha256='.hash_hmac('sha256', is_string($payloadJson) ? $payloadJson : '{}', 'facebook-cross-provider-secret-001');
}

it('allows zalo and facebook conversations to normalize the same external provider message id independently', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-CROSS-PROVIDER',
        'active' => true,
    ]);

    configureCrossProviderConversationRuntime($branch);

    $sharedMessageId = 'shared-provider-message-001';

    $this->withHeaders([
        'X-Zalo-Signature' => signCrossProviderZaloPayload([
            'event_id' => 'evt_cross_provider_zalo',
            'event_name' => 'user_send_text',
            'oa_id' => 'oa-cross-provider-001',
            'timestamp' => 1_735_689_600,
            'sender' => [
                'id' => 'zalo-cross-provider-user',
                'name' => 'Khach Zalo Shared',
            ],
            'message' => [
                'msg_id' => $sharedMessageId,
                'text' => 'Tin nhan Zalo shared id',
            ],
        ]),
    ])->postJson('/api/v1/integrations/zalo/webhook', [
        'event_id' => 'evt_cross_provider_zalo',
        'event_name' => 'user_send_text',
        'oa_id' => 'oa-cross-provider-001',
        'timestamp' => 1_735_689_600,
        'sender' => [
            'id' => 'zalo-cross-provider-user',
            'name' => 'Khach Zalo Shared',
        ],
        'message' => [
            'msg_id' => $sharedMessageId,
            'text' => 'Tin nhan Zalo shared id',
        ],
    ])->assertSuccessful();

    $this->withHeaders([
        'X-Hub-Signature-256' => signCrossProviderFacebookPayload([
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'facebook-page-cross-provider',
                    'time' => 1_735_689_610_000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'facebook-cross-provider-user'],
                            'recipient' => ['id' => 'facebook-page-cross-provider'],
                            'timestamp' => 1_735_689_610_000,
                            'message' => [
                                'mid' => $sharedMessageId,
                                'text' => 'Tin nhan Facebook shared id',
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ])->postJson('/api/v1/integrations/facebook/webhook', [
        'object' => 'page',
        'entry' => [
            [
                'id' => 'facebook-page-cross-provider',
                'time' => 1_735_689_610_000,
                'messaging' => [
                    [
                        'sender' => ['id' => 'facebook-cross-provider-user'],
                        'recipient' => ['id' => 'facebook-page-cross-provider'],
                        'timestamp' => 1_735_689_610_000,
                        'message' => [
                            'mid' => $sharedMessageId,
                            'text' => 'Tin nhan Facebook shared id',
                        ],
                    ],
                ],
            ],
        ],
    ])->assertSuccessful();

    expect(Conversation::query()->count())->toBe(2)
        ->and(ConversationMessage::query()->count())->toBe(2)
        ->and(ConversationMessage::query()->where('provider_message_id', $sharedMessageId)->count())->toBe(2)
        ->and(ConversationMessage::query()->where('body', 'Tin nhan Zalo shared id')->exists())->toBeTrue()
        ->and(ConversationMessage::query()->where('body', 'Tin nhan Facebook shared id')->exists())->toBeTrue();
});
