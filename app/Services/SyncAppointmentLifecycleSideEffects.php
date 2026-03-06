<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAppointmentLifecycleSideEffects implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const MODE_UPSERT = 'upsert';

    public const MODE_DELETED = 'deleted';

    public const MODE_RESTORED = 'restored';

    public int $tries = 3;

    public function __construct(
        public int $appointmentId,
        public string $mode,
        public bool $statusChanged = false,
        public bool $shouldSyncOperationalArtifacts = false,
        public bool $shouldAttemptLeadConversion = false,
    ) {
        $this->afterCommit();
    }

    public function handle(
        CareTicketService $careTicketService,
        VisitEpisodeService $visitEpisodeService,
        GoogleCalendarSyncEventPublisher $googleCalendarSyncEventPublisher,
        ZnsAutomationEventPublisher $znsAutomationEventPublisher,
        PatientConversionService $patientConversionService,
    ): void {
        $appointment = Appointment::query()
            ->withTrashed()
            ->with(['customer', 'patient', 'doctor', 'branch'])
            ->find($this->appointmentId);

        if (! $appointment) {
            return;
        }

        match ($this->mode) {
            self::MODE_UPSERT => $this->handleUpsert(
                appointment: $appointment,
                careTicketService: $careTicketService,
                visitEpisodeService: $visitEpisodeService,
                googleCalendarSyncEventPublisher: $googleCalendarSyncEventPublisher,
                znsAutomationEventPublisher: $znsAutomationEventPublisher,
                patientConversionService: $patientConversionService,
            ),
            self::MODE_DELETED => $this->handleDeleted(
                appointment: $appointment,
                careTicketService: $careTicketService,
                visitEpisodeService: $visitEpisodeService,
                googleCalendarSyncEventPublisher: $googleCalendarSyncEventPublisher,
                znsAutomationEventPublisher: $znsAutomationEventPublisher,
            ),
            self::MODE_RESTORED => $this->handleRestored(
                appointment: $appointment,
                googleCalendarSyncEventPublisher: $googleCalendarSyncEventPublisher,
                znsAutomationEventPublisher: $znsAutomationEventPublisher,
            ),
            default => null,
        };
    }

    protected function handleUpsert(
        Appointment $appointment,
        CareTicketService $careTicketService,
        VisitEpisodeService $visitEpisodeService,
        GoogleCalendarSyncEventPublisher $googleCalendarSyncEventPublisher,
        ZnsAutomationEventPublisher $znsAutomationEventPublisher,
        PatientConversionService $patientConversionService,
    ): void {
        if ($this->shouldAttemptLeadConversion && ! $appointment->patient_id && $appointment->customer_id) {
            $customer = $appointment->customer;

            if ($customer) {
                $patientConversionService->convert($customer, $appointment);
                $appointment->refresh();
                $appointment->loadMissing(['customer', 'patient', 'doctor', 'branch']);
            }
        }

        if (! $this->shouldSyncOperationalArtifacts) {
            return;
        }

        $careTicketService->syncAppointment($appointment);
        $visitEpisodeService->syncFromAppointment($appointment, $this->statusChanged);
        $googleCalendarSyncEventPublisher->publishForAppointment($appointment);
        $znsAutomationEventPublisher->publishAppointmentReminder($appointment);
    }

    protected function handleDeleted(
        Appointment $appointment,
        CareTicketService $careTicketService,
        VisitEpisodeService $visitEpisodeService,
        GoogleCalendarSyncEventPublisher $googleCalendarSyncEventPublisher,
        ZnsAutomationEventPublisher $znsAutomationEventPublisher,
    ): void {
        $careTicketService->cancelBySource(Appointment::class, $appointment->id, 'appointment_reminder');
        $visitEpisodeService->markAppointmentDeleted($appointment);
        $googleCalendarSyncEventPublisher->publishForAppointment($appointment);
        $znsAutomationEventPublisher->cancelAppointmentReminder(
            appointmentId: (int) $appointment->id,
            reason: 'Lịch hẹn đã bị xóa.',
        );
    }

    protected function handleRestored(
        Appointment $appointment,
        GoogleCalendarSyncEventPublisher $googleCalendarSyncEventPublisher,
        ZnsAutomationEventPublisher $znsAutomationEventPublisher,
    ): void {
        $googleCalendarSyncEventPublisher->publishForAppointment($appointment);
        $znsAutomationEventPublisher->publishAppointmentReminder($appointment);
    }
}
