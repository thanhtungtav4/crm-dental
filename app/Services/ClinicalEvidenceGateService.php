<?php

namespace App\Services;

use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\PlanItem;
use App\Models\TreatmentSession;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClinicalEvidenceGateService
{
    /**
     * @return array{override_used: bool, required_media: int, available_media: int}
     */
    public function assertCanFinalizeClinicalResult(
        ClinicalResult $result,
        ?string $overrideReason = null,
    ): array {
        $requiredMedia = $this->resolveRequiredMediaForClinicalResult($result);
        if ($requiredMedia <= 0) {
            return [
                'override_used' => false,
                'required_media' => 0,
                'available_media' => 0,
            ];
        }

        $availableMedia = $this->countClinicalResultEvidence($result);
        if ($availableMedia >= $requiredMedia) {
            return [
                'override_used' => false,
                'required_media' => $requiredMedia,
                'available_media' => $availableMedia,
            ];
        }

        $this->normalizeOverrideReason($overrideReason, 'evidence_override_reason');
        ActionGate::authorize(
            ActionPermission::EMR_EVIDENCE_OVERRIDE,
            'Bạn không có quyền override thiếu chứng cứ hình ảnh lâm sàng.',
        );

        return [
            'override_used' => true,
            'required_media' => $requiredMedia,
            'available_media' => $availableMedia,
        ];
    }

    /**
     * @return array{override_used: bool, required_media: int, available_media: int}
     */
    public function assertCanCompleteTreatmentSession(
        TreatmentSession $session,
        ?string $overrideReason = null,
    ): array {
        $requiredMedia = $this->resolveRequiredMediaForTreatmentSession($session);
        if ($requiredMedia <= 0) {
            return [
                'override_used' => false,
                'required_media' => 0,
                'available_media' => 0,
            ];
        }

        $availableMedia = $this->countTreatmentSessionEvidence($session);
        if ($availableMedia >= $requiredMedia) {
            return [
                'override_used' => false,
                'required_media' => $requiredMedia,
                'available_media' => $availableMedia,
            ];
        }

        $this->normalizeOverrideReason($overrideReason, 'evidence_override_reason');
        ActionGate::authorize(
            ActionPermission::EMR_EVIDENCE_OVERRIDE,
            'Bạn không có quyền override thiếu chứng cứ hình ảnh điều trị.',
        );

        return [
            'override_used' => true,
            'required_media' => $requiredMedia,
            'available_media' => $availableMedia,
        ];
    }

    protected function resolveRequiredMediaForClinicalResult(ClinicalResult $result): int
    {
        $requirements = ClinicRuntimeSettings::clinicalEvidenceOrderTypeRequirements();
        $orderType = $this->resolveClinicalOrderType($result);

        if ($orderType === null) {
            return 0;
        }

        $direct = $requirements[$orderType] ?? null;
        if ($direct !== null) {
            return max(0, (int) $direct);
        }

        $modality = $this->resolveClinicalOrderModality($result);
        if ($modality !== null && array_key_exists($modality, $requirements)) {
            return max(0, (int) $requirements[$modality]);
        }

        return max(0, (int) ($requirements['*'] ?? 0));
    }

    protected function resolveRequiredMediaForTreatmentSession(TreatmentSession $session): int
    {
        if (! $session->plan_item_id) {
            return ClinicRuntimeSettings::clinicalEvidenceSessionDefaultMinMedia();
        }

        $row = PlanItem::query()
            ->leftJoin('services', 'services.id', '=', 'plan_items.service_id')
            ->where('plan_items.id', (int) $session->plan_item_id)
            ->first([
                'plan_items.service_id as service_id',
                'services.workflow_type as workflow_type',
                'services.protocol_id as protocol_id',
            ]);

        if (! $row || ! filled($row->service_id)) {
            return ClinicRuntimeSettings::clinicalEvidenceSessionDefaultMinMedia();
        }

        if ((string) $row->workflow_type === 'protocol' || filled($row->protocol_id)) {
            return ClinicRuntimeSettings::clinicalEvidenceSessionProtocolMinMedia();
        }

        return ClinicRuntimeSettings::clinicalEvidenceSessionDefaultMinMedia();
    }

    protected function countClinicalResultEvidence(ClinicalResult $result): int
    {
        $mediaCount = ClinicalMediaAsset::query()
            ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
            ->where(function ($query) use ($result): void {
                if ($result->id !== null) {
                    $query->orWhere('clinical_result_id', (int) $result->id);
                }

                if ($result->clinical_order_id !== null) {
                    $query->orWhere('clinical_order_id', (int) $result->clinical_order_id);
                }
            })
            ->count();

        return (int) $mediaCount + $this->countEvidenceCandidatesFromArray((array) ($result->payload ?? []));
    }

    protected function countTreatmentSessionEvidence(TreatmentSession $session): int
    {
        $query = ClinicalMediaAsset::query()
            ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
            ->where(function ($nestedQuery) use ($session): void {
                if ($session->id !== null) {
                    $nestedQuery->orWhere('treatment_session_id', (int) $session->id);
                }

                if ($session->plan_item_id !== null) {
                    $nestedQuery->orWhere('plan_item_id', (int) $session->plan_item_id);
                }
            });

        $mediaCount = (int) $query->count();
        $inlineImages = $this->countEvidenceCandidatesFromArray((array) ($session->images ?? []));

        return $mediaCount + $inlineImages;
    }

    protected function resolveClinicalOrderType(ClinicalResult $result): ?string
    {
        $orderType = $result->clinicalOrder?->order_type;
        if ($orderType === null && $result->clinical_order_id) {
            $orderType = ClinicalOrder::query()
                ->whereKey((int) $result->clinical_order_id)
                ->value('order_type');
        }

        if (! is_string($orderType)) {
            return null;
        }

        $normalized = strtolower(trim($orderType));

        return $normalized !== '' ? $normalized : null;
    }

    protected function resolveClinicalOrderModality(ClinicalResult $result): ?string
    {
        $modality = data_get($result->payload, 'modality');

        if (! is_string($modality) && $result->clinical_order_id) {
            $orderPayload = ClinicalOrder::query()
                ->whereKey((int) $result->clinical_order_id)
                ->value('payload');

            if (is_string($orderPayload)) {
                $decoded = json_decode($orderPayload, true);
                if (is_array($decoded)) {
                    $modality = data_get($decoded, 'modality');
                }
            } elseif (is_array($orderPayload)) {
                $modality = data_get($orderPayload, 'modality');
            }
        }

        if (! is_string($modality)) {
            return null;
        }

        $normalized = strtolower(trim($modality));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeOverrideReason(?string $overrideReason, string $field): string
    {
        $normalizedReason = trim((string) $overrideReason);
        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                $field => 'Thiếu chứng cứ hình ảnh bắt buộc. Vui lòng nhập lý do override.',
            ]);
        }

        return $normalizedReason;
    }

    /**
     * @param  array<mixed>  $payload
     */
    protected function countEvidenceCandidatesFromArray(array $payload): int
    {
        if ($payload === []) {
            return 0;
        }

        $flat = Arr::dot($payload);

        return collect($flat)
            ->filter(function (mixed $value, string|int $key): bool {
                if (! is_string($value)) {
                    return false;
                }

                $normalized = trim($value);
                if ($normalized === '') {
                    return false;
                }

                $keyText = is_string($key) ? strtolower($key) : '';
                $looksLikeEvidenceKey = Str::contains($keyText, [
                    'attachment',
                    'image',
                    'file',
                    'media',
                    'path',
                    'url',
                ]);

                $looksLikeFilePath = Str::contains(strtolower($normalized), [
                    '.jpg',
                    '.jpeg',
                    '.png',
                    '.webp',
                    '.pdf',
                    '/storage/',
                    'http://',
                    'https://',
                ]);

                return $looksLikeEvidenceKey || $looksLikeFilePath;
            })
            ->count();
    }
}
