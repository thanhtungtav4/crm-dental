<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Note;
use App\Models\Patient;
use App\Services\CareTicketWorkflowService;
use App\Services\ZnsAutomationEventPublisher;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateBirthdayCareTickets extends Command
{
    protected $signature = 'care:generate-birthday-tickets {--date=} {--dry-run}';

    protected $description = 'Tạo ticket CSKH sinh nhật theo ngày hiện tại.';

    public function handle(
        CareTicketWorkflowService $careTicketWorkflowService,
        ZnsAutomationEventPublisher $znsAutomationEventPublisher,
    ): int {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation chăm sóc sinh nhật.',
        );

        $inputDate = $this->option('date');
        $date = $inputDate ? Carbon::parse($inputDate) : now();
        $date = $date->timezone(config('app.timezone'));

        $scheduledAt = $date->copy()->startOfDay()->addMinutes(5);
        $patients = Patient::query()
            ->whereNotNull('birthday')
            ->whereMonth('birthday', $date->month)
            ->whereDay('birthday', $date->day)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($patients as $patient) {
            $scope = (string) $date->format('Y');
            $ticketKey = Note::ticketKey(Patient::class, $patient->id, 'birthday_care', $scope);

            if ($this->option('dry-run')) {
                if (Note::query()->where('ticket_key', $ticketKey)->exists()) {
                    $skipped++;

                    continue;
                }

                $created++;

                continue;
            }

            $ticketResult = DB::transaction(function () use (
                $careTicketWorkflowService,
                $date,
                $patient,
                $scheduledAt,
                $scope,
                $znsAutomationEventPublisher,
            ): array {
                $result = $careTicketWorkflowService->upsertSourceTicketWithState(
                    attributes: [
                        'patient_id' => $patient->id,
                        'customer_id' => $patient->customer_id,
                        'user_id' => $patient->owner_staff_id ?? $patient->primary_doctor_id ?? $patient->created_by,
                        'type' => Note::TYPE_GENERAL,
                        'care_type' => 'birthday_care',
                        'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
                        'care_status' => Note::CARE_STATUS_NOT_STARTED,
                        'care_mode' => 'scheduled',
                        'is_recurring' => true,
                        'care_at' => $scheduledAt,
                        'content' => 'Chúc mừng sinh nhật '.$patient->full_name,
                    ],
                    sourceType: Patient::class,
                    sourceId: $patient->id,
                    scope: $scope,
                );

                if ($result['was_created']) {
                    $znsAutomationEventPublisher->publishBirthdayGreeting(
                        patient: $patient,
                        asOfDate: $date,
                    );
                }

                return $result;
            }, 3);

            if (! $ticketResult['was_created']) {
                $skipped++;

                continue;
            }

            $created++;
        }

        if (! $this->option('dry-run')) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'care:generate-birthday-tickets',
                    'created' => $created,
                    'skipped' => $skipped,
                    'date' => $date->toDateString(),
                ],
            );
        }

        $this->info("Birthday care tickets - created: {$created}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
