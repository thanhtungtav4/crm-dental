<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\User;
use App\Models\WebLeadIngestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('rejects web lead ingestion when token is invalid', function (): void {
    configureWebLeadApi(enabled: true, token: 'valid-token');

    $response = $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Nguyen Van A',
        'phone' => '0901234567',
    ], [
        'Authorization' => 'Bearer invalid-token',
        'X-Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Token không hợp lệ.');
});

it('creates a new lead from web payload and stores ingestion log', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-WEB-HCM',
        'active' => true,
    ]);

    configureWebLeadApi(
        enabled: true,
        token: 'web-token',
        defaultBranchCode: $branch->code,
        rateLimit: 120,
    );

    $requestId = (string) Str::uuid();

    $response = $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Tran Thi B',
        'phone' => '0901234567',
        'branch_code' => 'BR-WEB-HCM',
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => $requestId,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.created', true)
        ->assertJsonPath('data.replayed', false)
        ->assertJsonPath('data.phone', '0901234567');

    $customer = Customer::query()->firstOrFail();

    expect($customer->full_name)->toBe('Tran Thi B')
        ->and($customer->source)->toBe('other')
        ->and($customer->source_detail)->toBe('website')
        ->and($customer->phone_normalized)->toBe('0901234567')
        ->and((int) $customer->branch_id)->toBe($branch->id)
        ->and($customer->last_web_contact_at)->not->toBeNull();

    $ingestion = WebLeadIngestion::query()->firstOrFail();

    expect($ingestion->request_id)->toBe($requestId)
        ->and($ingestion->status)->toBe(WebLeadIngestion::STATUS_CREATED)
        ->and((int) $ingestion->customer_id)->toBe($customer->id);

    expect(AuditLog::query()
        ->where('entity_type', 'web_lead')
        ->where('entity_id', $ingestion->id)
        ->where('action', AuditLog::ACTION_CREATE)
        ->exists())->toBeTrue();
});

it('replays the same idempotency key without creating duplicate customers', function (): void {
    configureWebLeadApi(enabled: true, token: 'web-token');

    $requestId = (string) Str::uuid();
    $headers = [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => $requestId,
    ];

    $payload = [
        'full_name' => 'Le Van C',
        'phone' => '0912345678',
    ];

    $this->postJson('/api/v1/web-leads', $payload, $headers)
        ->assertCreated()
        ->assertJsonPath('data.created', true);

    $this->postJson('/api/v1/web-leads', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.created', true)
        ->assertJsonPath('data.replayed', true);

    expect(Customer::query()->count())->toBe(1)
        ->and(WebLeadIngestion::query()->count())->toBe(1);
});

it('merges lead by normalized phone when request id changes', function (): void {
    configureWebLeadApi(enabled: true, token: 'web-token');

    $existing = Customer::factory()->create([
        'full_name' => 'Existing Lead',
        'phone' => '0909 888 777',
        'phone_normalized' => '0909888777',
        'source' => 'facebook',
        'status' => 'lead',
    ]);

    $response = $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Incoming Name',
        'phone' => '+84 909 888 777',
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.created', false)
        ->assertJsonPath('data.replayed', false)
        ->assertJsonPath('data.customer_id', $existing->id);

    $existing->refresh();

    expect($existing->phone_normalized)->toBe('0909888777')
        ->and($existing->source_detail)->toBe('website')
        ->and($existing->last_web_contact_at)->not->toBeNull();

    expect(WebLeadIngestion::query()->where('status', WebLeadIngestion::STATUS_MERGED)->count())
        ->toBe(1);
});

it('uses default branch code when payload does not include branch_code', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-20260119-1DCADA',
        'active' => true,
    ]);

    configureWebLeadApi(
        enabled: true,
        token: 'web-token',
        defaultBranchCode: $branch->code,
        rateLimit: 120,
    );

    $response = $this->postJson('/api/v1/web-leads', [
        'full_name' => 'No Branch Payload',
        'phone' => '0934567890',
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.branch_id', $branch->id);

    $customer = Customer::query()->latest('id')->firstOrFail();

    expect((int) $customer->branch_id)->toBe($branch->id);
});

it('does not fallback to legacy default_branch_id when default_branch_code is empty', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-LEGACY-ID',
        'active' => true,
    ]);

    configureWebLeadApi(
        enabled: true,
        token: 'web-token',
        defaultBranchCode: '',
        rateLimit: 120,
    );

    ClinicSetting::setValue('web_lead.default_branch_id', $branch->id, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);

    $response = $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Legacy Fallback Disabled',
        'phone' => '0934567891',
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.branch_id', null);

    $customer = Customer::query()->latest('id')->firstOrFail();

    expect($customer->branch_id)->toBeNull();
});

it('sends realtime notifications to all users in configured roles', function (): void {
    $targetBranch = Branch::factory()->create([
        'code' => 'BR-WEB-NOTI-HCM',
        'active' => true,
    ]);
    $otherBranch = Branch::factory()->create([
        'code' => 'BR-WEB-NOTI-DN',
        'active' => true,
    ]);

    configureWebLeadApi(
        enabled: true,
        token: 'web-token',
        defaultBranchCode: $targetBranch->code,
        rateLimit: 120,
    );
    configureWebLeadRealtimeNotification(
        enabled: true,
        roles: ['CSKH'],
    );

    $sameBranchRecipient = User::factory()->create([
        'branch_id' => $targetBranch->id,
    ]);
    $sameBranchRecipient->assignRole('CSKH');

    $globalRecipient = User::factory()->create([
        'branch_id' => null,
    ]);
    $globalRecipient->assignRole('CSKH');

    $otherBranchRecipient = User::factory()->create([
        'branch_id' => $otherBranch->id,
    ]);
    $otherBranchRecipient->assignRole('CSKH');

    $nonTargetRoleRecipient = User::factory()->create([
        'branch_id' => $targetBranch->id,
    ]);
    $nonTargetRoleRecipient->assignRole('Doctor');

    $requestId = (string) Str::uuid();

    $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Realtime Receiver',
        'phone' => '0901234599',
        'branch_code' => $targetBranch->code,
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => $requestId,
    ])->assertCreated();

    $notifications = DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->get();

    expect($notifications->count())->toBe(3)
        ->and($notifications->contains(fn ($item) => (int) $item->notifiable_id === $sameBranchRecipient->id))->toBeTrue()
        ->and($notifications->contains(fn ($item) => (int) $item->notifiable_id === $globalRecipient->id))->toBeTrue()
        ->and($notifications->contains(fn ($item) => (int) $item->notifiable_id === $otherBranchRecipient->id))->toBeTrue()
        ->and($notifications->contains(fn ($item) => (int) $item->notifiable_id === $nonTargetRoleRecipient->id))->toBeFalse();

    $payload = json_decode((string) $notifications->firstWhere('notifiable_id', $sameBranchRecipient->id)->data, true);

    expect($payload)
        ->toBeArray()
        ->and((string) ($payload['title'] ?? ''))->toContain('Web Lead')
        ->and((string) ($payload['body'] ?? ''))->toContain($requestId);
});

it('does not dispatch duplicate realtime notifications when idempotency request is replayed', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-WEB-NOTI-REPLAY',
        'active' => true,
    ]);

    configureWebLeadApi(
        enabled: true,
        token: 'web-token',
        defaultBranchCode: $branch->code,
        rateLimit: 120,
    );
    configureWebLeadRealtimeNotification(
        enabled: true,
        roles: ['CSKH'],
    );

    $recipient = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $recipient->assignRole('CSKH');

    $requestId = (string) Str::uuid();
    $headers = [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => $requestId,
    ];
    $payload = [
        'full_name' => 'Replay Notification Check',
        'phone' => '0901111222',
        'branch_code' => $branch->code,
    ];

    $this->postJson('/api/v1/web-leads', $payload, $headers)
        ->assertCreated()
        ->assertJsonPath('data.replayed', false);

    $this->postJson('/api/v1/web-leads', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.replayed', true);

    expect(DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $recipient->id)
        ->count())->toBe(1);
});

it('stores realtime notification immediately even when queue default is database', function (): void {
    config()->set('queue.default', 'database');

    $branch = Branch::factory()->create([
        'code' => 'BR-WEB-NOTI-IMMEDIATE',
        'active' => true,
    ]);

    configureWebLeadApi(
        enabled: true,
        token: 'web-token',
        defaultBranchCode: $branch->code,
        rateLimit: 120,
    );
    configureWebLeadRealtimeNotification(
        enabled: true,
        roles: ['CSKH'],
    );

    $recipient = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $recipient->assignRole('CSKH');

    $requestId = (string) Str::uuid();

    $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Queue Database Notification',
        'phone' => '0903333444',
        'branch_code' => $branch->code,
    ], [
        'Authorization' => 'Bearer web-token',
        'X-Idempotency-Key' => $requestId,
    ])->assertCreated();

    expect(DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $recipient->id)
        ->count())->toBe(1);
});

function configureWebLeadApi(
    bool $enabled,
    string $token,
    ?string $defaultBranchCode = null,
    int $rateLimit = 60,
): void {
    ClinicSetting::setValue('web_lead.enabled', $enabled, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('web_lead.api_token', $token, [
        'group' => 'web_lead',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('web_lead.default_branch_code', $defaultBranchCode ?? '', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('web_lead.rate_limit_per_minute', $rateLimit, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);
}

/**
 * @param  array<int, string>  $roles
 */
function configureWebLeadRealtimeNotification(bool $enabled, array $roles): void
{
    ClinicSetting::setValue('web_lead.realtime_notification_enabled', $enabled, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('web_lead.realtime_notification_roles', $roles, [
        'group' => 'web_lead',
        'value_type' => 'json',
    ]);
}
