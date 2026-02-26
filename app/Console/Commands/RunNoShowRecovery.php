<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Note;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunNoShowRecovery extends Command
{
    protected $signature = 'appointments:run-no-show-recovery {--date= : Ngày chạy (Y-m-d)} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Tạo automation recovery cho lịch hẹn no-show và đóng ticket khi đã recover.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation no-show recovery.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->endOfDay()
            : now();

        $delayHours = ClinicRuntimeSettings::noShowRecoveryDelayHours();
        $recoveryCutoff = $asOf->copy()->subHours($delayHours);
        $createdOrUpdated = 0;

        Appointment::query()
            ->with(['patient'])
            ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_NO_SHOW]))
            ->where('date', '<=', $recoveryCutoff)
            ->chunkById(200, function ($appointments) use (&$createdOrUpdated, $dryRun): void {
                foreach ($appointments as $appointment) {
                    if (! $appointment->patient_id) {
                        continue;
                    }

                    if (! $dryRun) {
                        Note::query()->updateOrCreate(
                            [
                                'source_type' => Appointment::class,
                                'source_id' => $appointment->id,
                                'care_type' => 'no_show_recovery',
                            ],
                            [
                                'patient_id' => $appointment->patient_id,
                                'customer_id' => $appointment->customer_id ?? $appointment->patient?->customer_id,
                                'user_id' => $appointment->assigned_to ?? $appointment->doctor_id,
                                'type' => Note::TYPE_GENERAL,
                                'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
                                'care_status' => Note::CARE_STATUS_NOT_STARTED,
                                'care_mode' => 'scheduled',
                                'is_recurring' => false,
                                'care_at' => now(),
                                'content' => 'Bệnh nhân no-show, cần gọi lại để recovery lịch hẹn.',
                            ]
                        );
                    }

                    $createdOrUpdated++;
                }
            });

        $resolvedCount = 0;

        if (! $dryRun) {
            $resolvedCount = Note::query()
                ->where('source_type', Appointment::class)
                ->where('care_type', 'no_show_recovery')
                ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('appointments')
                        ->whereColumn('appointments.id', 'notes.source_id')
                        ->whereNotIn('appointments.status', Appointment::statusesForQuery([Appointment::STATUS_NO_SHOW]));
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
                    'command' => 'appointments:run-no-show-recovery',
                    'upserted' => $createdOrUpdated,
                    'resolved' => $resolvedCount,
                    'as_of' => $asOf->toDateTimeString(),
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] No-show recovery processed. upserted={$createdOrUpdated}, resolved={$resolvedCount}");

        return self::SUCCESS;
    }
}
