<?php

namespace App\Services;

use App\Models\ClinicalMediaAsset;
use App\Models\Patient;

class PatientExamMediaReadModelService
{
    public function __construct(protected ClinicalMediaAccessService $clinicalMediaAccessService) {}

    /**
     * @param  array<int, string>  $selectedIndications
     * @param  array<string, array<int, string>>  $indicationImages
     * @param  array<string, string>  $indicationTypes
     * @return array{
     *     mediaTimeline: array<int, array{
     *         id:int,
     *         captured_at:?string,
     *         phase:string,
     *         modality:string,
     *         anatomy_scope:string,
     *         exam_session_id:?int,
     *         view_url:string,
     *         download_url:string
     *     }>,
     *     mediaPhaseSummary: array<string, int>,
     *     evidenceChecklist: array{
     *         required:int,
     *         fulfilled:int,
     *         completion_percent:int,
     *         missing_labels: array<int, string>,
     *         quality_warnings: array<int, string>
     *     }
     * }
     */
    public function build(
        Patient $patient,
        ?int $activeSessionId,
        array $selectedIndications,
        array $indicationImages,
        array $indicationTypes,
    ): array {
        $mediaAssets = $patient->clinicalMediaAssets()
            ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
            ->latest('captured_at')
            ->limit(120)
            ->get();

        $mediaPhaseSummary = $mediaAssets
            ->groupBy(fn (ClinicalMediaAsset $asset): string => (string) ($asset->phase ?: 'unspecified'))
            ->map(fn ($group): int => $group->count())
            ->toArray();

        $mediaTimeline = $mediaAssets
            ->take(20)
            ->map(function (ClinicalMediaAsset $asset): array {
                return [
                    'id' => (int) $asset->id,
                    'captured_at' => $asset->captured_at?->toDateTimeString(),
                    'phase' => (string) ($asset->phase ?: 'unspecified'),
                    'modality' => (string) ($asset->modality ?: ClinicalMediaAsset::MODALITY_PHOTO),
                    'anatomy_scope' => (string) ($asset->anatomy_scope ?: 'general'),
                    'exam_session_id' => $asset->exam_session_id ? (int) $asset->exam_session_id : null,
                    'view_url' => $this->clinicalMediaAccessService->signedViewUrl($asset),
                    'download_url' => $this->clinicalMediaAccessService->signedDownloadUrl($asset),
                ];
            })
            ->values()
            ->all();

        $activeSessionMedia = $mediaAssets
            ->filter(fn (ClinicalMediaAsset $asset): bool => (int) ($asset->exam_session_id ?? 0) === (int) ($activeSessionId ?? 0))
            ->values();

        $missingIndications = collect($selectedIndications)
            ->filter(function (string $type) use ($activeSessionMedia, $indicationImages): bool {
                $uploadedCount = count((array) ($indicationImages[$type] ?? []));
                $assetCount = $activeSessionMedia
                    ->filter(fn (ClinicalMediaAsset $asset): bool => strtolower((string) data_get($asset->meta, 'indication_type', '')) === $type)
                    ->count();

                return ($uploadedCount + $assetCount) <= 0;
            })
            ->map(fn (string $type): string => (string) ($indicationTypes[$type] ?? strtoupper($type)))
            ->values()
            ->all();

        $requiredEvidenceCount = count($selectedIndications);
        $fulfilledEvidenceCount = max(0, $requiredEvidenceCount - count($missingIndications));
        $evidenceCompletionPercent = $requiredEvidenceCount > 0
            ? (int) floor(($fulfilledEvidenceCount / $requiredEvidenceCount) * 100)
            : 100;

        $qualityWarnings = [];
        if ($activeSessionMedia->whereNull('checksum_sha256')->count() > 0) {
            $qualityWarnings[] = 'Có ảnh chưa có checksum, cần kiểm tra integrity dữ liệu.';
        }
        if ($activeSessionMedia->whereNull('captured_at')->count() > 0) {
            $qualityWarnings[] = 'Có ảnh thiếu thời gian chụp, nên chuẩn hóa metadata.';
        }
        if ($requiredEvidenceCount > 0 && $fulfilledEvidenceCount <= 0) {
            $qualityWarnings[] = 'Phiếu khám đã chọn chỉ định nhưng chưa có bằng chứng ảnh lâm sàng.';
        }

        return [
            'mediaTimeline' => $mediaTimeline,
            'mediaPhaseSummary' => $mediaPhaseSummary,
            'evidenceChecklist' => [
                'required' => $requiredEvidenceCount,
                'fulfilled' => $fulfilledEvidenceCount,
                'completion_percent' => max(0, min(100, $evidenceCompletionPercent)),
                'missing_labels' => $missingIndications,
                'quality_warnings' => $qualityWarnings,
            ],
        ];
    }
}
