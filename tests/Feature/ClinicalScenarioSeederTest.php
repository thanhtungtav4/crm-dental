<?php

use App\Models\Consent;
use App\Models\PlanItem;
use App\Models\User;
use App\Services\ConsentLifecycleService;
use Database\Seeders\ClinicalScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\seed;

it('creates clinical scenarios for consent gating and media reconcile smoke', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()->where('email', 'doctor.q1@demo.nhakhoaanphuc.test')->firstOrFail();
    $admin = User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->firstOrFail();

    $planItem = PlanItem::query()->where('name', ClinicalScenarioSeeder::PLAN_ITEM_NAME)->firstOrFail();
    $consent = Consent::query()
        ->where('plan_item_id', $planItem->id)
        ->where('consent_version', ClinicalScenarioSeeder::CONSENT_VERSION)
        ->firstOrFail();

    expect(fn () => $planItem->update([
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]))->toThrow(ValidationException::class, 'Thiếu consent hợp lệ');

    app(ConsentLifecycleService::class)->sign(
        consent: $consent,
        signedBy: $doctor->id,
        signatureContext: ['source' => 'seeded_clinical_smoke'],
    );

    $planItem->update([
        'status' => PlanItem::STATUS_IN_PROGRESS,
    ]);

    expect($planItem->fresh()->status)->toBe(PlanItem::STATUS_IN_PROGRESS);

    $this->actingAs($admin);
    $this->artisan('emr:reconcile-clinical-media', ['--strict' => true])
        ->expectsOutputToContain('missing_checksum_asset')
        ->assertFailed();
});
