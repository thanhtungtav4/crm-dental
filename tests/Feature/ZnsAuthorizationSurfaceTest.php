<?php

use App\Filament\Pages\ZaloZns;
use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('blocks doctors from accessing zns resource and page surfaces', function (): void {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor)
        ->get(ZnsCampaignResource::getUrl('index'))
        ->assertForbidden();

    $this->actingAs($doctor)
        ->get(ZaloZns::getUrl())
        ->assertForbidden();
});

it('allows managers to access zns resource and page surfaces', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(ZnsCampaignResource::getUrl('index'))
        ->assertOk();

    $this->actingAs($manager)
        ->get(ZaloZns::getUrl())
        ->assertOk();
});

it('blocks doctors from running zns campaign command', function (): void {
    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');

    $this->actingAs($doctor);

    expect(fn () => $this->artisan('zns:run-campaigns'))
        ->toThrow(ValidationException::class, 'Bạn không có quyền chạy campaign ZNS.');
});

it('allows managers to run zns campaign command', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->artisan('zns:run-campaigns')
        ->assertSuccessful();
});
