<?php

namespace App\Services;

use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\ClinicalNote;
use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PatientExamMediaWorkflowService
{
    public function __construct(
        protected PatientExamClinicalNoteWorkflowService $patientExamClinicalNoteWorkflowService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, mixed>  $uploads
     * @return array{clinicalNote:?ClinicalNote,paths:array<int,string>}
     */
    public function storeUploads(
        Patient $patient,
        ?ExamSession $session,
        ?ClinicalNote $clinicalNote,
        array $payload,
        string $indicationType,
        array $uploads,
        ?User $actor = null,
        ?int $actorId = null,
    ): array {
        if (! $session instanceof ExamSession) {
            return [
                'clinicalNote' => $clinicalNote,
                'paths' => [],
            ];
        }

        $clinicalNote = $this->patientExamClinicalNoteWorkflowService->ensurePersisted(
            patient: $patient,
            session: $session,
            clinicalNote: $clinicalNote,
            payload: $payload,
            actor: $actor,
            actorId: $actorId,
        );

        if (! $clinicalNote instanceof ClinicalNote) {
            return [
                'clinicalNote' => $clinicalNote,
                'paths' => [],
            ];
        }

        $storedPaths = [];

        foreach ($uploads as $upload) {
            if (! $upload || ! method_exists($upload, 'store')) {
                continue;
            }

            $path = $upload->store("patients/{$patient->id}/indications/{$indicationType}", 'public');

            if (! is_string($path) || $path === '') {
                continue;
            }

            $storedPaths[] = $path;

            $this->createAsset(
                patient: $patient,
                clinicalNote: $clinicalNote,
                session: $session,
                indicationType: $indicationType,
                storagePath: $path,
                actorId: $actorId,
            );
        }

        return [
            'clinicalNote' => $clinicalNote,
            'paths' => $storedPaths,
        ];
    }

    public function createAsset(
        Patient $patient,
        ClinicalNote $clinicalNote,
        ?ExamSession $session,
        string $indicationType,
        string $storagePath,
        ?int $actorId = null,
    ): ?ClinicalMediaAsset {
        $existing = ClinicalMediaAsset::query()
            ->where('patient_id', $patient->id)
            ->where('exam_session_id', $session?->id)
            ->where('storage_disk', 'public')
            ->where('storage_path', $storagePath)
            ->first();

        if ($existing instanceof ClinicalMediaAsset) {
            return $existing;
        }

        $asset = ClinicalMediaAsset::query()->create([
            'patient_id' => (int) $patient->id,
            'visit_episode_id' => $clinicalNote->visit_episode_id ? (int) $clinicalNote->visit_episode_id : null,
            'exam_session_id' => $session?->id ? (int) $session->id : null,
            'branch_id' => $clinicalNote->branch_id
                ? (int) $clinicalNote->branch_id
                : ($patient->first_branch_id ? (int) $patient->first_branch_id : null),
            'captured_by' => $actorId,
            'captured_at' => now(),
            'modality' => in_array($indicationType, ['xray', 'panorama', 'cephalometric', '3d', '3d5x5'], true)
                ? ClinicalMediaAsset::MODALITY_XRAY
                : ClinicalMediaAsset::MODALITY_PHOTO,
            'anatomy_scope' => match ($indicationType) {
                'int' => 'intraoral',
                'ext' => 'extraoral',
                default => $indicationType !== '' ? $indicationType : 'general',
            },
            'phase' => 'pre',
            'mime_type' => $this->guessMimeTypeFromPath($storagePath),
            'file_size_bytes' => $this->resolveStoredFileSize($storagePath),
            'checksum_sha256' => $this->resolveStoredFileChecksum($storagePath),
            'storage_disk' => 'public',
            'storage_path' => $storagePath,
            'status' => ClinicalMediaAsset::STATUS_ACTIVE,
            'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
            'legal_hold' => false,
            'meta' => [
                'source_table' => 'clinical_notes.indication_images',
                'source_id' => (int) $clinicalNote->id,
                'indication_type' => $indicationType,
            ],
        ]);

        ClinicalMediaVersion::query()->create([
            'clinical_media_asset_id' => (int) $asset->id,
            'version_number' => 1,
            'is_original' => true,
            'mime_type' => $asset->mime_type,
            'checksum_sha256' => $asset->checksum_sha256,
            'storage_disk' => $asset->storage_disk,
            'storage_path' => $asset->storage_path,
            'created_by' => $actorId,
        ]);

        return $asset;
    }

    public function removeAsset(Patient $patient, ?ExamSession $session, string $storagePath): void
    {
        Storage::disk('public')->delete($storagePath);

        ClinicalMediaAsset::query()
            ->where('patient_id', $patient->id)
            ->where('exam_session_id', $session?->id)
            ->where('storage_disk', 'public')
            ->where('storage_path', $storagePath)
            ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
            ->get()
            ->each
            ->delete();
    }

    protected function guessMimeTypeFromPath(string $path): ?string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => null,
        };
    }

    protected function resolveStoredFileChecksum(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);

            if (is_resource($stream)) {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                fclose($stream);

                return hash_final($hash);
            }
        }

        return hash('sha256', 'public|'.$path);
    }

    protected function resolveStoredFileSize(string $path): ?int
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $size = Storage::disk('public')->size($path);

        return is_int($size) ? $size : null;
    }
}
