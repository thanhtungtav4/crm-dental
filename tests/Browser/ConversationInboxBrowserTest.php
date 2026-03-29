<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Support\Facades\Http;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

use function Pest\Laravel\seed;

function configureConversationInboxBrowserRuntime(Branch $branch): void
{
    $settings = [
        'zalo.enabled' => true,
        'zalo.oa_id' => 'oa-browser-001',
        'zalo.app_id' => 'app-browser-001',
        'zalo.app_secret' => 'browser_app_secret_001',
        'zalo.access_token' => 'browser-access-token-001',
        'zalo.webhook_token' => 'secure-browser-token-12345678901234567890',
        'zalo.send_endpoint' => 'https://openapi.zalo.me/v3.0/oa/message/cs',
        'zalo.inbox_default_branch_code' => $branch->code,
        'zalo.inbox_polling_seconds' => 1,
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
function signConversationInboxBrowserPayload(array $payload): string
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

    return hash_hmac('sha256', is_string($payloadJson) ? $payloadJson : '{}', 'browser_app_secret_001');
}

/**
 * @param  array<string, mixed>  $payload
 */
function postConversationInboxBrowserWebhook(array $payload): void
{
    test()->withHeaders([
        'X-Zalo-Signature' => signConversationInboxBrowserPayload($payload),
    ])->postJson('/api/v1/integrations/zalo/webhook', $payload)->assertSuccessful();
}

it('shows inbound zalo messages in the inbox, lets cskh reply, create a lead, and keeps later inbound flowing into the same thread', function (): void {
    seed(LocalDemoDataSeeder::class);

    Http::preventStrayRequests();
    Http::fake([
        'https://openapi.zalo.me/v3.0/oa/message/cs' => Http::response([
            'error' => 0,
            'data' => [
                'message_id' => 'browser-provider-message-001',
            ],
        ], 200),
    ]);

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $branch = $cskh->branch()->firstOrFail();

    configureConversationInboxBrowserRuntime($branch);

    postConversationInboxBrowserWebhook([
        'event_id' => 'evt_browser_001',
        'event_name' => 'user_send_text',
        'oa_id' => 'oa-browser-001',
        'timestamp' => 1_735_689_600,
        'sender' => [
            'id' => 'browser-user-001',
            'name' => 'Browser Inbox Lead',
        ],
        'message' => [
            'msg_id' => 'msg_browser_001',
            'text' => 'Xin chao tu browser test',
        ],
    ]);

    $page = loginToConversationInbox('cskh.q1@demo.ident.test');

    $page->resize(1440, 1100)
        ->navigate('/admin/conversation-inbox')
        ->assertSee('Inbox hội thoại Zalo')
        ->assertSee('Browser Inbox Lead')
        ->assertSee('Xin chao tu browser test')
        ->fill('[data-testid="reply-composer"]', 'CRM da nhan duoc yeu cau cua ban')
        ->click('Gửi phản hồi')
        ->assertSee('CRM da nhan duoc yeu cau cua ban')
        ->click('Tạo lead')
        ->assertSee('Tạo lead từ hội thoại')
        ->fill('[data-testid="lead-full-name"]', 'Browser Inbox Lead')
        ->fill('[data-testid="lead-phone"]', '0901888999');

    $page->script("document.querySelector('[data-testid=\"lead-save\"]')?.scrollIntoView({ block: 'center' })");
    $page->script("document.querySelector('[data-testid=\"lead-save\"]')?.click()");

    $page->wait(1)
        ->assertSee('Mở lead');

    postConversationInboxBrowserWebhook([
        'event_id' => 'evt_browser_002',
        'event_name' => 'user_send_text',
        'oa_id' => 'oa-browser-001',
        'timestamp' => 1_735_689_660,
        'sender' => [
            'id' => 'browser-user-001',
            'name' => 'Browser Inbox Lead',
        ],
        'message' => [
            'msg_id' => 'msg_browser_002',
            'text' => 'Tin nhan follow-up sau khi tao lead',
        ],
    ]);

    $page->wait(2)
        ->assertSee('Tin nhan follow-up sau khi tao lead')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

function loginToConversationInbox(string $email, ?string $recoveryCode = null): PendingAwaitablePage|AwaitableWebpage
{
    $page = visit('/admin/login');

    $page->fill('input[type="email"]', $email)
        ->fill('input[type="password"]', LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD)
        ->click('button[type="submit"]')
        ->wait(0.5);

    if ($recoveryCode !== null && str_contains($page->url(), '/two-factor-authentication')) {
        $page->click('Sử dụng mã khôi phục')
            ->fill('input[placeholder="abcdef-98765"]', $recoveryCode)
            ->click('button[type="submit"]')
            ->wait(0.5);
    }

    return $page;
}
