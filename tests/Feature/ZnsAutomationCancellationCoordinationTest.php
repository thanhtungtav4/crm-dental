<?php

use App\Console\Commands\SyncZnsAutomationEvents;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Services\ZnsAutomationEventPublisher;
use Illuminate\Support\Facades\Http;

it('does not dispatch appointment reminder after the event is cancelled post-claim', function (): void {
    configureZnsCancellationRuntime();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(26),
        'reminder_hours' => 24,
    ]);

    $event = ZnsAutomationEvent::query()
        ->where('appointment_id', $appointment->id)
        ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
        ->firstOrFail();

    $event->update([
        'next_retry_at' => now()->subMinute(),
    ]);

    $command = app(SyncZnsAutomationEvents::class);

    $claim = (function (int $eventId): array {
        return $this->claimEvent($eventId);
    })->call($command, (int) $event->id);

    expect($claim['claimed'] ?? false)->toBeTrue()
        ->and($claim['processing_token'] ?? null)->not->toBeNull();

    $cancelled = app(ZnsAutomationEventPublisher::class)->cancelAppointmentReminder(
        appointmentId: (int) $appointment->id,
        reason: 'Lich hen da bi huy sau khi worker claim event.',
    );

    Http::preventStrayRequests();

    $result = (function (array $claim): string {
        return $this->processClaimedEvent($claim);
    })->call($command, $claim);

    $event = $event->fresh();

    expect($cancelled)->toBe(1)
        ->and($result)->toBe(ZnsAutomationEvent::STATUS_DEAD)
        ->and($event)->not->toBeNull()
        ->and($event?->status)->toBe(ZnsAutomationEvent::STATUS_DEAD)
        ->and($event?->processing_token)->toBeNull()
        ->and($event?->locked_at)->toBeNull()
        ->and((string) ($event?->last_error ?? ''))->toContain('worker claim')
        ->and(ZnsAutomationLog::query()->where('zns_automation_event_id', $event?->id)->count())->toBe(0);

    Http::assertNothingSent();
});

function configureZnsCancellationRuntime(): void
{
    ClinicSetting::setValue('zns.enabled', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.access_token', 'zns_access_token_cancel_guard', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.refresh_token', 'zns_refresh_token_cancel_guard', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.auto_send_appointment_reminder', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.appointment_reminder_default_hours', 24, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('zns.template_appointment', 'tpl_appointment_cancel_guard', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.campaign_delivery_max_attempts', 5, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('zns.request_timeout_seconds', 15, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);
}
