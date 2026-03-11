<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\OpsControlCenterService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class OpsControlCenter extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Trung tâm OPS';

    protected static string|UnitEnum|null $navigationGroup = 'Cài đặt hệ thống';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'ops-control-center';

    protected string $view = 'filament.pages.ops-control-center';

    public array $state = [];

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->hasRole('Admin')
            && $authUser->can('View:OpsControlCenter');
    }

    public function mount(OpsControlCenterService $service): void
    {
        $this->state = $service->build();
    }

    public function getHeading(): string
    {
        return 'Trung tâm OPS';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Theo dõi control-plane local/test cho backup, restore, readiness và observability sau mỗi lần reset seed.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOverviewCards(): array
    {
        return (array) ($this->state['overview_cards'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAutomationActor(): array
    {
        return (array) ($this->state['automation_actor'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRuntimeBackup(): array
    {
        return (array) ($this->state['runtime_backup'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getIntegrations(): array
    {
        return (array) ($this->state['integrations'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getKpi(): array
    {
        return (array) ($this->state['kpi'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getZns(): array
    {
        return (array) ($this->state['zns'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFinance(): array
    {
        return (array) ($this->state['finance'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGovernance(): array
    {
        return (array) ($this->state['governance'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackupFixtures(): array
    {
        return (array) ($this->state['backup_fixtures'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReadinessFixtures(): array
    {
        return (array) ($this->state['readiness_fixtures'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSignoffFixtures(): array
    {
        return (array) ($this->state['signoff_fixtures'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestRuntimeReport(): ?array
    {
        $report = $this->state['latest_runtime_report'] ?? null;

        return is_array($report) ? $report : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestRuntimeSignoff(): ?array
    {
        $signoff = $this->state['latest_runtime_signoff'] ?? null;

        return is_array($signoff) ? $signoff : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getObservability(): array
    {
        return (array) ($this->state['observability'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentRuns(): array
    {
        return (array) ($this->state['recent_runs'] ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function getSmokeCommands(): array
    {
        return (array) ($this->state['smoke_commands'] ?? []);
    }
}
