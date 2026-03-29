<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Jobs\SendConversationMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\ConversationInboxReadModelService;
use App\Services\ConversationLeadBindingService;
use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use UnitEnum;

class ConversationInbox extends Page
{
    protected const DEFAULT_VISIBLE_MESSAGE_LIMIT = 30;

    protected const MESSAGE_BATCH_SIZE = 30;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Inbox hội thoại';

    protected static string|UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'conversation-inbox';

    protected string $view = 'filament.pages.conversation-inbox';

    public ?int $selectedConversationId = null;

    public string $draftReply = '';

    public bool $showLeadModal = false;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'provider')]
    public string $providerFilter = 'all';

    #[Url(as: 'tab')]
    public string $inboxTab = 'all';

    public int $visibleMessageLimit = self::DEFAULT_VISIBLE_MESSAGE_LIMIT;

    /**
     * @var array<string, mixed>
     */
    public array $leadForm = [];

    /**
     * @var array<string, mixed>
     */
    public array $handoffForm = [
        'priority' => Conversation::PRIORITY_NORMAL,
        'status' => Conversation::HANDOFF_STATUS_NEW,
        'next_action_at' => '',
        'summary' => '',
    ];

    /**
     * @var array<string, mixed>
     */
    public array $handoffSnapshot = [
        'priority' => Conversation::PRIORITY_NORMAL,
        'status' => Conversation::HANDOFF_STATUS_NEW,
        'next_action_at' => '',
        'summary' => '',
        'version' => 0,
    ];

    /**
     * @var array{assigned_to:string}
     */
    public array $assignmentForm = [
        'assigned_to' => '',
    ];

    /**
     * @var array{assigned_to:string}
     */
    public array $assignmentSnapshot = [
        'assigned_to' => '',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->can('View:ConversationInbox')
            && $authUser->hasAnyAccessibleBranch();
    }

    public function mount(): void
    {
        if (! $this->isConversationSchemaReady()) {
            $this->selectedConversationId = null;
            $this->resetVisibleMessageLimit();
            $this->showLeadModal = false;
            $this->syncHandoffForm(null);
            $this->syncAssignmentForm(null);

            return;
        }

        $this->syncSelectionToCurrentFilters();
    }

    public function getHeading(): string
    {
        return 'Inbox hội thoại đa kênh';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Theo dõi hội thoại Zalo OA và Facebook Messenger theo thời gian thực bằng polling, phản hồi ngay trên CRM và gắn lead vào đúng luồng tư vấn.';
    }

    public function getPollingIntervalSeconds(): int
    {
        return ClinicRuntimeSettings::conversationInboxPollingSeconds();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationListProperty(): Collection
    {
        if (! $this->isConversationSchemaReady()) {
            return new Collection;
        }

        return app(ConversationInboxReadModelService::class)->list(
            $this->currentUser(),
            filters: $this->currentFilters(),
        );
    }

    public function getSelectedConversationProperty(): ?Conversation
    {
        if (! $this->isConversationSchemaReady()) {
            return null;
        }

        return app(ConversationInboxReadModelService::class)
            ->findVisibleConversation(
                $this->currentUser(),
                $this->selectedConversationId,
                $this->currentFilters(),
                $this->visibleMessageLimit,
            );
    }

    /**
     * @return array<int, string>
     */
    public function getBranchOptionsProperty(): array
    {
        return BranchAccess::branchOptionsForCurrentUser();
    }

    /**
     * @return array<int, string>
     */
    public function getAssignableStaffOptionsProperty(): array
    {
        $branchId = filled($this->leadForm['branch_id'] ?? null)
            ? (int) $this->leadForm['branch_id']
            : null;

        return app(PatientAssignmentAuthorizer::class)
            ->assignableStaffOptions($this->currentUser(), $branchId);
    }

    /**
     * @return array<int, string>
     */
    public function getConversationAssigneeOptionsProperty(): array
    {
        $branchId = $this->selectedConversation?->branch_id;

        if (! filled($branchId)) {
            return [];
        }

        return app(PatientAssignmentAuthorizer::class)
            ->assignableStaffOptions($this->currentUser(), (int) $branchId);
    }

    /**
     * @return array<string, string>
     */
    public function getHandoffPriorityOptionsProperty(): array
    {
        return Conversation::handoffPriorityOptions();
    }

    /**
     * @return array<string, string>
     */
    public function getHandoffStatusOptionsProperty(): array
    {
        return Conversation::handoffStatusOptions();
    }

    /**
     * @return array<string, string>
     */
    public function getInboxTabOptionsProperty(): array
    {
        return [
            'all' => 'Tất cả',
            'priority' => 'Ưu tiên',
            'due' => 'Đến hạn',
            'unbound' => 'Chưa gắn lead',
            'mine' => 'Của tôi',
        ];
    }

    /**
     * @return array{unread:int,due:int,unclaimed:int,unbound:int}
     */
    public function getInboxStatsProperty(): array
    {
        if (! $this->isConversationSchemaReady()) {
            return [
                'unread' => 0,
                'due' => 0,
                'unclaimed' => 0,
                'unbound' => 0,
            ];
        }

        return app(ConversationInboxReadModelService::class)->stats(
            $this->currentUser(),
            $this->currentFilters(),
        );
    }

    public function refreshInbox(): void
    {
        if (! $this->isConversationSchemaReady()) {
            $this->selectedConversationId = null;

            return;
        }

        $service = app(ConversationInboxReadModelService::class);
        $selectedConversation = $service->findVisibleConversation(
            $this->currentUser(),
            $this->selectedConversationId,
            $this->currentFilters(),
            $this->visibleMessageLimit,
        );

        if (! $selectedConversation instanceof Conversation) {
            $this->syncSelectionToCurrentFilters();

            return;
        }

        if ((int) $selectedConversation->unread_count > 0) {
            $this->markConversationAsRead($selectedConversation->id);
        }

        if (! $this->handoffFormIsDirty()) {
            $this->syncHandoffForm($selectedConversation);
        }

        if (! $this->assignmentFormIsDirty()) {
            $this->syncAssignmentForm($selectedConversation);
        }
    }

    public function selectConversation(int $conversationId): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        if ($conversationId !== $this->selectedConversationId) {
            $this->resetVisibleMessageLimit();
        }

        $conversation = app(ConversationInboxReadModelService::class)
            ->findVisibleConversation(
                $this->currentUser(),
                $conversationId,
                $this->currentFilters(),
                $this->visibleMessageLimit,
            );

        if (! $conversation instanceof Conversation) {
            return;
        }

        $this->selectedConversationId = $conversation->id;
        $this->markConversationAsRead($conversation->id);
        $this->syncHandoffForm($conversation);
        $this->syncAssignmentForm($conversation);
    }

    public function updatedSearch(): void
    {
        $this->syncSelectionToCurrentFilters();
    }

    public function updatedProviderFilter(): void
    {
        $this->syncSelectionToCurrentFilters();
    }

    public function updatedInboxTab(): void
    {
        $this->syncSelectionToCurrentFilters();
    }

    public function loadOlderMessages(): void
    {
        if (! $this->isConversationSchemaReady() || $this->selectedConversationId === null) {
            return;
        }

        $this->visibleMessageLimit += static::MESSAGE_BATCH_SIZE;
    }

    public function claimConversation(): void
    {
        $actor = $this->currentUser();

        if (! $actor instanceof User) {
            return;
        }

        $this->assignmentForm['assigned_to'] = (string) $actor->id;
        $this->saveConversationAssignee();
    }

    public function releaseConversation(): void
    {
        $this->assignmentForm['assigned_to'] = '';
        $this->saveConversationAssignee();
    }

    public function saveConversationAssignee(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;
        $actor = $this->currentUser();

        if (! $conversation instanceof Conversation || ! $actor instanceof User) {
            return;
        }

        $validated = $this->validate([
            'assignmentForm.assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $assigneeId = filled($validated['assignmentForm']['assigned_to'] ?? null)
            ? app(PatientAssignmentAuthorizer::class)->assertAssignableStaffId(
                actor: $actor,
                staffId: (int) $validated['assignmentForm']['assigned_to'],
                branchId: $conversation->branch_id,
                field: 'assignmentForm.assigned_to',
            )
            : null;

        $updatedConversation = DB::transaction(function () use ($actor, $assigneeId, $conversation): Conversation {
            $lockedConversation = Conversation::query()
                ->visibleTo($actor)
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $lockedConversation->forceFill([
                'assigned_to' => $assigneeId,
            ])->save();

            $freshConversation = $lockedConversation->fresh([
                'assignee:id,name',
                'handoffEditor:id,name',
            ]);

            return $freshConversation instanceof Conversation ? $freshConversation : $lockedConversation;
        }, 3);

        $this->syncAssignmentForm($updatedConversation);

        Notification::make()
            ->title('Đã cập nhật người phụ trách hội thoại')
            ->body($updatedConversation->assignee?->name
                ? 'Hội thoại này hiện do '.$updatedConversation->assignee->name.' phụ trách.'
                : 'Hội thoại đã được nhả claim và quay về queue chung.')
            ->success()
            ->send();
    }

    public function saveHandoffNote(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;
        $actor = $this->currentUser();

        if (! $conversation instanceof Conversation || ! $actor instanceof User) {
            return;
        }

        $validated = $this->validate([
            'handoffForm.priority' => ['required', 'string', Rule::in(array_keys(Conversation::handoffPriorityOptions()))],
            'handoffForm.status' => ['required', 'string', Rule::in(array_keys(Conversation::handoffStatusOptions()))],
            'handoffForm.next_action_at' => ['nullable', 'date'],
            'handoffForm.summary' => ['nullable', 'string', 'max:4000'],
        ]);

        $summary = trim((string) ($validated['handoffForm']['summary'] ?? ''));
        $nextActionAt = filled($validated['handoffForm']['next_action_at'] ?? null)
            ? Carbon::parse((string) $validated['handoffForm']['next_action_at'])->seconds(0)
            : null;
        $expectedVersion = (int) ($this->handoffSnapshot['version'] ?? 0);

        /** @var array{saved: bool, conversation: Conversation} $result */
        $result = DB::transaction(function () use ($actor, $conversation, $expectedVersion, $nextActionAt, $summary, $validated): array {
            $lockedConversation = Conversation::query()
                ->visibleTo($actor)
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ((int) $lockedConversation->handoff_version !== $expectedVersion) {
                $freshConversation = $lockedConversation->fresh(['handoffEditor']);

                return [
                    'saved' => false,
                    'conversation' => $freshConversation instanceof Conversation ? $freshConversation : $lockedConversation,
                ];
            }

            $lockedConversation->forceFill([
                'handoff_priority' => (string) $validated['handoffForm']['priority'],
                'handoff_status' => (string) $validated['handoffForm']['status'],
                'handoff_summary' => $summary !== '' ? $summary : null,
                'handoff_next_action_at' => $nextActionAt,
                'handoff_updated_by' => $actor->id,
                'handoff_updated_at' => now(),
                'handoff_version' => (int) $lockedConversation->handoff_version + 1,
            ])->save();

            $freshConversation = $lockedConversation->fresh(['handoffEditor']);

            return [
                'saved' => true,
                'conversation' => $freshConversation instanceof Conversation ? $freshConversation : $lockedConversation,
            ];
        }, 3);

        $this->syncHandoffForm($result['conversation']);

        if (! $result['saved']) {
            Notification::make()
                ->title('Note vừa được người khác cập nhật trước bạn')
                ->body('CRM đã tải lại nội dung mới nhất để tránh ghi đè nhầm phần bàn giao nội bộ.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Đã lưu note bàn giao')
            ->body('Tóm tắt nội bộ, trạng thái xử lý và lịch follow-up đã được cập nhật cho cả team CSKH.')
            ->success()
            ->send();
    }

    public function sendReply(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $validated = $this->validate([
            'draftReply' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = $this->selectedConversation;
        $actor = $this->currentUser();

        if (! $conversation instanceof Conversation || ! $actor instanceof User) {
            return;
        }

        $message = null;
        $body = trim((string) $validated['draftReply']);

        DB::transaction(function () use (&$message, $conversation, $actor, $body): void {
            $lockedConversation = Conversation::query()
                ->visibleTo($actor)
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $messageAt = now();

            $lockedConversation->forceFill([
                'assigned_to' => $lockedConversation->assigned_to ?: $actor->id,
                'status' => Conversation::STATUS_OPEN,
                'unread_count' => 0,
                'latest_message_preview' => Str::limit($body, 120),
                'last_message_at' => $messageAt,
                'last_outbound_at' => $messageAt,
            ])->save();

            $message = ConversationMessage::query()->create([
                'conversation_id' => $lockedConversation->id,
                'direction' => ConversationMessage::DIRECTION_OUTBOUND,
                'message_type' => ConversationMessage::TYPE_TEXT,
                'body' => $body,
                'status' => ConversationMessage::STATUS_PENDING,
                'sent_by_user_id' => $actor->id,
                'message_at' => $messageAt,
            ]);

            SendConversationMessage::dispatch((int) $message->id)->afterCommit();
        }, 3);

        $this->draftReply = '';

        Notification::make()
            ->title('Đã xếp tin nhắn vào hàng gửi')
            ->body('CRM sẽ gửi phản hồi qua '.$conversation->providerLabel().' ngay khi queue xử lý xong.')
            ->success()
            ->send();
    }

    public function retryMessage(int $messageId): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation instanceof Conversation) {
            return;
        }

        DB::transaction(function () use ($conversation, $messageId): void {
            $message = ConversationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($messageId)
                ->lockForUpdate()
                ->first();

            if (! $message instanceof ConversationMessage || $message->status !== ConversationMessage::STATUS_FAILED) {
                return;
            }

            $message->forceFill([
                'status' => ConversationMessage::STATUS_PENDING,
                'processing_token' => null,
                'processed_at' => null,
                'next_retry_at' => null,
                'last_error' => null,
            ])->save();

            SendConversationMessage::dispatch($message->id)->afterCommit();
        }, 3);

        Notification::make()
            ->title('Đã xếp lại tin nhắn vào hàng gửi')
            ->success()
            ->send();
    }

    public function openLeadForm(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation instanceof Conversation) {
            return;
        }

        if ($conversation->customer_id !== null) {
            Notification::make()
                ->title('Hội thoại này đã được gắn lead')
                ->warning()
                ->send();

            return;
        }

        $this->leadForm = app(ConversationLeadBindingService::class)
            ->prefillForm($conversation, $this->currentUser());
        $this->showLeadModal = true;
        $this->resetErrorBag();
    }

    public function closeLeadForm(): void
    {
        $this->showLeadModal = false;
        $this->resetErrorBag();
    }

    public function createLead(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation instanceof Conversation) {
            return;
        }

        $validated = $this->validate([
            'leadForm.full_name' => ['required', 'string', 'max:255'],
            'leadForm.phone' => ['nullable', 'string', 'max:20'],
            'leadForm.email' => ['nullable', 'email', 'max:255'],
            'leadForm.branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'leadForm.assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'leadForm.notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $customer = app(ConversationLeadBindingService::class)->createLead(
            $conversation,
            $validated['leadForm'],
            $this->currentUser(),
        );

        $this->showLeadModal = false;
        $this->leadForm = [];

        Notification::make()
            ->title('Đã tạo lead từ hội thoại')
            ->body('Lead mới: '.$customer->full_name)
            ->success()
            ->send();
    }

    public function updatedLeadFormBranchId(mixed $value): void
    {
        $assignedTo = $this->leadForm['assigned_to'] ?? null;

        if (! filled($assignedTo)) {
            return;
        }

        $isAllowed = app(PatientAssignmentAuthorizer::class)
            ->scopeAssignableStaff(
                query: User::query()->whereKey((int) $assignedTo),
                actor: $this->currentUser(),
                branchId: filled($value) ? (int) $value : null,
            )
            ->exists();

        if (! $isAllowed) {
            $this->leadForm['assigned_to'] = null;
        }
    }

    public function customerEditUrl(?Conversation $conversation): ?string
    {
        if (! $conversation instanceof Conversation || $conversation->customer_id === null) {
            return null;
        }

        return CustomerResource::getUrl('edit', ['record' => $conversation->customer_id]);
    }

    public function messageStatusLabel(string $status): string
    {
        return match ($status) {
            ConversationMessage::STATUS_SENT => 'Đã gửi',
            ConversationMessage::STATUS_FAILED => 'Lỗi gửi',
            ConversationMessage::STATUS_PENDING => 'Đang chờ gửi',
            ConversationMessage::STATUS_IGNORED => 'Đã bỏ qua',
            default => 'Đã nhận',
        };
    }

    protected function currentUser(): ?User
    {
        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }

    protected function markConversationAsRead(int $conversationId): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $actor = $this->currentUser();

        if (! $actor instanceof User) {
            return;
        }

        Conversation::query()
            ->visibleTo($actor)
            ->whereKey($conversationId)
            ->where('unread_count', '>', 0)
            ->update(['unread_count' => 0]);
    }

    /**
     * @return array{search:string,provider:string,tab:string}
     */
    protected function currentFilters(): array
    {
        return [
            'search' => trim($this->search),
            'provider' => trim($this->providerFilter),
            'tab' => trim($this->inboxTab),
        ];
    }

    protected function syncHandoffForm(?Conversation $conversation): void
    {
        $payload = [
            'priority' => $conversation?->handoffPriorityValue() ?? Conversation::PRIORITY_NORMAL,
            'status' => $conversation?->handoffStatusValue() ?? Conversation::HANDOFF_STATUS_NEW,
            'next_action_at' => $conversation?->handoff_next_action_at?->format('Y-m-d\TH:i') ?? '',
            'summary' => (string) ($conversation?->handoff_summary ?? ''),
            'version' => (int) ($conversation?->handoff_version ?? 0),
        ];

        $this->handoffForm = $payload;
        $this->handoffSnapshot = $payload;
    }

    protected function handoffFormIsDirty(): bool
    {
        return $this->handoffForm !== $this->handoffSnapshot;
    }

    protected function syncAssignmentForm(?Conversation $conversation): void
    {
        $payload = [
            'assigned_to' => $conversation?->assigned_to !== null
                ? (string) $conversation->assigned_to
                : '',
        ];

        $this->assignmentForm = $payload;
        $this->assignmentSnapshot = $payload;
    }

    protected function assignmentFormIsDirty(): bool
    {
        return $this->assignmentForm !== $this->assignmentSnapshot;
    }

    protected function syncSelectionToCurrentFilters(): void
    {
        if (! $this->isConversationSchemaReady()) {
            $this->selectedConversationId = null;
            $this->resetVisibleMessageLimit();
            $this->syncHandoffForm(null);
            $this->syncAssignmentForm(null);

            return;
        }

        $service = app(ConversationInboxReadModelService::class);
        $currentUser = $this->currentUser();
        $filters = $this->currentFilters();
        $previousConversationId = $this->selectedConversationId;
        $selectedConversation = $service->findVisibleConversation(
            $currentUser,
            $this->selectedConversationId,
            $filters,
            $this->visibleMessageLimit,
        );

        if (! $selectedConversation instanceof Conversation) {
            $this->selectedConversationId = $service->resolveInitialConversationId($currentUser, $filters);
        }

        if ($this->selectedConversationId !== $previousConversationId) {
            $this->resetVisibleMessageLimit();
        }

        if ($this->selectedConversationId !== null) {
            $this->markConversationAsRead($this->selectedConversationId);
        }

        $this->syncHandoffForm($this->selectedConversation);
        $this->syncAssignmentForm($this->selectedConversation);
    }

    protected function resetVisibleMessageLimit(): void
    {
        $this->visibleMessageLimit = static::DEFAULT_VISIBLE_MESSAGE_LIMIT;
    }

    public function isConversationSchemaReady(): bool
    {
        return Schema::hasTable('conversations')
            && Schema::hasTable('conversation_messages');
    }
}
