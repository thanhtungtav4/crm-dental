<?php

use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;

it('renders improved patient medical record form layout', function () {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'birthday' => '1990-01-01',
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('filament.admin.resources.patient-medical-records.create', [
        'patient_id' => $patient->id,
    ]));

    $response
        ->assertSuccessful()
        ->assertSee('Liên kết bệnh nhân')
        ->assertSee('Yếu tố nguy cơ lâm sàng')
        ->assertSee('Tóm tắt hồ sơ')
        ->assertSee('Checklist an toàn trước thủ thuật')
        ->assertSee('Mỗi bệnh nhân chỉ có một hồ sơ y tế.')
        ->assertSee($patient->patient_code);
});

it('shows safe birthday message when patient birthday is in the future', function () {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
        'birthday' => now()->addYears(2)->toDateString(),
    ]);

    $this->actingAs($admin);

    $this->get(route('filament.admin.resources.patient-medical-records.create', [
        'patient_id' => $patient->id,
    ]))
        ->assertSuccessful()
        ->assertSee('Ngày sinh chưa hợp lệ');
});
