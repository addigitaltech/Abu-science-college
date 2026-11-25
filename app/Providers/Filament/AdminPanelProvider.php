<?php

namespace App\Providers\Filament;

use App\Models\SchoolSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\View\PanelsRenderHook;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
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
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->globalSearch()
            ->globalSearchDebounce('300ms')
            ->globalSearchKeyBindings(['ctrl+k'])
            ->brandName(fn () => SchoolSettings::current()->school_name.' admin')
            ->brandLogo(fn () => ($path = SchoolSettings::current()->logo_path) ? \App\Support\MediaUrl::for($path) : null)
            ->brandLogoHeight('2.6rem')
            ->favicon(fn () => ($path = SchoolSettings::current()->favicon_path) ? \App\Support\MediaUrl::for($path) : null)
            ->font('Inter')
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                // School navy (matches the website's --maroon-* / --navy-* tokens)
                'primary' => [
                    50 => '238, 243, 255', 100 => '219, 228, 255', 200 => '184, 201, 255', 300 => '143, 171, 245', 400 => '96, 134, 230',
                    500 => '53, 95, 207', 600 => '22, 59, 156', 700 => '16, 47, 128', 800 => '12, 36, 98', 900 => '8, 26, 72', 950 => '4, 20, 51',
                ],
                'info' => Color::Blue,
                'warning' => Color::Amber,
            ])
            ->navigationGroups([
                NavigationGroup::make('Students & staff'),
                NavigationGroup::make('Results'),
                NavigationGroup::make('Fees & payments')->collapsed(),
                NavigationGroup::make('Classes & subjects')->collapsed(),
                NavigationGroup::make('Admissions & messages')->collapsed(),
                NavigationGroup::make('Website')->collapsed(),
                NavigationGroup::make('Homepage & menus')->collapsed(),
                NavigationGroup::make('Settings')->collapsed(),
                NavigationGroup::make('Advanced')->collapsed(),
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.back-to-site'))
            // Responsive polish and school styling; a plain stylesheet in /public so no Filament theme build is needed.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.theme-link'))
            ->navigationItems([
                NavigationItem::make('Manage results')
                    ->url(fn () => route('staff.results'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->group('Results')
                    ->sort(1)
                    ->visible(fn () => (bool) auth()->user()?->can('manage results')),
                NavigationItem::make('Reports archive')
                    ->url(fn () => route('staff.results.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->group('Results')
                    ->sort(2)
                    ->visible(fn () => (bool) auth()->user()?->can('manage results')),
                NavigationItem::make('View website')
                    ->url(fn () => route('home'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-globe-alt')
                    ->sort(99),
            ])
            ->userMenuItems([
                MenuItem::make()->label('Staff portal')->url(fn () => route('portal.dashboard'))->icon('heroicon-o-home'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\QuickActions::class,
                Widgets\AccountWidget::class,
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
