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
        ->assertSee('Inbox hội thoại Zalo')
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
