<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ConversationInboxReadModelService
{
    public function visibleConversationQuery(?User $actor): Builder
    {
        return Conversation::query()
            ->visibleTo($actor)
            ->with([
                'branch:id,name',
                'customer:id,full_name,status',
                'assignee:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function list(?User $actor, int $limit = 50): Collection
    {
        return $this->visibleConversationQuery($actor)
            ->limit($limit)
            ->get();
    }

    public function resolveInitialConversationId(?User $actor): ?int
    {
        $conversationId = $this->visibleConversationQuery($actor)->value('id');

        return $conversationId !== null ? (int) $conversationId : null;
    }

    public function findVisibleConversation(?User $actor, ?int $conversationId): ?Conversation
    {
        if ($conversationId === null) {
            return null;
        }

        return $this->visibleConversationQuery($actor)
            ->with([
                'messages' => fn ($query) => $query
                    ->with('sender:id,name')
                    ->orderBy('message_at')
                    ->orderBy('id'),
            ])
            ->find($conversationId);
    }
}
