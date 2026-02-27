<?php

use App\Models\Patient;
use App\Models\User;

it('loads critical admin pages for treatment and integration flow', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $patient = Patient::factory()->create();

    $pages = [
        "/admin/patients/{$patient->id}?tab=exam-treatment" => 'Kế hoạch điều trị',
        '/admin/treatment-plans/create?plan-step=form.chan-doan-dieu-tri::data::wizard-step' => 'Sơ đồ răng',
        '/admin/integration-settings' => 'Cài đặt tích hợp',
        '/admin/customers' => 'Khách hàng',
    ];

    foreach ($pages as $url => $expectedText) {
        $this->actingAs($admin)
            ->get($url)
            ->assertSuccessful()
            ->assertSeeText($expectedText);
    }
});
