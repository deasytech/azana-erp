<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /** The Azana orange (#F08732 at 500), darkened for text and hover and lightened for tints. */
    private const PRIMARY = [
        50 => '#fff6ee', 100 => '#fdebd9', 200 => '#fbd5b0', 300 => '#f8bb83', 400 => '#f4a05a',
        500 => '#f08732', 600 => '#d96f20', 700 => '#b5571a', 800 => '#92461b', 900 => '#773b19', 950 => '#411d0a',
    ];

    /**
     * Sidebar order: the farm's day-to-day work first, then what it consumes and sells, then money, management and set-up. The group names
     * belong to the resources; this only orders them and folds the ones used least.
     *
     * @return list<NavigationGroup>
     */
    private function navigationGroups(): array
    {
        $groups = ['Animals', 'Farm structure', 'Breeding', 'Health', 'Biosecurity', 'Production', 'Feed mill', 'Inventory', 'Purchasing', 'Semen', 'Sales', 'Slaughter & meat', 'Finance', 'Tasks & alerts', 'Management', 'Website', 'Master data', 'Configuration', 'Administration'];

        // Filament does not open a folded group on its own page, so only the groups used least are folded; the rest stay open.
        $folded = ['Farm structure', 'Biosecurity', 'Feed mill', 'Semen', 'Slaughter & meat', 'Website', 'Master data', 'Configuration', 'Administration'];

        return array_map(fn (string $name): NavigationGroup => NavigationGroup::make($name)->collapsed(in_array($name, $folded, true)), $groups);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->domain(config('website.erp_host'))
            ->login()
            ->profile()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            // Our relation managers carry permission-checked actions (add identifier, record weight...).
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: fn (): bool => (bool) auth()->user()?->requiresTwoFactor(),
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('Azana Farms')
            ->brandLogo(asset('images/branding/logo.jpeg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/branding/logo.jpeg'))
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->maxContentWidth(Width::Full)
            ->navigationGroups($this->navigationGroups())
            // Orange is the brand accent (actions, active states); the neutrals are warm stone, and status colours keep their own meaning.
            ->colors([
                'primary' => self::PRIMARY,
                'gray' => Color::Stone,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
            ])
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
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
