<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Support\ConversationProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ConversationInboxReadModelService
{
    /**
     * @param  array{search?:string,provider?:string,tab?:string}  $filters
     */
    public function visibleConversationQuery(?User $actor, array $filters = []): Builder
    {
        $query = Conversation::query()
            ->visibleTo($actor)
            ->with([
                'branch:id,name',
                'customer:id,full_name,status',
                'assignee:id,name',
                'handoffEditor:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        return $this->applyFilters($query, $actor, $filters);
    }

    /**
     * @param  array{search?:string,provider?:string,tab?:string}  $filters
     * @return Collection<int, Conversation>
     */
    public function list(?User $actor, int $limit = 50, array $filters = []): Collection
    {
        return $this->visibleConversationQuery($actor, $filters)
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{search?:string,provider?:string,tab?:string}  $filters
     */
    public function resolveInitialConversationId(?User $actor, array $filters = []): ?int
    {
        $conversationId = $this->visibleConversationQuery($actor, $filters)->value('id');

        return $conversationId !== null ? (int) $conversationId : null;
    }

    /**
     * @param  array{search?:string,provider?:string,tab?:string}  $filters
     * @return array{unread:int,due:int,unclaimed:int,unbound:int}
     */
    public function stats(?User $actor, array $filters = []): array
    {
        $baseQuery = $this->visibleConversationQuery($actor, [
            'search' => $filters['search'] ?? '',
            'provider' => $filters['provider'] ?? '',
        ]);

        return [
            'unread' => (int) (clone $baseQuery)->where('unread_count', '>', 0)->count(),
            'due' => (int) (clone $baseQuery)
                ->whereNotNull('handoff_next_action_at')
                ->where('handoff_next_action_at', '<=', now())
                ->count(),
            'unclaimed' => (int) (clone $baseQuery)->whereNull('assigned_to')->count(),
            'unbound' => (int) (clone $baseQuery)->whereNull('customer_id')->count(),
        ];
    }

    /**
     * @param  array{search?:string,provider?:string,tab?:string}  $filters
     */
    public function findVisibleConversation(?User $actor, ?int $conversationId, array $filters = [], int $messageLimit = 30): ?Conversation
    {
        if ($conversationId === null) {
            return null;
        }

        $conversation = $this->visibleConversationQuery($actor, $filters)->find($conversationId);

        if (! $conversation instanceof Conversation) {
            return null;
        }

        $safeMessageLimit = max(1, $messageLimit);
        $descendingMessages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender:id,name')
            ->orderByDesc('message_at')
            ->orderByDesc('id')
            ->limit($safeMessageLimit + 1)
            ->get();

        $hasMoreMessages = $descendingMessages->count() > $safeMessageLimit;
        $messages = $descendingMessages
            ->take($safeMessageLimit)
            ->reverse()
            ->values();

        $conversation->setRelation('messages', $messages);
        $conversation->setAttribute('has_more_messages', $hasMoreMessages);
        $conversation->setAttribute('loaded_message_count', $messages->count());
        $conversation->syncOriginal();

        return $conversation;
    }

    /**
     * @param  array{search?:string,provider?:string,tab?:string}  $filters
     */
    protected function applyFilters(Builder $query, ?User $actor, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $searchTerm = '%'.str_replace(' ', '%', $search).'%';

            $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                $searchQuery
                    ->where('external_display_name', 'like', $searchTerm)
                    ->orWhere('external_user_id', 'like', $searchTerm)
                    ->orWhere('latest_message_preview', 'like', $searchTerm)
                    ->orWhere('handoff_summary', 'like', $searchTerm)
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($searchTerm): void {
                        $customerQuery
                            ->where('full_name', 'like', $searchTerm)
                            ->orWhere('phone', 'like', $searchTerm);
                    });
            });
        }

        $provider = ConversationProvider::tryFromNullable($filters['provider'] ?? null);

        if ($provider instanceof ConversationProvider) {
            $query->where('provider', $provider->value);
        }

        return match ((string) ($filters['tab'] ?? 'all')) {
            'priority' => $query->whereIn('handoff_priority', [
                Conversation::PRIORITY_HIGH,
                Conversation::PRIORITY_URGENT,
            ]),
            'due' => $query
                ->whereNotNull('handoff_next_action_at')
                ->where('handoff_next_action_at', '<=', now()),
            'unbound' => $query->whereNull('customer_id'),
            'mine' => $actor instanceof User
                ? $query->where('assigned_to', $actor->id)
                : $query->whereRaw('1 = 0'),
            default => $query,
        };
    }
}
