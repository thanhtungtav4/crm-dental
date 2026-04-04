<?php

use App\Filament\Pages\ConversationInbox;
use App\Jobs\SendConversationMessage;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\File;
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

it('renders the conversation inbox shell from a shared inbox view state', function (): void {
    $blade = File::get(resource_path('views/filament/pages/conversation-inbox.blade.php'));
    $inboxStatCardPartial = File::get(resource_path('views/filament/pages/partials/conversation-inbox-stat-card.blade.php'));
    $conversationQueuePanelPartial = File::get(resource_path('views/filament/pages/partials/conversation-queue-panel.blade.php'));
    $conversationSummaryCardPartial = File::get(resource_path('views/filament/pages/partials/conversation-summary-card.blade.php'));
    $conversationSelectedHeaderPartial = File::get(resource_path('views/filament/pages/partials/conversation-selected-header.blade.php'));
    $conversationHandoffFormPartial = File::get(resource_path('views/filament/pages/partials/conversation-handoff-form.blade.php'));
    $conversationThreadPanelPartial = File::get(resource_path('views/filament/pages/partials/conversation-thread-panel.blade.php'));
    $conversationListItemPartial = File::get(resource_path('views/filament/pages/partials/conversation-list-item.blade.php'));
    $conversationLeadModalPartial = File::get(resource_path('views/filament/pages/partials/conversation-lead-modal.blade.php'));
    $pageShellPartial = File::get(resource_path('views/filament/pages/partials/conversation-inbox-page-shell.blade.php'));
    $schemaNoticePartial = File::get(resource_path('views/filament/pages/partials/conversation-inbox-schema-notice.blade.php'));
    $readyShellPartial = File::get(resource_path('views/filament/pages/partials/conversation-inbox-ready-shell.blade.php'));
    $detailPanelPartial = File::get(resource_path('views/filament/pages/partials/conversation-detail-panel.blade.php'));

    expect($blade)
        ->not->toContain('@php')
        ->toContain("@include('filament.pages.partials.conversation-inbox-page-shell', [")
        ->toContain("'viewState' => \$this->inboxViewState,")
        ->toContain("'pagePanel' => \$this->inboxViewState['page_panel'],")
        ->toContain("'showLeadModal' => \$showLeadModal,")
        ->not->toContain('match ($conversation->handoffPriorityValue())')
        ->not->toContain('match ($selectedConversation->handoffStatusValue())')
        ->not->toContain('$isActiveTab = $this->inboxTab === $tabValue');

    expect($pageShellPartial)
        ->not->toContain('@php')
        ->toContain('@props([')
        ->toContain("@if (! \$viewState['is_schema_ready'])")
        ->toContain("@include('filament.pages.partials.conversation-inbox-schema-notice', [")
        ->toContain("'schemaNotice' => \$viewState['schema_notice']")
        ->toContain("@include('filament.pages.partials.conversation-inbox-ready-shell', [")
        ->toContain("'viewState' => \$viewState,")
        ->toContain("'pagePanel' => \$pagePanel,")
        ->toContain("@if(\$showLeadModal && \$pagePanel['detail_panel']['conversation'])")
        ->toContain("@include('filament.pages.partials.conversation-lead-modal', [")
        ->toContain("'leadModalView' => \$pagePanel['lead_modal_view']");

    expect($schemaNoticePartial)
        ->toContain(":heading=\"\$schemaNotice['heading']\"")
        ->toContain(":description=\"\$schemaNotice['description']\"")
        ->toContain("{{ \$schemaNotice['message'] }}");

    expect($readyShellPartial)
        ->toContain("wire:poll.{{ \$viewState['polling_interval_seconds'] }}s=\"refreshInbox\"")
        ->toContain("@include('filament.pages.partials.conversation-queue-panel', [")
        ->toContain("'queuePanel' => \$pagePanel['queue_panel']")
        ->toContain("@include('filament.pages.partials.conversation-detail-panel', [")
        ->toContain("'detailPanel' => \$pagePanel['detail_panel']");

    expect($detailPanelPartial)
        ->toContain(":heading=\"\$detailPanel['heading']\"")
        ->toContain(":description=\"\$detailPanel['description']\"")
        ->toContain("@if(\$detailPanel['conversation'])")
        ->toContain("@include('filament.pages.partials.conversation-selected-header', [")
        ->toContain("'summaryCards' => \$detailPanel['selected_conversation_view']['summary_cards']")
        ->toContain("@include('filament.pages.partials.conversation-handoff-form', [")
        ->toContain("'handoffPanel' => \$detailPanel['selected_conversation_view']['handoff_panel']")
        ->toContain("@include('filament.pages.partials.conversation-thread-panel', [")
        ->toContain("'threadPanel' => \$detailPanel['selected_conversation_view']['thread_panel']")
        ->toContain("{{ \$detailPanel['empty_state_text'] }}");

    expect($inboxStatCardPartial)
        ->toContain("{{ \$card['label'] }}")
        ->toContain("{{ \$card['count'] }}")
        ->toContain("{{ \$card['description'] }}");

    expect($conversationQueuePanelPartial)
        ->toContain('@props([')
        ->toContain("{{ \$queuePanel['search_label'] }}")
        ->toContain("{{ \$queuePanel['search_placeholder'] }}")
        ->toContain("@foreach(\$queuePanel['inbox_stat_cards'] as \$card)")
        ->toContain("@foreach(\$queuePanel['rendered_inbox_tabs'] as \$tab)")
        ->toContain("@foreach(\$queuePanel['provider_filter_options'] as \$providerValue => \$providerLabel)")
        ->toContain("@forelse(\$queuePanel['conversation_rows'] as \$conversationRow)")
        ->toContain("{{ \$queuePanel['results_text'] }}")
        ->toContain("{{ \$queuePanel['empty_state_text'] }}");

    expect($conversationSummaryCardPartial)
        ->toContain("{{ \$card['label'] }}")
        ->toContain("{{ \$card['value'] }}");

    expect($conversationSelectedHeaderPartial)
        ->toContain('@props([')
        ->toContain('$conversation->providerBadgeClasses()')
        ->toContain('$conversation->handoffStatusBadgeClasses()')
        ->toContain('$conversation->handoffPriorityBadgeClasses()')
        ->toContain('@foreach($summaryCards as $card)')
        ->toContain("@include('filament.pages.partials.conversation-summary-card', ['card' => \$card])");

    expect($conversationHandoffFormPartial)
        ->toContain('@props([')
        ->toContain("{{ \$handoffPanel['summary_heading'] }}")
        ->toContain("{{ \$handoffPanel['summary_description'] }}")
        ->toContain("@foreach(\$handoffPanel['status_options'] as \$statusValue => \$statusLabel)")
        ->toContain("@foreach(\$handoffPanel['priority_options'] as \$priorityValue => \$priorityLabel)");

    expect($conversationThreadPanelPartial)
        ->toContain('@props([')
        ->toContain('@forelse($messages as $message)')
        ->toContain("{{ \$threadPanel['description'] }}")
        ->toContain("{{ \$composerPanel['submit_label'] }}");

    expect($conversationListItemPartial)
        ->toContain("wire:key=\"conversation-{{ \$conversationRow['id'] }}\"")
        ->toContain("{{ \$conversationRow['provider_label'] }}")
        ->toContain("{{ \$conversationRow['handoff_status_label'] }}")
        ->toContain("{{ \$conversationRow['handoff_priority_label'] }}")
        ->toContain("{{ \$conversationRow['preview'] }}");

    expect($conversationLeadModalPartial)
        ->toContain("{{ \$leadModalView['heading'] }}")
        ->toContain("{{ \$leadModalView['description'] }}")
        ->toContain("@foreach(\$leadModalView['branch_options'] as \$branchId => \$branchLabel)")
        ->toContain("@foreach(\$leadModalView['assignee_options'] as \$staffId => \$staffLabel)")
        ->toContain("@foreach(\$leadModalView['summary_cards'] as \$card)")
        ->toContain("{{ \$leadModalView['submit_label'] }}");
});

it('formats conversation handoff badge classes through the model presentation helpers', function (): void {
    $conversation = new Conversation([
        'handoff_priority' => Conversation::PRIORITY_URGENT,
        'handoff_status' => Conversation::HANDOFF_STATUS_WAITING_CUSTOMER,
    ]);

    expect($conversation->handoffPriorityBadgeClasses())->toContain('border-danger-200')
        ->and($conversation->handoffStatusBadgeClasses())->toContain('border-warning-200');
});

it('formats conversation provider badge classes through the model presentation helper', function (): void {
    $zaloConversation = new Conversation([
        'provider' => Conversation::PROVIDER_ZALO,
    ]);

    $facebookConversation = new Conversation([
        'provider' => Conversation::PROVIDER_FACEBOOK,
    ]);

    expect($zaloConversation->providerBadgeClasses())->toContain('border-sky-200')
        ->and($facebookConversation->providerBadgeClasses())->toContain('border-indigo-200');
});

it('exposes inbox summary cards and provider filter options through the shared view state', function (): void {
    $branch = Branch::factory()->create();

    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $user->assignRole('CSKH');

    $page = new ConversationInbox;
    $page->search = '';
    $page->providerFilter = 'all';
    $page->inboxTab = 'all';
    $page->visibleMessageLimit = 30;

    test()->actingAs($user);

    $viewState = $page->inboxViewState();
    $pagePanel = $viewState['page_panel'];

    expect($pagePanel['queue_panel']['provider_filter_options'])
        ->toBe([
            'all' => 'Tất cả',
            'zalo' => 'Zalo OA',
            'facebook' => 'Facebook Messenger',
        ])
        ->and($viewState['schema_notice'])->toMatchArray([
            'heading' => 'Inbox hội thoại chưa sẵn sàng',
            'description' => 'Trang này sẽ tự hoạt động lại sau khi schema hội thoại được cài đặt đầy đủ.',
        ])
        ->and($pagePanel['detail_panel'])->toMatchArray([
            'heading' => 'Chi tiết hội thoại',
            'description' => 'Phản hồi trực tiếp, gắn lead vào đúng conversation và tiếp tục giữ luồng tin nhắn về sau.',
            'empty_state_text' => 'Chọn một hội thoại ở cột bên trái để xem thread chi tiết.',
        ])
        ->and($pagePanel['queue_panel']['inbox_stat_cards'])->toHaveCount(4)
        ->and(collect($pagePanel['queue_panel']['inbox_stat_cards'])->pluck('key')->all())
        ->toBe(['unread', 'due', 'unclaimed', 'unbound'])
        ->and(collect($pagePanel['queue_panel']['rendered_inbox_tabs'])->pluck('key')->all())
        ->toBe(['all', 'priority', 'due', 'unbound', 'mine'])
        ->and($pagePanel['queue_panel']['conversation_rows'])->toBeArray()
        ->and($pagePanel['queue_panel']['heading'])->toBe('Queue hội thoại')
        ->and($pagePanel['detail_panel']['selected_conversation_view']['summary_cards'])->toBe([])
        ->and($pagePanel['detail_panel']['selected_conversation_view']['messages'])->toBe([])
        ->and($pagePanel['detail_panel']['selected_conversation_view']['thread_panel']['show_load_older_messages'])->toBeFalse()
        ->and($pagePanel['detail_panel']['selected_conversation_view']['composer_panel']['submit_label'])->toBe('Gửi phản hồi')
        ->and($pagePanel['lead_modal_view']['heading'])->toBe('Tạo lead từ hội thoại')
        ->and($pagePanel['lead_modal_view']['summary_cards'])->toHaveCount(3);
});

it('builds selected conversation render state from the shared inbox view state', function (): void {
    $branch = Branch::factory()->create();

    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $user->assignRole('CSKH');

    $customer = Customer::factory()->create();

    $conversation = Conversation::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'assigned_to' => $user->id,
        'handoff_priority' => Conversation::PRIORITY_HIGH,
        'handoff_status' => Conversation::HANDOFF_STATUS_WAITING_CUSTOMER,
        'handoff_summary' => 'Khach muon duoc goi lai.',
        'handoff_updated_by' => $user->id,
        'handoff_updated_at' => now(),
    ]);

    ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => ConversationMessage::DIRECTION_INBOUND,
        'status' => ConversationMessage::STATUS_RECEIVED,
        'body' => 'Cho minh xin lich hen som nhat.',
        'message_at' => now()->subMinutes(5),
    ]);

    test()->actingAs($user);

    $page = new ConversationInbox;
    $page->selectedConversationId = $conversation->id;
    $page->visibleMessageLimit = 30;

    $viewState = $page->inboxViewState();
    $pagePanel = $viewState['page_panel'];
    $selectedConversationView = $pagePanel['detail_panel']['selected_conversation_view'];

    expect($selectedConversationView['summary_cards'])->toHaveCount(3)
        ->and($selectedConversationView['customer_edit_url'])->toBeString()
        ->and($selectedConversationView['handoff_panel']['updated_by_name'])->toBe($user->name)
        ->and($selectedConversationView['messages'])->toHaveCount(1)
        ->and($selectedConversationView['messages'][0]['sender_label'])->toBe('Khách')
        ->and($selectedConversationView['messages'][0]['status_label'])->toBe('Đã nhận')
        ->and($selectedConversationView['thread_panel']['heading'])->toBe('Thread hội thoại')
        ->and($selectedConversationView['composer_panel']['label'])->toBe('Phản hồi từ CRM')
        ->and($selectedConversationView['assignee_options'])->toBeArray()
        ->and($pagePanel['queue_panel']['conversation_rows'])->toHaveCount(1)
        ->and($pagePanel['queue_panel']['conversation_rows'][0]['display_name'])->toBe($conversation->displayName())
        ->and($pagePanel['queue_panel']['conversation_rows'][0]['provider_label'])->toBe($conversation->providerLabel())
        ->and($pagePanel['queue_panel']['results_text'])->toContain('1 hội thoại');
});

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
