<?php

use App\Filament\Pages\ConversationInbox;
use App\Jobs\SendConversationMessage;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('allows admin manager and cskh personas to access the conversation inbox page', function (string $role): void {
    $branch = Branch::factory()->create();

    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(ConversationInbox::getUrl())
        ->assertOk()
        ->assertSee('Inbox hội thoại đa kênh')
        ->assertSee('Queue hội thoại');
})->with([
    'admin' => 'Admin',
    'manager' => 'Manager',
    'cskh' => 'CSKH',
]);

it('blocks doctor persona from accessing the conversation inbox page', function (): void {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor)
        ->get(ConversationInbox::getUrl())
        ->assertForbidden();
});

it('scopes conversation inbox rows to the authenticated users accessible branches', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $cskh->assignRole('CSKH');

    Conversation::factory()->create([
        'branch_id' => $branchA->id,
        'external_display_name' => 'Khach scope A',
        'latest_message_preview' => 'Tin scope A',
    ]);

    Conversation::factory()->create([
        'branch_id' => $branchB->id,
        'external_display_name' => 'Khach scope B',
        'latest_message_preview' => 'Tin scope B',
    ]);

    $this->actingAs($cskh)
        ->get(ConversationInbox::getUrl())
        ->assertOk()
        ->assertSee('Khach scope A')
        ->assertDontSee('Khach scope B');
});

it('stores structured handoff context on the selected conversation for team visibility', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $conversation = Conversation::factory()->create([
        'branch_id' => $branch->id,
        'external_display_name' => 'Khach handoff note',
        'handoff_priority' => Conversation::PRIORITY_NORMAL,
        'handoff_status' => Conversation::HANDOFF_STATUS_NEW,
        'handoff_summary' => null,
    ]);

    $this->actingAs($cskh);

    $nextActionAt = now()->addDay()->setTime(17, 0)->seconds(0);

    Livewire::test(ConversationInbox::class)
        ->call('selectConversation', $conversation->id)
        ->set('handoffForm.priority', Conversation::PRIORITY_URGENT)
        ->set('handoffForm.status', Conversation::HANDOFF_STATUS_QUOTED)
        ->set('handoffForm.next_action_at', $nextActionAt->format('Y-m-d\TH:i'))
        ->set('handoffForm.summary', 'Khach xin bao gia gap, can goi lai truoc 17h.')
        ->call('saveHandoffNote')
        ->assertSet('handoffForm.priority', Conversation::PRIORITY_URGENT)
        ->assertSet('handoffForm.status', Conversation::HANDOFF_STATUS_QUOTED)
        ->assertSet('handoffForm.next_action_at', $nextActionAt->format('Y-m-d\TH:i'))
        ->assertSet('handoffForm.summary', 'Khach xin bao gia gap, can goi lai truoc 17h.');

    $freshConversation = $conversation->fresh();

    expect($freshConversation)->not->toBeNull()
        ->and($freshConversation?->handoff_priority)->toBe(Conversation::PRIORITY_URGENT)
        ->and($freshConversation?->handoff_status)->toBe(Conversation::HANDOFF_STATUS_QUOTED)
        ->and($freshConversation?->handoff_summary)->toBe('Khach xin bao gia gap, can goi lai truoc 17h.')
        ->and($freshConversation?->handoff_next_action_at?->format('Y-m-d H:i'))->toBe($nextActionAt->format('Y-m-d H:i'))
        ->and($freshConversation?->handoff_updated_by)->toBe($cskh->id)
        ->and($freshConversation?->handoff_updated_at)->not->toBeNull()
        ->and($freshConversation?->handoff_version)->toBe(1);
});

it('refreshes the handoff form instead of overwriting a newer teammate update', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $conversation = Conversation::factory()->create([
        'branch_id' => $branch->id,
        'external_display_name' => 'Khach stale handoff',
        'handoff_priority' => Conversation::PRIORITY_NORMAL,
        'handoff_status' => Conversation::HANDOFF_STATUS_NEW,
        'handoff_summary' => 'Ban dau',
        'handoff_version' => 0,
    ]);

    $this->actingAs($cskh);

    $component = Livewire::test(ConversationInbox::class)
        ->call('selectConversation', $conversation->id)
        ->set('handoffForm.priority', Conversation::PRIORITY_HIGH)
        ->set('handoffForm.status', Conversation::HANDOFF_STATUS_QUOTED)
        ->set('handoffForm.summary', 'Ban nhap nhap local');

    $conversation->forceFill([
        'handoff_priority' => Conversation::PRIORITY_LOW,
        'handoff_status' => Conversation::HANDOFF_STATUS_WAITING_CUSTOMER,
        'handoff_summary' => 'Da duoc dong nghiep cap nhat truoc',
        'handoff_updated_by' => $cskh->id,
        'handoff_updated_at' => now(),
        'handoff_version' => 1,
    ])->save();

    $component->call('saveHandoffNote')
        ->assertSet('handoffForm.priority', Conversation::PRIORITY_LOW)
        ->assertSet('handoffForm.status', Conversation::HANDOFF_STATUS_WAITING_CUSTOMER)
        ->assertSet('handoffForm.summary', 'Da duoc dong nghiep cap nhat truoc');

    $freshConversation = $conversation->fresh();

    expect($freshConversation)->not->toBeNull()
        ->and($freshConversation?->handoff_priority)->toBe(Conversation::PRIORITY_LOW)
        ->and($freshConversation?->handoff_status)->toBe(Conversation::HANDOFF_STATUS_WAITING_CUSTOMER)
        ->and($freshConversation?->handoff_summary)->toBe('Da duoc dong nghiep cap nhat truoc')
        ->and($freshConversation?->handoff_version)->toBe(1);
});

it('loads recent thread messages first and can reveal older history on demand', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $conversation = Conversation::factory()->create([
        'branch_id' => $branch->id,
        'external_display_name' => 'Khach lich su dai',
        'latest_message_preview' => 'Tin nhan 45',
    ]);

    foreach (range(1, 45) as $index) {
        ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => ConversationMessage::DIRECTION_INBOUND,
            'body' => sprintf('Tin nhan %02d', $index),
            'message_at' => now()->copy()->startOfMinute()->addSeconds($index),
        ]);
    }

    $this->actingAs($cskh);

    Livewire::test(ConversationInbox::class)
        ->call('selectConversation', $conversation->id)
        ->assertSee('Tin nhan 45')
        ->assertSee('Tin nhan 16')
        ->assertDontSee('Tin nhan 15')
        ->assertSee('Xem tin cũ hơn')
        ->call('loadOlderMessages')
        ->assertSee('Tin nhan 15')
        ->assertSee('Tin nhan 01');
});

it('filters the inbox list by search term provider and queue tab for faster cskh triage', function (): void {
    $branch = Branch::factory()->create();

    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    Conversation::factory()->create([
        'branch_id' => $branch->id,
        'external_display_name' => 'Khach Bao Gia Gap',
        'provider' => Conversation::PROVIDER_ZALO,
        'latest_message_preview' => 'Can bao gia gap trong hom nay',
        'handoff_priority' => Conversation::PRIORITY_URGENT,
        'customer_id' => null,
        'assigned_to' => null,
        'unread_count' => 2,
    ]);

    Conversation::factory()->facebook()->create([
        'branch_id' => $branch->id,
        'external_display_name' => 'Khach Facebook Thuong',
        'latest_message_preview' => 'Tin nhan Facebook',
        'handoff_priority' => Conversation::PRIORITY_NORMAL,
        'assigned_to' => $cskh->id,
        'customer_id' => Customer::factory()->create()->id,
        'unread_count' => 0,
    ]);

    Conversation::factory()->create([
        'branch_id' => $branch->id,
        'external_display_name' => 'Khach Theo Doi',
        'latest_message_preview' => 'Theo doi sau',
        'handoff_priority' => Conversation::PRIORITY_LOW,
        'handoff_status' => Conversation::HANDOFF_STATUS_FOLLOW_UP,
        'handoff_next_action_at' => now()->subMinutes(30),
        'assigned_to' => null,
        'customer_id' => null,
        'unread_count' => 0,
    ]);

    $this->actingAs($cskh);

    Livewire::test(ConversationInbox::class)
        ->set('search', 'Bao Gia')
        ->assertSee('Khach Bao Gia Gap')
        ->assertDontSee('Khach Facebook Thuong')
        ->set('search', '')
        ->set('providerFilter', Conversation::PROVIDER_FACEBOOK)
        ->assertSee('Khach Facebook Thuong')
        ->assertDontSee('Khach Bao Gia Gap')
        ->set('providerFilter', 'all')
        ->set('inboxTab', 'priority')
        ->assertSee('Khach Bao Gia Gap')
        ->assertDontSee('Khach Theo Doi')
        ->set('inboxTab', 'due')
        ->assertSee('Khach Theo Doi')
        ->assertDontSee('Khach Bao Gia Gap')
        ->set('inboxTab', 'mine')
        ->assertSee('Khach Facebook Thuong')
        ->assertDontSee('Khach Bao Gia Gap');
});

it('allows cskh to claim reassign and release a conversation from the thread header', function (): void {
    $branch = Branch::factory()->create();

    $cskhA = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskhA->assignRole('CSKH');

    $cskhB = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskhB->assignRole('CSKH');

    $conversation = Conversation::factory()->create([
        'branch_id' => $branch->id,
        'assigned_to' => null,
        'external_display_name' => 'Khach claim thread',
    ]);

    $this->actingAs($cskhA);

    $component = Livewire::test(ConversationInbox::class)
        ->call('selectConversation', $conversation->id)
        ->call('claimConversation')
        ->assertSet('assignmentForm.assigned_to', (string) $cskhA->id);

    expect($conversation->fresh()?->assigned_to)->toBe($cskhA->id);

    $component
        ->set('assignmentForm.assigned_to', (string) $cskhB->id)
        ->call('saveConversationAssignee')
        ->assertSet('assignmentForm.assigned_to', (string) $cskhB->id);

    expect($conversation->fresh()?->assigned_to)->toBe($cskhB->id);

    $component
        ->call('releaseConversation')
        ->assertSet('assignmentForm.assigned_to', '');

    expect($conversation->fresh()?->assigned_to)->toBeNull();
});

it('creates a pending outbound message and auto claims the conversation when staff replies from the inbox', function (): void {
    Queue::fake();

    $branch = Branch::factory()->create();
    $cskh = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $cskh->assignRole('CSKH');

    $conversation = Conversation::factory()->create([
        'branch_id' => $branch->id,
        'assigned_to' => null,
        'unread_count' => 2,
        'external_display_name' => 'Khach pending reply',
    ]);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => ConversationMessage::DIRECTION_INBOUND,
        'body' => 'Khach dang cho phan hoi',
    ]);

    $this->actingAs($cskh);

    Livewire::test(ConversationInbox::class)
        ->call('selectConversation', $conversation->id)
        ->set('draftReply', 'Em da nhan duoc thong tin cua anh chi')
        ->call('sendReply')
        ->assertSet('draftReply', '');

    $freshConversation = $conversation->fresh();
    $outboundMessage = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', ConversationMessage::DIRECTION_OUTBOUND)
        ->latest('id')
        ->first();

    expect($freshConversation)->not->toBeNull()
        ->and($freshConversation?->assigned_to)->toBe($cskh->id)
        ->and($freshConversation?->unread_count)->toBe(0)
        ->and($freshConversation?->latest_message_preview)->toContain('Em da nhan')
        ->and($outboundMessage)->not->toBeNull()
        ->and($outboundMessage?->status)->toBe(ConversationMessage::STATUS_PENDING);

    Queue::assertPushed(SendConversationMessage::class, 1);
});

it('prefills and creates a lead from the selected conversation then binds it back to the conversation', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $admin->assignRole('Admin');

    $conversation = Conversation::factory()->create([
        'branch_id' => $branchA->id,
        'assigned_to' => $admin->id,
        'external_display_name' => 'Le Thi Inbox',
        'customer_id' => null,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(ConversationInbox::class)
        ->call('selectConversation', $conversation->id)
        ->call('openLeadForm')
        ->assertSet('showLeadModal', true)
        ->assertSet('leadForm.full_name', 'Le Thi Inbox')
        ->assertSet('leadForm.branch_id', $branchA->id)
        ->assertSet('leadForm.assigned_to', $admin->id)
        ->set('leadForm.phone', '0901112233')
        ->set('leadForm.branch_id', $branchB->id)
        ->set('leadForm.notes', 'Lead tu conversation inbox')
        ->call('createLead')
        ->assertSet('showLeadModal', false);

    $customer = Customer::query()->firstOrFail();

    expect($customer->full_name)->toBe('Le Thi Inbox')
        ->and($customer->source)->toBe('zalo')
        ->and($customer->source_detail)->toBe('zalo_oa_inbox')
        ->and($customer->branch_id)->toBe($branchB->id)
        ->and($conversation->fresh()?->customer_id)->toBe($customer->id)
        ->and($conversation->fresh()?->branch_id)->toBe($branchB->id);
});
