<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationGroup;
use App\Filament\Widgets\DmvStatsOverview;
use App\Livewire\UsersChart;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                 'primary' => '#0B84D8',   // الأزرق
                 'success' => '#22C55E',   // الأخضر
                 'warning' => '#F59E0B',   // البرتقالي
                 'danger'  => '#EF4444',   // الأحمر
                 'gray'    => Color::Slate,

            ])
            ->brandLogo(asset('images/dmv-logo.png'))
            ->favicon(asset('favicon.png'))
            ->brandLogoHeight('80px')
            ->brandName('DMV')
            ->navigationGroups([
                NavigationGroup::make()
                ->label('Content Management'),

                NavigationGroup::make()
                ->label('Subscription Management'),

                NavigationGroup::make()
                ->label('User Management'),
                ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                DmvStatsOverview::class,
                UsersChart::class,

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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
