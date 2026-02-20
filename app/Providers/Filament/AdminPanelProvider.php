<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use MarcelWeidum\Passkeys\PasskeysPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Forms\Components\FileUpload;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Facades\Filament;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandLogo(asset('images/logo.svg'))
            ->favicon(asset('images/logo.svg'))
            ->viteTheme('resources/css/filament/admin/theme.css')

            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(
                    '<link rel="icon" type="image/svg+xml" href="' . asset('images/logo.svg') . '">' .
                    '<link rel="apple-touch-icon" href="' . asset('images/logo.svg') . '">'
                )
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
                        'create', 'view', 'update', 'delete'
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
