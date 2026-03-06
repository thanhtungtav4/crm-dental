<?php

namespace App\Services;

use App\Models\Note;
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
        $query = Note::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()));

        if ($careType !== null) {
            $query->where('care_type', $careType);
        }

        return $query->update([
            'care_status' => Note::CARE_STATUS_FAILED,
        ]);
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

        $note->fill(array_merge($attributes, [
            'ticket_key' => $ticketKey,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]));

        if ($note->exists && $preserveDoneStatus && $existingStatus === Note::CARE_STATUS_DONE) {
            $note->care_status = Note::CARE_STATUS_DONE;
        }

        $note->save();

        return [
            'note' => $note->fresh(),
            'was_created' => $wasCreated,
        ];
    }

    protected function isDuplicateTicketKeyViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000' && str_contains($message, 'notes_ticket_key_unique');
    }
}
