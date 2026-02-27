<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Note;
use App\Models\Patient;
use App\Services\PatientLoyaltyService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunReactivationFlow extends Command
{
    protected $signature = 'growth:run-reactivation-flow {--date= : Ngày chạy (Y-m-d)} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Chạy flow reactivation cho bệnh nhân lâu chưa quay lại và thưởng loyalty khi tái kích hoạt.';

    public function __construct(
        protected PatientLoyaltyService $loyaltyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation reactivation.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->endOfDay()
            : now()->endOfDay();
        $persist = ! $dryRun;

        $inactiveDays = ClinicRuntimeSettings::loyaltyReactivationInactiveDays();
        $inactiveCutoff = $asOf->copy()->subDays($inactiveDays)->endOfDay();

        $ticketUpserted = 0;
        $ticketSkipped = 0;
        $rewarded = 0;

        Patient::query()
            ->with(['customer'])
            ->withMax(
                ['appointments as last_visit_at' => function ($query): void {
                    $query->whereIn('status', Appointment::statusesForQuery([
                        Appointment::STATUS_CONFIRMED,
                        Appointment::STATUS_IN_PROGRESS,
                        Appointment::STATUS_COMPLETED,
                    ]));
                }],
                'date',
            )
            ->orderBy('id')
            ->chunkById(200, function ($patients) use (
                $inactiveCutoff,
                $inactiveDays,
                $persist,
                &$ticketUpserted,
                &$ticketSkipped,
            ): void {
                foreach ($patients as $patient) {
                    $lastVisitAt = $patient->last_visit_at
                        ? Carbon::parse($patient->last_visit_at)
                        : Carbon::parse($patient->created_at ?? now());

                    if ($lastVisitAt->gt($inactiveCutoff)) {
                        continue;
                    }

                    $hasActiveTicket = Note::query()
                        ->where('patient_id', $patient->id)
                        ->where('source_type', Patient::class)
                        ->where('source_id', $patient->id)
                        ->where('care_type', 'reactivation_follow_up')
                        ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                        ->exists();

                    if ($hasActiveTicket) {
                        $ticketSkipped++;

                        continue;
                    }

                    if (! $persist) {
                        $ticketUpserted++;

                        continue;
                    }

                    Note::query()->updateOrCreate(
                        [
                            'source_type' => Patient::class,
                            'source_id' => $patient->id,
                            'care_type' => 'reactivation_follow_up',
                        ],
                        [
                            'patient_id' => $patient->id,
                            'customer_id' => $patient->customer_id,
                            'user_id' => $patient->owner_staff_id ?? $patient->primary_doctor_id,
                            'type' => Note::TYPE_GENERAL,
                            'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
                            'care_status' => Note::CARE_STATUS_NOT_STARTED,
                            'care_mode' => 'scheduled',
                            'is_recurring' => false,
                            'care_at' => now(),
                            'content' => "Bệnh nhân đã {$inactiveDays} ngày chưa quay lại. Cần gọi reactivation và đề xuất lịch tái khám.",
                        ],
                    );

                    $ticketUpserted++;
                }
            });

        Note::query()
            ->with(['patient'])
            ->where('care_type', 'reactivation_follow_up')
            ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use ($persist, &$rewarded): void {
                foreach ($tickets as $ticket) {
                    $patient = $ticket->patient;
                    if (! $patient) {
                        continue;
                    }

                    $hasVisitAfterTicket = Appointment::query()
                        ->where('patient_id', $patient->id)
                        ->whereIn('status', Appointment::statusesForQuery([
                            Appointment::STATUS_CONFIRMED,
                            Appointment::STATUS_IN_PROGRESS,
                            Appointment::STATUS_COMPLETED,
                        ]))
                        ->where('date', '>=', $ticket->care_at ?? $ticket->created_at ?? now())
                        ->exists();

                    if (! $hasVisitAfterTicket) {
                        continue;
                    }

                    $applied = $this->loyaltyService->applyReactivationBonus(
                        patient: $patient,
                        ticket: $ticket,
                        persist: $persist,
                        actorId: auth()->id(),
                    );

                    if ($applied) {
                        $rewarded++;
                    }
                }
            });

        if ($persist) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'growth:run-reactivation-flow',
                    'as_of' => $asOf->toDateString(),
                    'inactive_days' => $inactiveDays,
                    'ticket_upserted' => $ticketUpserted,
                    'ticket_skipped' => $ticketSkipped,
                    'rewarded' => $rewarded,
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info(
            "[{$mode}] Reactivation processed. ".
            "ticket_upserted={$ticketUpserted}, ticket_skipped={$ticketSkipped}, rewarded={$rewarded}",
        );

        return self::SUCCESS;
    }
}
