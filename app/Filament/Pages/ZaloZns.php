<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use App\Models\User;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsCampaign;
use App\Services\IntegrationProviderHealthReadModelService;
use App\Services\ZnsOperationalReadModelService;
use App\Services\ZnsPayloadSanitizer;
use App\Support\BranchAccess;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;

class ZaloZns extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Zalo ZNS';

    protected static string|\UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'zalo-zns';

    protected string $view = 'filament.pages.zalo-zns';

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return ZnsCampaign::canAccessModule($authUser instanceof User ? $authUser : null);
    }

    public function getHeading(): string
    {
        return 'Zalo ZNS';
    }

    public function getSubheading(): string
    {
        return 'Theo dõi backlog automation, delivery retry/dead-letter và chuyển nhanh sang campaign workflow.';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    /**
     * @return array{
     *   dashboard_section:array{
     *     partial:string,
     *     include_data:array{
     *       panel:array{
     *         dashboard_sections:array<int, array{
     *           partial:string,
     *           include_data:array<string, mixed>
     *         }>
     *       }
     *     }
     *   }
     * }
     */
    #[Computed]
    public function dashboardViewState(): array
    {
        return [
            'dashboard_section' => $this->dashboardSection(),
        ];
    }

    /**
     * @return array{
     *   partial:string,
     *   include_data:array{
     *     panel:array{
     *       dashboard_sections:array<int, array{
     *         partial:string,
     *         include_data:array<string, mixed>
     *       }>
     *     }
     *   }
     * }
     */
    protected function dashboardSection(): array
    {
        return [
            'partial' => 'filament.pages.partials.zalo-zns-dashboard-panel',
            'include_data' => [
                'panel' => [
                    'dashboard_sections' => $this->dashboardSections(),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{
     *   partial:string,
     *   include_data:array<string, mixed>
     * }>
     */
    protected function dashboardSections(): array
    {
        return [
            [
                'partial' => 'filament.pages.partials.zalo-zns-summary-panel',
                'include_data' => [
                    'panel' => $this->summaryPanel(),
                ],
            ],
            [
                'partial' => 'filament.pages.partials.provider-health-panel',
                'include_data' => [
                    'panel' => $this->providerHealthPanel(),
                ],
            ],
            [
                'partial' => 'filament.pages.partials.zalo-zns-note-panels',
                'include_data' => [
                    'panels' => $this->notePanels(),
                ],
            ],
            [
                'partial' => 'filament.pages.partials.zalo-zns-table-panel',
                'include_data' => [],
            ],
        ];
    }

    /**
     * @return array{items:array<int, array<string, mixed>>}
     */
    protected function summaryPanel(): array
    {
        $branchIds = BranchAccess::accessibleBranchIds(BranchAccess::currentUser(), false);

        return [
            'items' => $this->renderedDashboardSummaryCards(
                app(ZnsOperationalReadModelService::class)->dashboardSummaryCards($branchIds),
                containerClasses: 'rounded-xl border p-3',
                valueTypographyClasses: 'text-xl font-semibold',
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array<string, mixed>>
     */
    protected function renderedDashboardSummaryCards(
        array $cards,
        string $containerClasses,
        string $valueTypographyClasses,
    ): array {
        return array_map(
            fn (array $card): array => [
                ...$card,
                'container_classes' => trim(($card['card_classes'] ?? '').' '.$containerClasses),
                'label_classes' => trim('text-sm font-medium '.($card['label_classes'] ?? 'text-gray-500')),
                'value_classes' => trim($valueTypographyClasses.' '.($card['value_classes'] ?? 'text-gray-900 dark:text-white')),
            ],
            $cards,
        );
    }

    /**
     * @return array{
     *   heading:string,
     *   description:string,
     *   drift_count:int,
     *   drift_label:string,
     *   items:array<int, array<string, mixed>>
     * }
     */
    protected function providerHealthPanel(): array
    {
        $providerHealth = collect(app(IntegrationProviderHealthReadModelService::class)->snapshotCards())
            ->whereIn('key', ['zalo_oa', 'zns'])
            ->values()
            ->all();

        $providerHealthDriftCount = collect($providerHealth)
            ->filter(fn (array $provider): bool => filled($provider['issue_badge'] ?? null))
            ->count();

        return [
            'heading' => 'Provider readiness',
            'description' => 'Dùng chung contract với OPS và Integration Settings để triage nhanh runtime drift.',
            'drift_count' => $providerHealthDriftCount,
            'drift_label' => $providerHealthDriftCount.' drift',
            'items' => $providerHealth,
        ];
    }

    /**
     * @return array<int, array{
     *   heading:string,
     *   items:array<int, array<string, string>|string>
     * }>
     */
    protected function notePanels(): array
    {
        return [
            [
                'heading' => 'Triage nhanh',
                'items' => [
                    'Trang này ưu tiên backlog cần xử lý: retry tới hạn, dead-letter, processing kẹt và failed campaign.',
                    'Thao tác đổi trạng thái campaign vẫn đi qua workflow action trong resource campaign, không sửa tay trực tiếp trên page này.',
                    'Dùng filter theo luồng, mã provider và chi nhánh để khoanh vùng lỗi trước khi mở campaign tương ứng.',
                ],
            ],
            [
                'heading' => 'Gợi ý xử lý',
                'items' => [
                    [
                        'tone_classes' => 'text-amber-700 dark:text-amber-300',
                        'label' => 'Retry tới hạn',
                        'description' => 'kiểm tra lỗi provider và template trước khi để scheduler chạy lại.',
                    ],
                    [
                        'tone_classes' => 'text-rose-700 dark:text-rose-300',
                        'label' => 'Dead-letter',
                        'description' => 'ưu tiên root cause, tránh bấm chạy lại campaign khi lỗi đến từ dữ liệu template hoặc số điện thoại.',
                    ],
                    [
                        'tone_classes' => 'text-blue-700 dark:text-blue-300',
                        'label' => 'Campaign running',
                        'description' => 'nếu backlog tăng mà không giảm, mở campaign để kiểm tra deliveries relation manager.',
                    ],
                ],
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openCampaigns')
                ->label('Mở campaign')
                ->icon('heroicon-o-list-bullet')
                ->url(ZnsCampaignResource::getUrl('index')),
            Action::make('createCampaign')
                ->label('Tạo campaign')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->url(ZnsCampaignResource::getUrl('create')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->tableQuery())
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('event_type')
                    ->label('Luồng')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->eventTypeLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER => 'info',
                        ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING => 'warning',
                        ZnsAutomationEvent::EVENT_LEAD_WELCOME => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->default('Không xác định')
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->formatStateUsing(fn (mixed $state, ZnsAutomationEvent $record): string => $this->maskedPhone(
                        $record->normalized_phone ?: $record->phone,
                    ) ?? '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $phoneHash = ZnsAutomationEvent::phoneSearchHash($search);

                        if ($phoneHash === null) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->where('phone_search_hash', $phoneHash);
                    }),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->automationStatusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        ZnsAutomationEvent::STATUS_PENDING => 'gray',
                        ZnsAutomationEvent::STATUS_PROCESSING => 'info',
                        ZnsAutomationEvent::STATUS_SENT => 'success',
                        ZnsAutomationEvent::STATUS_FAILED => 'warning',
                        ZnsAutomationEvent::STATUS_DEAD => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('provider_status_code')
                    ->label('Mã provider')
                    ->default('-')
                    ->toggleable(),
                TextColumn::make('attempts')
                    ->label('Lần thử')
                    ->numeric(),
                TextColumn::make('next_retry_at')
                    ->label('Retry tiếp theo')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                TextColumn::make('processed_at')
                    ->label('Xử lý lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('last_error')
                    ->label('Lỗi cuối')
                    ->default('-')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Luồng')
                    ->options($this->eventTypeOptions())
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options($this->automationStatusOptions())
                    ->multiple(),
                SelectFilter::make('provider_status_code')
                    ->label('Mã provider')
                    ->options(fn (): array => $this->providerStatusOptionsForAutomationTable())
                    ->searchable(),
                SelectFilter::make('branch_id')
                    ->label('Chi nhánh')
                    ->relationship(
                        'branch',
                        'name',
                        fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                    )
                    ->searchable(),
                Filter::make('retry_due')
                    ->label('Retry tới hạn')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', ZnsAutomationEvent::STATUS_FAILED)
                        ->whereNotNull('next_retry_at')
                        ->where('next_retry_at', '<=', now())),
                Filter::make('dead_letter')
                    ->label('Dead-letter')
                    ->query(fn (Builder $query): Builder => $query->where('status', ZnsAutomationEvent::STATUS_DEAD)),
                Filter::make('processing')
                    ->label('Đang processing')
                    ->query(fn (Builder $query): Builder => $query->where('status', ZnsAutomationEvent::STATUS_PROCESSING)),
            ])
            ->emptyStateHeading('Không có backlog ZNS cần triage')
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([]);
    }

    protected function tableQuery(): Builder
    {
        return $this->baseAutomationQuery()
            ->with(['patient', 'branch'])
            ->whereIn('status', [
                ZnsAutomationEvent::STATUS_PENDING,
                ZnsAutomationEvent::STATUS_PROCESSING,
                ZnsAutomationEvent::STATUS_FAILED,
                ZnsAutomationEvent::STATUS_DEAD,
            ]);
    }

    protected function baseAutomationQuery(): Builder
    {
        return BranchAccess::scopeQueryByAccessibleBranches(
            ZnsAutomationEvent::query(),
            column: 'branch_id',
            activeOnly: false,
        );
    }

    protected function baseCampaignQuery(): Builder
    {
        return ZnsCampaign::query()->branchAccessible();
    }

    /**
     * @return array<string, string>
     */
    protected function eventTypeOptions(): array
    {
        return [
            ZnsAutomationEvent::EVENT_LEAD_WELCOME => $this->eventTypeLabel(ZnsAutomationEvent::EVENT_LEAD_WELCOME),
            ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER => $this->eventTypeLabel(ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER),
            ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING => $this->eventTypeLabel(ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function automationStatusOptions(): array
    {
        return [
            ZnsAutomationEvent::STATUS_PENDING => $this->automationStatusLabel(ZnsAutomationEvent::STATUS_PENDING),
            ZnsAutomationEvent::STATUS_PROCESSING => $this->automationStatusLabel(ZnsAutomationEvent::STATUS_PROCESSING),
            ZnsAutomationEvent::STATUS_FAILED => $this->automationStatusLabel(ZnsAutomationEvent::STATUS_FAILED),
            ZnsAutomationEvent::STATUS_DEAD => $this->automationStatusLabel(ZnsAutomationEvent::STATUS_DEAD),
        ];
    }

    protected function eventTypeLabel(string $eventType): string
    {
        return match ($eventType) {
            ZnsAutomationEvent::EVENT_LEAD_WELCOME => 'Welcome lead',
            ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER => 'Nhắc lịch hẹn',
            ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING => 'Chúc mừng sinh nhật',
            default => $eventType,
        };
    }

    protected function automationStatusLabel(string $status): string
    {
        return match ($status) {
            ZnsAutomationEvent::STATUS_PENDING => 'Chờ xử lý',
            ZnsAutomationEvent::STATUS_PROCESSING => 'Đang xử lý',
            ZnsAutomationEvent::STATUS_FAILED => 'Lỗi, sẽ retry',
            ZnsAutomationEvent::STATUS_DEAD => 'Dead-letter',
            ZnsAutomationEvent::STATUS_SENT => 'Đã gửi',
            default => $status,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function providerStatusOptionsForAutomationTable(): array
    {
        return app(ZnsOperationalReadModelService::class)->automationProviderStatusOptions(
            BranchAccess::accessibleBranchIds(BranchAccess::currentUser(), false),
        );
    }

    protected function maskedPhone(?string $phone): ?string
    {
        return app(ZnsPayloadSanitizer::class)->maskPhone($phone);
    }
}
