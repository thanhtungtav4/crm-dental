<?php

namespace App\Http\Controllers;

use App\Models\ClinicalMediaAccessLog;
use App\Models\ClinicalMediaAsset;
use App\Services\ClinicalMediaAccessService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicalMediaController extends Controller
{
    public function __construct(protected ClinicalMediaAccessService $accessService) {}

    public function view(ClinicalMediaAsset $clinicalMediaAsset): StreamedResponse|Response
    {
        $version = $this->accessService->originalVersion($clinicalMediaAsset);
        $disk = Storage::disk((string) ($version?->storage_disk ?: $clinicalMediaAsset->storage_disk));
        $path = (string) ($version?->storage_path ?: $clinicalMediaAsset->storage_path);
        abort_unless($disk->exists($path), 404);

        $this->accessService->recordAction(
            asset: $clinicalMediaAsset,
            action: ClinicalMediaAccessLog::ACTION_VIEW,
            version: $version,
            purpose: 'clinical-review',
            context: [
                'channel' => 'signed_view',
            ],
        );

        $filename = basename($path);

        return $disk->response($path, $filename, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function download(ClinicalMediaAsset $clinicalMediaAsset): StreamedResponse
    {
        ActionGate::authorize(
            ActionPermission::EMR_RECORD_EXPORT,
            'Bạn không có quyền tải xuống hồ ảnh lâm sàng.',
        );

        $version = $this->accessService->originalVersion($clinicalMediaAsset);
        $disk = Storage::disk((string) ($version?->storage_disk ?: $clinicalMediaAsset->storage_disk));
        $path = (string) ($version?->storage_path ?: $clinicalMediaAsset->storage_path);
        abort_unless($disk->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = 'clinical-media-'.$clinicalMediaAsset->id.($extension ? '.'.$extension : '');

        $this->accessService->recordAction(
            asset: $clinicalMediaAsset,
            action: ClinicalMediaAccessLog::ACTION_DOWNLOAD,
            version: $version,
            purpose: 'clinical-export',
            context: [
                'channel' => 'signed_download',
            ],
        );

        return $disk->download($path, $filename);
    }

    public function share(Request $request, ClinicalMediaAsset $clinicalMediaAsset): JsonResponse
    {
        ActionGate::authorize(
            ActionPermission::EMR_RECORD_EXPORT,
            'Bạn không có quyền chia sẻ hồ ảnh lâm sàng.',
        );

        $payload = $this->accessService->sharePayload($clinicalMediaAsset);

        $this->accessService->recordAction(
            asset: $clinicalMediaAsset,
            action: ClinicalMediaAccessLog::ACTION_SHARE,
            version: $this->accessService->originalVersion($clinicalMediaAsset),
            purpose: (string) ($request->input('purpose') ?: 'clinical-share'),
            context: [
                'channel' => 'signed_share',
                'recipient_hint' => (string) ($request->input('recipient_hint') ?: ''),
                'expires_at' => $payload['expires_at'],
            ],
        );

        return response()->json([
            'ok' => true,
            'data' => $payload,
        ]);
    }
}
