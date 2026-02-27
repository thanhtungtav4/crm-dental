<?php

namespace App\Filament\Resources\PlanItems\Pages;

use App\Filament\Resources\PlanItems\PlanItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanItem extends EditRecord
{
    protected static string $resource = PlanItemResource::class;

    public ?string $returnUrl = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->returnUrl = $this->sanitizeReturnUrl(request()->query('return_url'));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl('index')),
            ForceDeleteAction::make()
                ->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl('index')),
            RestoreAction::make()
                ->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->resolveReturnUrl() ?? static::getResource()::getUrl('index');
    }

    private function resolveReturnUrl(): ?string
    {
        return $this->sanitizeReturnUrl($this->returnUrl);
    }

    private function sanitizeReturnUrl(mixed $returnUrl): ?string
    {
        if (! is_string($returnUrl) || trim($returnUrl) === '') {
            return null;
        }

        $returnUrl = trim($returnUrl);

        if (str_starts_with($returnUrl, '/')) {
            return url($returnUrl);
        }

        $appBaseUrl = rtrim(url('/'), '/');

        if ($returnUrl === $appBaseUrl || str_starts_with($returnUrl, $appBaseUrl.'/')) {
            return $returnUrl;
        }

        return null;
    }
}
