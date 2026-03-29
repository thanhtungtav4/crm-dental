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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use UnitEnum;

class ConversationInbox extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Inbox hội thoại';

    protected static string|UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'conversation-inbox';

    protected string $view = 'filament.pages.conversation-inbox';

    public ?int $selectedConversationId = null;

    public string $draftReply = '';

    public bool $showLeadModal = false;

    /**
     * @var array<string, mixed>
     */
    public array $leadForm = [];

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
        $this->selectedConversationId = app(ConversationInboxReadModelService::class)
            ->resolveInitialConversationId($this->currentUser());

        if ($this->selectedConversationId !== null) {
            $this->markConversationAsRead($this->selectedConversationId);
        }
    }

    public function getHeading(): string
    {
        return 'Inbox hội thoại Zalo';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Theo dõi hội thoại Zalo OA realtime bằng polling, phản hồi ngay trên CRM và gắn lead vào đúng luồng tư vấn.';
    }

    public function getPollingIntervalSeconds(): int
    {
        return ClinicRuntimeSettings::zaloInboxPollingSeconds();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationListProperty(): Collection
    {
        return app(ConversationInboxReadModelService::class)->list($this->currentUser());
    }

    public function getSelectedConversationProperty(): ?Conversation
    {
        return app(ConversationInboxReadModelService::class)
            ->findVisibleConversation($this->currentUser(), $this->selectedConversationId);
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

    public function refreshInbox(): void
    {
        $service = app(ConversationInboxReadModelService::class);
        $selectedConversation = $service->findVisibleConversation($this->currentUser(), $this->selectedConversationId);

        if (! $selectedConversation instanceof Conversation) {
            $this->selectedConversationId = $service->resolveInitialConversationId($this->currentUser());

            return;
        }

        if ((int) $selectedConversation->unread_count > 0) {
            $this->markConversationAsRead($selectedConversation->id);
        }
    }

    public function selectConversation(int $conversationId): void
    {
        $conversation = app(ConversationInboxReadModelService::class)
            ->findVisibleConversation($this->currentUser(), $conversationId);

        if (! $conversation instanceof Conversation) {
            return;
        }

        $this->selectedConversationId = $conversation->id;
        $this->markConversationAsRead($conversation->id);
    }

    public function sendReply(): void
    {
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
            ->body('CRM sẽ gửi phản hồi qua Zalo OA ngay khi queue xử lý xong.')
            ->success()
            ->send();
    }

    public function retryMessage(int $messageId): void
    {
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
}
