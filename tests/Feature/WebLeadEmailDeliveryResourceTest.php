<?php

use App\Filament\Resources\WebLeadEmailDeliveries\Pages\ListWebLeadEmailDeliveries;
use App\Filament\Resources\WebLeadEmailDeliveries\WebLeadEmailDeliveryResource;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\User;
use App\Models\WebLeadEmailDelivery;
use App\Services\WebLeadInternalEmailNotificationService;
use Livewire\Livewire;

it('allows admin and manager to access the web lead email delivery resource', function (string $role): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $role === 'Manager' ? $branch->id : null,
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(WebLeadEmailDeliveryResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Mail web lead');
})->with([
    'admin' => 'Admin',
    'manager' => 'Manager',
]);

it('forbids cskh from accessing the web lead email delivery resource', function (): void {
    $user = User::factory()->create();
    $user->assignRole('CSKH');

    $this->actingAs($user)
        ->get(WebLeadEmailDeliveryResource::getUrl('index'))
        ->assertForbidden();
});

it('lets a manager resend a delivery from their branch', function (): void {
    ClinicSetting::setValue('web_lead.internal_email_enabled', true, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $delivery = WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
        'status' => WebLeadEmailDelivery::STATUS_DEAD,
    ]);

    $service = \Mockery::mock(WebLeadInternalEmailNotificationService::class);
    $service->shouldReceive('resend')
        ->once()
        ->withArgs(fn (WebLeadEmailDelivery $record, ?int $actorId): bool => $record->is($delivery) && $actorId === $manager->id)
        ->andReturn($delivery->fresh());
    app()->instance(WebLeadInternalEmailNotificationService::class, $service);

    Livewire::actingAs($manager)
        ->test(ListWebLeadEmailDeliveries::class)
        ->assertCanSeeTableRecords([$delivery])
        ->assertTableActionVisible('resend', $delivery)
        ->callTableAction('resend', $delivery)
        ->assertHasNoActionErrors();
});

it('hides resend action when internal email runtime is disabled', function (): void {
    ClinicSetting::setValue('web_lead.internal_email_enabled', false, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $delivery = WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
        'status' => WebLeadEmailDelivery::STATUS_DEAD,
    ]);

    Livewire::actingAs($manager)
        ->test(ListWebLeadEmailDeliveries::class)
        ->assertTableActionHidden('resend', $delivery);
});

it('allows a manager to view only deliveries from accessible branches', function (): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $visibleDelivery = WebLeadEmailDelivery::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $hiddenDelivery = WebLeadEmailDelivery::factory()->create([
        'branch_id' => $otherBranch->id,
    ]);

    Livewire::actingAs($manager)
        ->test(ListWebLeadEmailDeliveries::class)
        ->assertCanSeeTableRecords([$visibleDelivery])
        ->assertCanNotSeeTableRecords([$hiddenDelivery]);

    $this->actingAs($manager)
        ->get(WebLeadEmailDeliveryResource::getUrl('view', ['record' => $visibleDelivery]))
        ->assertOk()
        ->assertSee((string) $visibleDelivery->id);

    $this->actingAs($manager)
        ->get(WebLeadEmailDeliveryResource::getUrl('view', ['record' => $hiddenDelivery]))
        ->assertNotFound();
});
