<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Note;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunInvoiceAgingReminders extends Command
{
    protected $signature = 'finance:run-invoice-aging-reminders {--date= : Ngày chạy (Y-m-d)} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Tạo nhắc thanh toán theo aging bucket cho hóa đơn còn nợ.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation nhắc thanh toán.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->endOfDay()
            : now()->endOfDay();

        $delayDays = ClinicRuntimeSettings::invoiceAgingReminderDelayDays();
        $minIntervalHours = ClinicRuntimeSettings::invoiceAgingReminderMinIntervalHours();
        $dueCutoff = $asOf->copy()->subDays($delayDays)->startOfDay();

        $upserted = 0;
        $skipped = 0;

        Invoice::query()
            ->with(['patient'])
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID, Invoice::STATUS_DRAFT])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $dueCutoff)
            ->chunkById(200, function ($invoices) use (&$upserted, &$skipped, $dryRun, $asOf, $minIntervalHours): void {
                foreach ($invoices as $invoice) {
                    if (! $invoice->patient_id) {
                        continue;
                    }

                    $balance = max(0, round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2));

                    if ($balance <= 0) {
                        continue;
                    }

                    $daysOverdue = max(1, (int) Carbon::parse($invoice->due_date)->startOfDay()->diffInDays($asOf->copy()->startOfDay()));
                    $existingTicket = Note::query()
                        ->where('source_type', Invoice::class)
                        ->where('source_id', $invoice->id)
                        ->where('care_type', 'payment_reminder')
                        ->first();

                    if (
                        $existingTicket
                        && in_array($existingTicket->care_status, Note::activeCareStatuses(), true)
                        && $existingTicket->care_at
                        && $existingTicket->care_at->gt(now()->subHours($minIntervalHours))
                    ) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        Note::query()->updateOrCreate(
                            [
                                'source_type' => Invoice::class,
                                'source_id' => $invoice->id,
                                'care_type' => 'payment_reminder',
                            ],
                            [
                                'patient_id' => $invoice->patient_id,
                                'customer_id' => $invoice->patient?->customer_id,
                                'user_id' => $invoice->patient?->owner_staff_id ?? $invoice->patient?->primary_doctor_id,
                                'type' => Note::TYPE_GENERAL,
                                'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
                                'care_status' => Note::CARE_STATUS_NOT_STARTED,
                                'care_mode' => 'scheduled',
                                'is_recurring' => false,
                                'care_at' => now(),
                                'content' => $this->buildReminderContent($daysOverdue, $balance),
                            ]
                        );
                    }

                    $upserted++;
                }
            });

        $resolved = 0;

        if (! $dryRun) {
            $resolved = Note::query()
                ->where('source_type', Invoice::class)
                ->where('care_type', 'payment_reminder')
                ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('invoices')
                        ->whereColumn('invoices.id', 'notes.source_id')
                        ->where(function ($innerQuery): void {
                            $innerQuery->whereIn('invoices.status', [
                                Invoice::STATUS_PAID,
                                Invoice::STATUS_CANCELLED,
                            ])->orWhereRaw('invoices.paid_amount >= invoices.total_amount');
                        });
                })
                ->update([
                    'care_status' => Note::CARE_STATUS_FAILED,
                ]);

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'finance:run-invoice-aging-reminders',
                    'upserted' => $upserted,
                    'skipped' => $skipped,
                    'resolved' => $resolved,
                    'as_of' => $asOf->toDateString(),
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Invoice aging reminders processed. upserted={$upserted}, skipped={$skipped}, resolved={$resolved}");

        return self::SUCCESS;
    }

    protected function buildReminderContent(int $daysOverdue, float $balance): string
    {
        $amount = number_format($balance, 0, ',', '.').'đ';

        if ($daysOverdue <= 3) {
            return "Nhắc thanh toán: hóa đơn đã quá hạn {$daysOverdue} ngày, công nợ còn {$amount}.";
        }

        if ($daysOverdue <= 7) {
            return "Nhắc thanh toán ưu tiên: quá hạn {$daysOverdue} ngày, công nợ còn {$amount}.";
        }

        return "Cảnh báo công nợ: hóa đơn quá hạn {$daysOverdue} ngày, công nợ còn {$amount}. Cần xử lý gấp.";
    }
}
