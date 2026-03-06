<?php

namespace App\Filament\Resources\Appointments;

use App\Models\Appointment;
use App\Services\AppointmentSchedulingService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;

class AppointmentStatusActions
{
    public static function confirm(?Closure $recordResolver = null): Action
    {
        return Action::make('confirm_appointment')
            ->label('Xác nhận')
            ->icon('heroicon-o-check-badge')
            ->color('info')
            ->requiresConfirmation()
            ->successNotificationTitle('Đã xác nhận lịch hẹn')
            ->visible(fn (?Appointment $record = null): bool => self::canTransition($record, $recordResolver, Appointment::STATUS_CONFIRMED))
            ->action(function (?Appointment $record = null) use ($recordResolver): void {
                app(AppointmentSchedulingService::class)->transitionStatus(
                    self::resolveRecord($record, $recordResolver),
                    Appointment::STATUS_CONFIRMED,
                );
            });
    }

    public static function start(?Closure $recordResolver = null): Action
    {
        return Action::make('start_appointment')
            ->label('Bắt đầu khám')
            ->icon('heroicon-o-play')
            ->color('warning')
            ->requiresConfirmation()
            ->successNotificationTitle('Đã chuyển lịch hẹn sang đang khám')
            ->visible(fn (?Appointment $record = null): bool => self::canTransition($record, $recordResolver, Appointment::STATUS_IN_PROGRESS))
            ->action(function (?Appointment $record = null) use ($recordResolver): void {
                app(AppointmentSchedulingService::class)->transitionStatus(
                    self::resolveRecord($record, $recordResolver),
                    Appointment::STATUS_IN_PROGRESS,
                );
            });
    }

    public static function complete(?Closure $recordResolver = null): Action
    {
        return Action::make('complete_appointment')
            ->label('Hoàn thành')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->successNotificationTitle('Đã hoàn thành lịch hẹn')
            ->visible(fn (?Appointment $record = null): bool => self::canTransition($record, $recordResolver, Appointment::STATUS_COMPLETED))
            ->action(function (?Appointment $record = null) use ($recordResolver): void {
                app(AppointmentSchedulingService::class)->transitionStatus(
                    self::resolveRecord($record, $recordResolver),
                    Appointment::STATUS_COMPLETED,
                );
            });
    }

    public static function markNoShow(?Closure $recordResolver = null): Action
    {
        return Action::make('mark_no_show')
            ->label('Ghi nhận không đến')
            ->icon('heroicon-o-user-minus')
            ->color('gray')
            ->form([
                Textarea::make('note')
                    ->label('Ghi chú')
                    ->rows(3)
                    ->placeholder('Ví dụ: Đã gọi nhưng bệnh nhân không phản hồi.'),
            ])
            ->successNotificationTitle('Đã ghi nhận lịch hẹn không đến')
            ->visible(fn (?Appointment $record = null): bool => self::canTransition($record, $recordResolver, Appointment::STATUS_NO_SHOW))
            ->action(function (array $data, ?Appointment $record = null) use ($recordResolver): void {
                app(AppointmentSchedulingService::class)->transitionStatus(
                    self::resolveRecord($record, $recordResolver),
                    Appointment::STATUS_NO_SHOW,
                    [
                        'note' => $data['note'] ?? null,
                        'note_prefix' => 'No-show',
                    ],
                );
            });
    }

    public static function cancel(?Closure $recordResolver = null): Action
    {
        return Action::make('cancel_appointment')
            ->label('Hủy lịch')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form([
                Textarea::make('reason')
                    ->label('Lý do hủy')
                    ->rows(3)
                    ->required(),
            ])
            ->successNotificationTitle('Đã hủy lịch hẹn')
            ->visible(fn (?Appointment $record = null): bool => self::canTransition($record, $recordResolver, Appointment::STATUS_CANCELLED))
            ->action(function (array $data, ?Appointment $record = null) use ($recordResolver): void {
                app(AppointmentSchedulingService::class)->transitionStatus(
                    self::resolveRecord($record, $recordResolver),
                    Appointment::STATUS_CANCELLED,
                    [
                        'reason' => $data['reason'] ?? null,
                    ],
                );
            });
    }

    protected static function canTransition(?Appointment $record, ?Closure $recordResolver, string $toStatus): bool
    {
        $appointment = self::resolveRecord($record, $recordResolver);

        return Appointment::canTransition($appointment->status, $toStatus);
    }

    protected static function resolveRecord(?Appointment $record, ?Closure $recordResolver): Appointment
    {
        if ($record instanceof Appointment) {
            return $record;
        }

        if ($recordResolver instanceof Closure) {
            return $recordResolver();
        }

        throw new \RuntimeException('Khong xac dinh duoc appointment de thuc hien action.');
    }
}
