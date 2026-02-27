<?php

use App\Filament\Resources\PlanItems\Pages\EditPlanItem;
use Illuminate\Support\Facades\File;

it('keeps patient context when opening plan item edit from treatment plan list', function (): void {
    $bladePath = resource_path('views/livewire/patient-treatment-plan-section.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain("'return_url' => \$returnUrl");
});

it('captures return url once at mount to avoid livewire internal endpoint redirect', function (): void {
    $componentPath = app_path('Livewire/PatientTreatmentPlanSection.php');
    $component = File::get($componentPath);

    expect($component)->toContain("public string \$returnUrl = '';")
        ->and($component)->toContain('$this->returnUrl = request()->fullUrl();');
});

it('exposes detailed fields in plan item edit form for treatment workflow', function (): void {
    $formPath = app_path('Filament/Resources/PlanItems/Schemas/PlanItemForm.php');
    $form = File::get($formPath);

    expect($form)->toContain("Select::make('diagnosis_ids')")
        ->and($form)->toContain("Select::make('approval_status')")
        ->and($form)->toContain("TextInput::make('discount_percent')")
        ->and($form)->toContain("TextInput::make('discount_amount')")
        ->and($form)->toContain("TextInput::make('vat_amount')")
        ->and($form)->toContain("TextInput::make('final_amount')")
        ->and($form)->toContain("Select::make('status')");
});

it('redirects plan item edit page back to return url after save', function (): void {
    $pagePath = app_path('Filament/Resources/PlanItems/Pages/EditPlanItem.php');
    $page = File::get($pagePath);

    expect($page)->toContain('protected function getRedirectUrl(): string')
        ->and($page)->toContain('DeleteAction::make()')
        ->and($page)->toContain('RestoreAction::make()')
        ->and($page)->toContain('->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl(\'index\'))')
        ->and($page)->toContain("request()->query('return_url')")
        ->and($page)->toContain('isDisallowedReturnPath')
        ->and($page)->toContain('isGetAccessiblePath');
});

it('rejects livewire internal endpoint as return url', function (): void {
    $page = app(EditPlanItem::class);
    $sanitizeReturnUrl = function (mixed $returnUrl): ?string {
        return $this->sanitizeReturnUrl($returnUrl);
    };
    $sanitizeReturnUrl = $sanitizeReturnUrl->bindTo($page, EditPlanItem::class);

    expect($sanitizeReturnUrl(url('/livewire/update')))->toBeNull()
        ->and($sanitizeReturnUrl(url('/admin')))->toBe(url('/admin'))
        ->and($sanitizeReturnUrl('https://example.org/admin'))->toBeNull();
});
