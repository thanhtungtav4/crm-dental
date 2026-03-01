<?php

use Filament\Actions\Action;

it('applies crm default failure notification title for custom filament actions', function (): void {
    $action = Action::make('sync_pipeline')
        ->label('Đồng bộ pipeline');

    expect($action->getFailureNotificationTitle())->toBe('Không thể xử lý: Đồng bộ pipeline');
});

it('allows explicit failure notification title overrides on specific actions', function (): void {
    $action = Action::make('custom_action')
        ->label('Tác vụ đặc biệt')
        ->failureNotificationTitle('Thất bại tùy chỉnh');

    expect($action->getFailureNotificationTitle())->toBe('Thất bại tùy chỉnh');
});
