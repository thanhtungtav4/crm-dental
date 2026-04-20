<?php

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Services\ConversationInboxReadModelService;

function makeConversation(array $overrides = []): Conversation
{
    $branch = $overrides['branch_id'] ?? Branch::factory()->create()->id;

    return Conversation::factory()->create(array_merge([
        'branch_id' => $branch,
        'status' => Conversation::STATUS_OPEN,
        'unread_count' => 0,
        'handoff_priority' => Conversation::PRIORITY_NORMAL,
        'assigned_to' => null,
        'customer_id' => null,
        'last_message_at' => now(),
    ], $overrides));
}

describe('ConversationInboxReadModelService::visibleConversationQuery()', function (): void {

    it('returns nothing when actor is null', function (): void {
        makeConversation();

        $result = app(ConversationInboxReadModelService::class)->list(null, 50);

        expect($result)->toBeEmpty();
    });

    it('admin sees all conversations across branches', function (): void {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch1->id]);
        $admin->assignRole('Admin');

        makeConversation(['branch_id' => $branch1->id]);
        makeConversation(['branch_id' => $branch2->id]);

        $result = app(ConversationInboxReadModelService::class)->list($admin, 50);

        expect($result->count())->toBeGreaterThanOrEqual(2);
    });

    it('non-admin only sees conversations in own branch', function (): void {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $manager = User::factory()->create(['branch_id' => $ownBranch->id]);
        $manager->assignRole('Manager');

        $visible = makeConversation(['branch_id' => $ownBranch->id]);
        makeConversation(['branch_id' => $otherBranch->id]);

        $result = app(ConversationInboxReadModelService::class)->list($manager, 50);

        expect($result->pluck('id')->contains($visible->id))->toBeTrue()
            ->and($result->count())->toBe(1);
    });
});

describe('ConversationInboxReadModelService::stats()', function (): void {

    it('counts unread, due, unclaimed, unbound correctly', function (): void {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('Admin');

        // unread
        makeConversation(['branch_id' => $branch->id, 'unread_count' => 3]);
        // due (handoff_next_action_at in past)
        makeConversation(['branch_id' => $branch->id, 'handoff_next_action_at' => now()->subHour()]);
        // unclaimed (no assigned_to)
        makeConversation(['branch_id' => $branch->id, 'assigned_to' => null]);
        // unbound (no customer_id)
        makeConversation(['branch_id' => $branch->id, 'customer_id' => null]);

        $stats = app(ConversationInboxReadModelService::class)->stats($admin, []);

        expect($stats)->toHaveKeys(['unread', 'due', 'unclaimed', 'unbound'])
            ->and($stats['unread'])->toBeGreaterThanOrEqual(1)
            ->and($stats['due'])->toBeGreaterThanOrEqual(1)
            ->and($stats['unclaimed'])->toBeGreaterThanOrEqual(1)
            ->and($stats['unbound'])->toBeGreaterThanOrEqual(1);
    });

    it('returns zeros when no conversations visible', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole('Manager');

        // conversations in other branch
        $otherBranch = Branch::factory()->create();
        makeConversation(['branch_id' => $otherBranch->id]);

        $stats = app(ConversationInboxReadModelService::class)->stats($manager, []);

        expect($stats['unread'])->toBe(0)
            ->and($stats['due'])->toBe(0)
            ->and($stats['unclaimed'])->toBe(0)
            ->and($stats['unbound'])->toBe(0);
    });
});

describe('ConversationInboxReadModelService filter: tab', function (): void {

    it('filters priority tab to high and urgent only', function (): void {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('Admin');

        $high = makeConversation(['branch_id' => $branch->id, 'handoff_priority' => Conversation::PRIORITY_HIGH]);
        $urgent = makeConversation(['branch_id' => $branch->id, 'handoff_priority' => Conversation::PRIORITY_URGENT]);
        makeConversation(['branch_id' => $branch->id, 'handoff_priority' => Conversation::PRIORITY_NORMAL]);

        $result = app(ConversationInboxReadModelService::class)->list($admin, 50, ['tab' => 'priority']);

        expect($result->pluck('id')->sort()->values()->all())
            ->toBe(collect([$high->id, $urgent->id])->sort()->values()->all());
    });

    it('filters mine tab to conversations assigned to actor', function (): void {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('Admin');
        $other = User::factory()->create(['branch_id' => $branch->id]);

        $mine = makeConversation(['branch_id' => $branch->id, 'assigned_to' => $admin->id]);
        makeConversation(['branch_id' => $branch->id, 'assigned_to' => $other->id]);

        $result = app(ConversationInboxReadModelService::class)->list($admin, 50, ['tab' => 'mine']);

        expect($result->pluck('id')->all())->toBe([$mine->id]);
    });

    it('filters unbound tab to conversations without customer', function (): void {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('Admin');
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        $unbound = makeConversation(['branch_id' => $branch->id, 'customer_id' => null]);
        makeConversation(['branch_id' => $branch->id, 'customer_id' => $customer->id]);

        $result = app(ConversationInboxReadModelService::class)->list($admin, 50, ['tab' => 'unbound']);

        expect($result->pluck('id')->all())->toBe([$unbound->id]);
    });
});

describe('ConversationInboxReadModelService::resolveInitialConversationId()', function (): void {

    it('returns null when no visible conversations', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole('Manager');

        $id = app(ConversationInboxReadModelService::class)->resolveInitialConversationId($manager, []);

        expect($id)->toBeNull();
    });

    it('returns an integer id when visible conversations exist', function (): void {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('Admin');

        $conv = makeConversation(['branch_id' => $branch->id]);

        $id = app(ConversationInboxReadModelService::class)->resolveInitialConversationId($admin, []);

        expect($id)->toBe($conv->id);
    });
});

describe('ConversationInboxReadModelService::findVisibleConversation()', function (): void {

    it('returns null when conversation not found', function (): void {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('Admin');

        $result = app(ConversationInboxReadModelService::class)->findVisibleConversation($admin, 999999);

        expect($result)->toBeNull();
    });

    it('returns null when conversationId is null', function (): void {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $result = app(ConversationInboxReadModelService::class)->findVisibleConversation($admin, null);

        expect($result)->toBeNull();
    });

    it('returns conversation with messages relation when found', function (): void {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole('Admin');

        $conv = makeConversation(['branch_id' => $branch->id]);

        $result = app(ConversationInboxReadModelService::class)->findVisibleConversation($admin, $conv->id);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($conv->id)
            ->and($result->relationLoaded('messages'))->toBeTrue()
            ->and($result->getAttribute('has_more_messages'))->toBeBool();
    });

    it('hides conversation from non-admin in wrong branch', function (): void {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $manager = User::factory()->create(['branch_id' => $ownBranch->id]);
        $manager->assignRole('Manager');

        $conv = makeConversation(['branch_id' => $otherBranch->id]);

        $result = app(ConversationInboxReadModelService::class)->findVisibleConversation($manager, $conv->id);

        expect($result)->toBeNull();
    });
});
