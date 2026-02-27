<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\WebLeadIngestion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WebLeadIngestionService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     ingestion: WebLeadIngestion,
     *     customer: Customer,
     *     created: bool,
     *     replayed: bool
     * }
     */
    public function ingest(array $payload, string $requestId, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        return DB::transaction(function () use ($payload, $requestId, $ipAddress, $userAgent): array {
            $ingestion = WebLeadIngestion::query()
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->first();

            if ($ingestion && in_array($ingestion->status, [WebLeadIngestion::STATUS_CREATED, WebLeadIngestion::STATUS_MERGED], true)) {
                $customer = Customer::query()->findOrFail($ingestion->customer_id);

                return [
                    'ingestion' => $ingestion,
                    'customer' => $customer,
                    'created' => $ingestion->status === WebLeadIngestion::STATUS_CREATED,
                    'replayed' => true,
                ];
            }

            $normalizedPhone = $this->normalizePhone((string) ($payload['phone'] ?? ''));
            $branch = $this->resolveBranch($payload['branch_code'] ?? null);
            $branchCode = $branch?->code ?? (filled($payload['branch_code'] ?? null) ? (string) $payload['branch_code'] : null);

            $ingestion = $ingestion ?? WebLeadIngestion::query()->make([
                'request_id' => $requestId,
            ]);

            $ingestion->fill([
                'source' => 'website',
                'full_name' => (string) ($payload['full_name'] ?? ''),
                'phone' => (string) ($payload['phone'] ?? ''),
                'phone_normalized' => $normalizedPhone,
                'branch_code' => $branchCode,
                'branch_id' => $branch?->id,
                'status' => WebLeadIngestion::STATUS_PENDING,
                'payload' => Arr::except($payload, ['idempotency_key']),
                'response' => null,
                'error_message' => null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'received_at' => $ingestion->received_at ?? now(),
                'processed_at' => null,
            ]);
            try {
                $ingestion->save();
            } catch (QueryException $exception) {
                if (! $this->isDuplicateRequestException($exception)) {
                    throw $exception;
                }

                $lockedIngestion = WebLeadIngestion::query()
                    ->where('request_id', $requestId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($lockedIngestion->status, [WebLeadIngestion::STATUS_CREATED, WebLeadIngestion::STATUS_MERGED], true)) {
                    $customer = Customer::query()->findOrFail($lockedIngestion->customer_id);

                    return [
                        'ingestion' => $lockedIngestion,
                        'customer' => $customer,
                        'created' => $lockedIngestion->status === WebLeadIngestion::STATUS_CREATED,
                        'replayed' => true,
                    ];
                }

                $ingestion = $lockedIngestion;
            }

            $customer = Customer::query()
                ->where(function ($query) use ($normalizedPhone, $payload): void {
                    if ($normalizedPhone !== '') {
                        $query->orWhere('phone_normalized', $normalizedPhone);
                    }

                    $query->orWhere('phone', (string) ($payload['phone'] ?? ''));
                })
                ->latest('id')
                ->first();

            $created = false;

            if ($customer) {
                if (! $customer->branch_id && $branch) {
                    $customer->branch_id = $branch->id;
                }

                $customer->fill([
                    'full_name' => $customer->full_name ?: (string) ($payload['full_name'] ?? ''),
                    'phone' => (string) ($payload['phone'] ?? ''),
                    'phone_normalized' => $normalizedPhone,
                    'source' => $customer->source ?: 'other',
                    'source_detail' => 'website',
                    'last_contacted_at' => now(),
                    'last_web_contact_at' => now(),
                ]);
                $customer->notes = $this->appendWebNote($customer->notes, (string) ($payload['note'] ?? ''), $branchCode);
                $customer->save();
            } else {
                $customer = Customer::query()->create([
                    'branch_id' => $branch?->id,
                    'full_name' => (string) ($payload['full_name'] ?? ''),
                    'phone' => (string) ($payload['phone'] ?? ''),
                    'phone_normalized' => $normalizedPhone,
                    'source' => 'other',
                    'source_detail' => 'website',
                    'status' => 'lead',
                    'last_contacted_at' => now(),
                    'last_web_contact_at' => now(),
                    'notes' => $this->appendWebNote(null, (string) ($payload['note'] ?? ''), $branchCode),
                ]);
                $created = true;
            }

            $status = $created ? WebLeadIngestion::STATUS_CREATED : WebLeadIngestion::STATUS_MERGED;

            $ingestion->fill([
                'customer_id' => $customer->id,
                'status' => $status,
                'response' => [
                    'customer_id' => $customer->id,
                    'branch_id' => $customer->branch_id,
                    'status' => $status,
                ],
                'processed_at' => now(),
            ])->save();

            AuditLog::record(
                entityType: 'web_lead',
                entityId: $ingestion->id,
                action: $created ? AuditLog::ACTION_CREATE : AuditLog::ACTION_UPDATE,
                actorId: null,
                metadata: [
                    'request_id' => $requestId,
                    'customer_id' => $customer->id,
                    'branch_id' => $customer->branch_id,
                    'status' => $status,
                    'ip_address' => $ipAddress,
                ],
            );

            return [
                'ingestion' => $ingestion,
                'customer' => $customer,
                'created' => $created,
                'replayed' => false,
            ];
        });
    }

    protected function resolveBranch(?string $branchCode): ?Branch
    {
        if (filled($branchCode)) {
            return Branch::query()
                ->where('code', (string) $branchCode)
                ->where('active', true)
                ->first();
        }

        $defaultBranchId = (int) ClinicSetting::getValue('web_lead.default_branch_id', 0);
        if ($defaultBranchId <= 0) {
            return null;
        }

        return Branch::query()
            ->whereKey($defaultBranchId)
            ->where('active', true)
            ->first();
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '84')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    protected function appendWebNote(?string $existingNote, string $incomingNote, ?string $branchCode): string
    {
        $parts = [];

        if (filled($existingNote)) {
            $parts[] = trim((string) $existingNote);
        }

        $summary = '[WEB LEAD] '.now()->format('Y-m-d H:i:s');
        if (filled($branchCode)) {
            $summary .= ' | branch_code='.$branchCode;
        }

        if (filled($incomingNote)) {
            $summary .= ' | note='.trim($incomingNote);
        }

        $parts[] = $summary;

        return trim(implode(PHP_EOL, $parts));
    }

    protected function isDuplicateRequestException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, '23000');
    }
}
