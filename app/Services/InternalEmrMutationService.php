<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Models\ClinicalNote;
use App\Models\EmrApiMutation;
use App\Models\EmrAuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternalEmrMutationService
{
    public function __construct() {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{mutation: EmrApiMutation, replayed: bool, status_code: int, data: array<string, mixed>}
     */
    public function amendClinicalNote(
        ClinicalNote $clinicalNote,
        array $payload,
        string $requestId,
        ?int $actorId = null,
    ): array {
        $normalizedPayload = $this->normalizePayload($payload);
        $payloadChecksum = hash(
            'sha256',
            json_encode($normalizedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        );

        return DB::transaction(function () use ($clinicalNote, $normalizedPayload, $payloadChecksum, $requestId, $actorId): array {
            $existing = EmrApiMutation::query()
                ->lockForUpdate()
                ->where('request_id', $requestId)
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->payload_checksum, $payloadChecksum)) {
                    throw new IdempotencyConflictException('X-Idempotency-Key đã được dùng với payload khác.');
                }

                return [
                    'mutation' => $existing,
                    'replayed' => true,
                    'status_code' => (int) ($existing->status_code ?: 200),
                    'data' => (array) ($existing->response_payload ?? []),
                ];
            }

            $mutation = EmrApiMutation::query()->create([
                'request_id' => $requestId,
                'endpoint' => '/api/v1/emr/internal/clinical-notes/'.$clinicalNote->id.'/amend',
                'mutation_type' => EmrApiMutation::TYPE_CLINICAL_NOTE_AMEND,
                'payload_checksum' => $payloadChecksum,
                'patient_id' => (int) $clinicalNote->patient_id,
                'clinical_note_id' => (int) $clinicalNote->id,
                'actor_id' => $actorId,
            ]);

            $updatedClinicalNote = app(ClinicalNoteVersioningService::class)->updateWithOptimisticLock(
                clinicalNote: $clinicalNote,
                attributes: Arr::except($normalizedPayload, ['expected_version', 'reason']),
                expectedVersion: (int) $normalizedPayload['expected_version'],
                actorId: $actorId,
                operation: 'amend',
                reason: $normalizedPayload['reason'] ?? null,
            );

            $responseData = [
                'clinical_note_id' => (int) $updatedClinicalNote->id,
                'patient_id' => (int) $updatedClinicalNote->patient_id,
                'visit_episode_id' => $updatedClinicalNote->visit_episode_id ? (int) $updatedClinicalNote->visit_episode_id : null,
                'lock_version' => (int) ($updatedClinicalNote->lock_version ?: 1),
                'updated_at' => $updatedClinicalNote->updated_at?->toISOString(),
            ];

            $mutation->update([
                'status_code' => 200,
                'response_payload' => $responseData,
                'processed_at' => now(),
            ]);

            app(EmrAuditLogger::class)->record(
                entityType: EmrAuditLog::ENTITY_CLINICAL_NOTE,
                entityId: (int) $updatedClinicalNote->id,
                action: EmrAuditLog::ACTION_AMEND,
                patientId: (int) $updatedClinicalNote->patient_id,
                visitEpisodeId: $updatedClinicalNote->visit_episode_id ? (int) $updatedClinicalNote->visit_episode_id : null,
                branchId: $updatedClinicalNote->branch_id ? (int) $updatedClinicalNote->branch_id : null,
                actorId: $actorId,
                context: [
                    'request_id' => $requestId,
                    'mutation_type' => EmrApiMutation::TYPE_CLINICAL_NOTE_AMEND,
                    'expected_version' => (int) $normalizedPayload['expected_version'],
                    'result_version' => (int) ($updatedClinicalNote->lock_version ?: 1),
                    'reason' => $normalizedPayload['reason'] ?? null,
                ],
            );

            return [
                'mutation' => $mutation->fresh(),
                'replayed' => false,
                'status_code' => 200,
                'data' => $responseData,
            ];
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $payload): array
    {
        $normalized = [
            'expected_version' => (int) ($payload['expected_version'] ?? 0),
        ];

        $nullableScalarFields = [
            'examining_doctor_id',
            'treating_doctor_id',
            'general_exam_notes',
            'treatment_plan_note',
            'other_diagnosis',
            'reason',
        ];

        foreach ($nullableScalarFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $normalized[$field] = $payload[$field];
        }

        if (array_key_exists('indications', $payload)) {
            $normalized['indications'] = collect((array) $payload['indications'])
                ->map(fn ($value): string => (string) $value)
                ->values()
                ->all();
        }

        if (array_key_exists('indication_images', $payload)) {
            $normalized['indication_images'] = (array) $payload['indication_images'];
        }

        if (array_key_exists('tooth_diagnosis_data', $payload)) {
            $normalized['tooth_diagnosis_data'] = (array) $payload['tooth_diagnosis_data'];
        }

        $hasAnyMutationField = collect(Arr::except($normalized, ['expected_version', 'reason']))->isNotEmpty();
        if (! $hasAnyMutationField) {
            throw ValidationException::withMessages([
                'payload' => 'Cần truyền ít nhất một trường cập nhật lâm sàng.',
            ]);
        }

        return $normalized;
    }
}
