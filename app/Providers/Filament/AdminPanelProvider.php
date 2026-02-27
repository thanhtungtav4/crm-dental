<?php

namespace App\Providers\Filament;

use App\Support\ClinicRuntimeSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use MarcelWeidum\Passkeys\PasskeysPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn (): string => ClinicRuntimeSettings::brandingClinicName())
            ->brandLogo(fn (): HtmlString => new HtmlString(sprintf(
                '<img src="%s" alt="%s" style="height:2rem;width:auto;object-fit:contain;" onerror="this.src=\'%s\'">',
                e(ClinicRuntimeSettings::brandingLogoUrl()),
                e(ClinicRuntimeSettings::brandingClinicName()),
                e(asset('images/logo.svg')),
            )))
            ->favicon(asset('images/logo.svg'))
            ->viteTheme('resources/css/filament/admin/theme.css')

            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(sprintf(
                    '<style>:root{--crm-brand-button-bg:%1$s;--crm-brand-button-bg-hover:%2$s;--crm-brand-button-text:%3$s;}</style>'.
                    '<link rel="icon" type="image/svg+xml" href="%4$s">'.
                    '<link rel="apple-touch-icon" href="%4$s">',
                    e(ClinicRuntimeSettings::brandingButtonBackgroundColor()),
                    e(ClinicRuntimeSettings::brandingButtonHoverBackgroundColor()),
                    e(ClinicRuntimeSettings::brandingButtonTextColor()),
                    e(ClinicRuntimeSettings::brandingLogoUrl()),
                ))
            )
            ->colors([
                'primary' => Color::Violet,
            ])
            ->navigationGroups([
                'Hoạt động hàng ngày',
                'Quản lý khách hàng',
                'Chăm sóc khách hàng',
                'Tài chính',
                'Dịch vụ & điều trị',
                'Quản lý kho',
                'Báo cáo & thống kê',
                'Quản lý nhân sự',
                'Quản lý chi nhánh',
                'Ứng dụng mở rộng',
                'Cài đặt hệ thống',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                PasskeysPlugin::make(),
                BreezyCore::make()
                    ->myProfile(
                        condition: true,
                        shouldRegisterUserMenu: true,
                        shouldRegisterNavigation: false,
                        hasAvatars: true,
                        slug: 'my-profile',
                        navigationGroup: null,
                        userMenuLabel: null,
                    )
                    ->enableSanctumTokens(true, permissions: [
                        'create', 'view', 'update', 'delete',
                    ])
                    ->enableBrowserSessions(true)
                    ->avatarUploadComponent(function (FileUpload $fileUpload) {
                        // Use our existing `users.avatar` column and public storage
                        return FileUpload::make('avatar')
                            ->label(__('Avatar'))
                            ->image()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public');
                    })
                    ->enableTwoFactorAuthentication()
                    ->myProfileComponents([
                        'passkeys' => \App\Livewire\PasskeysComponent::class,
                    ]),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
