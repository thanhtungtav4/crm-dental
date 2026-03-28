<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareTicketWorkflowService
{
    protected const TRANSACTION_ATTEMPTS = 5;

    public function upsertSourceTicket(
        array $attributes,
        string $sourceType,
        int $sourceId,
        ?string $scope = null,
        bool $preserveDoneStatus = true,
    ): Note {
        return $this->upsertSourceTicketWithState(
            attributes: $attributes,
            sourceType: $sourceType,
            sourceId: $sourceId,
            scope: $scope,
            preserveDoneStatus: $preserveDoneStatus,
        )['note'];
    }

    /**
     * @return array{note: Note, was_created: bool}
     */
    public function upsertSourceTicketWithState(
        array $attributes,
        string $sourceType,
        int $sourceId,
        ?string $scope = null,
        bool $preserveDoneStatus = true,
    ): array {
        $careType = trim((string) ($attributes['care_type'] ?? ''));

        if ($careType === '') {
            throw ValidationException::withMessages([
                'care_type' => 'Care ticket phải có care_type hợp lệ.',
            ]);
        }

        $ticketKey = Note::ticketKey($sourceType, $sourceId, $careType, $scope);

        return DB::transaction(function () use (
            $attributes,
            $sourceType,
            $sourceId,
            $ticketKey,
            $preserveDoneStatus,
        ): array {
            try {
                return $this->storeTicket(
                    attributes: $attributes,
                    ticketKey: $ticketKey,
                    sourceType: $sourceType,
                    sourceId: $sourceId,
                    preserveDoneStatus: $preserveDoneStatus,
                );
            } catch (QueryException $exception) {
                if (! $this->isDuplicateTicketKeyViolation($exception)) {
                    throw $exception;
                }

                return $this->storeTicket(
                    attributes: $attributes,
                    ticketKey: $ticketKey,
                    sourceType: $sourceType,
                    sourceId: $sourceId,
                    preserveDoneStatus: $preserveDoneStatus,
                );
            }
        }, attempts: self::TRANSACTION_ATTEMPTS);
    }

    public function failActiveTicketsForSource(string $sourceType, int $sourceId, ?string $careType = null): int
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $careType): int {
            $query = Note::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                ->lockForUpdate();

            if ($careType !== null) {
                $query->where('care_type', $careType);
            }

            $updatedCount = 0;

            $query->get()->each(function (Note $note) use (&$updatedCount): void {
                $this->persistTicket(
                    note: $note,
                    attributes: ['care_status' => Note::CARE_STATUS_FAILED],
                    context: [
                        'actor_id' => $this->resolveActorId(),
                        'reason' => 'Dong ticket do nguon chinh khong con hoat dong.',
                        'trigger' => 'source_sync_close',
                    ],
                );

                $updatedCount++;
            });

            return $updatedCount;
        }, attempts: self::TRANSACTION_ATTEMPTS);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateManualTicket(
        Note $note,
        array $attributes,
        ?string $reason = null,
        ?int $actorId = null,
        string $trigger = 'patient_notes_edit',
    ): Note {
        $this->authorizeTicketMutation($note);

        return DB::transaction(function () use ($note, $attributes, $reason, $actorId, $trigger): Note {
            $lockedNote = Note::query()->lockForUpdate()->findOrFail($note->getKey());

            if ($lockedNote->isWorkflowManagedCareTicket()) {
                throw ValidationException::withMessages([
                    'care_status' => 'Ticket automation/canonical chi duoc cap nhat qua workflow chuan.',
                ]);
            }

            if ($lockedNote->care_status === Note::CARE_STATUS_DONE) {
                throw ValidationException::withMessages([
                    'care_status' => 'Ban ghi da hoan thanh, khong the cap nhat.',
                ]);
            }

            return $this->persistTicket(
                note: $lockedNote,
                attributes: $attributes,
                context: array_filter([
                    'actor_id' => $this->resolveActorId($actorId),
                    'reason' => $reason,
                    'trigger' => $trigger,
                ], static fn (mixed $value): bool => $value !== null),
            );
        }, attempts: self::TRANSACTION_ATTEMPTS);
    }

    public function transitionTicket(
        Note $note,
        string $toStatus,
        ?string $reason = null,
        ?int $actorId = null,
        string $trigger = 'manual_status_change',
    ): Note {
        $this->authorizeTicketMutation($note);

        return DB::transaction(function () use ($note, $toStatus, $reason, $actorId, $trigger): Note {
            $lockedNote = Note::query()->lockForUpdate()->findOrFail($note->getKey());

            return $this->persistTicket(
                note: $lockedNote,
                attributes: ['care_status' => $toStatus],
                context: array_filter([
                    'actor_id' => $this->resolveActorId($actorId),
                    'reason' => $reason,
                    'trigger' => $trigger,
                ], static fn (mixed $value): bool => $value !== null),
            );
        }, attempts: self::TRANSACTION_ATTEMPTS);
    }

    protected function storeTicket(
        array $attributes,
        string $ticketKey,
        string $sourceType,
        int $sourceId,
        bool $preserveDoneStatus,
    ): array {
        $note = Note::query()
            ->withTrashed()
            ->where('ticket_key', $ticketKey)
            ->lockForUpdate()
            ->first();

        $wasCreated = ! $note instanceof Note;

        if (! $note instanceof Note) {
            $note = new Note;
        } elseif ($note->trashed()) {
            $note->restore();
            $note = $note->fresh();
        }

        $existingStatus = $note->exists ? $note->care_status : null;

        return [
            'note' => $this->persistTicket(
                note: $note,
                attributes: array_merge($attributes, [
                    'ticket_key' => $ticketKey,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]),
                context: [
                    'actor_id' => $this->resolveActorId(),
                    'trigger' => $wasCreated ? 'source_ticket_create' : 'source_ticket_sync',
                ],
                preserveDoneStatus: $note->exists && $preserveDoneStatus && $existingStatus === Note::CARE_STATUS_DONE,
            ),
            'was_created' => $wasCreated,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $context
     */
    protected function persistTicket(
        Note $note,
        array $attributes,
        array $context = [],
        bool $preserveDoneStatus = false,
    ): Note {
        $note->fill($attributes);

        if ($preserveDoneStatus) {
            $note->care_status = Note::CARE_STATUS_DONE;
        }

        Note::runWithinManagedWorkflow(function () use ($note): void {
            $note->save();
        }, $context);

        return $note->fresh();
    }

    protected function resolveActorId(?int $actorId = null): ?int
    {
        return $actorId ?? (is_numeric(auth()->id()) ? (int) auth()->id() : null);
    }

    protected function authorizeTicketMutation(Note $note): void
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User) {
            throw new AuthorizationException;
        }

        if ($authUser->hasRole('Admin')) {
            return;
        }

        if (! $authUser->can('Update:Note') || ! $authUser->canAccessBranch($note->resolveBranchId())) {
            throw new AuthorizationException;
        }
    }

    protected function isDuplicateTicketKeyViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000' && str_contains($message, 'notes_ticket_key_unique');
    }
}
