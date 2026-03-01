<?php

use App\Models\User;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

it('auto syncs missing action permissions before authorization check', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    Permission::query()
        ->where('name', ActionPermission::EMR_CLINICAL_WRITE)
        ->delete();

    expect(
        Permission::query()
            ->where('name', ActionPermission::EMR_CLINICAL_WRITE)
            ->exists()
    )->toBeFalse();

    $this->actingAs($admin);

    expect(fn () => ActionGate::authorize(
        ActionPermission::EMR_CLINICAL_WRITE,
        'Bạn không có quyền cập nhật dữ liệu lâm sàng EMR.',
    ))->not->toThrow(ValidationException::class);

    expect(
        Permission::query()
            ->where('name', ActionPermission::EMR_CLINICAL_WRITE)
            ->exists()
    )->toBeTrue();
});

it('keeps denial behavior for unauthorized role after auto sync', function () {
    $cskh = User::factory()->create();
    $cskh->assignRole('CSKH');

    Permission::query()
        ->where('name', ActionPermission::EMR_CLINICAL_WRITE)
        ->delete();

    $this->actingAs($cskh);

    expect(fn () => ActionGate::authorize(
        ActionPermission::EMR_CLINICAL_WRITE,
        'Bạn không có quyền cập nhật dữ liệu lâm sàng EMR.',
    ))->toThrow(ValidationException::class);
});
