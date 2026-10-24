<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\OperationsOverview;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Staff administration. Separate from the public site and the student workspace. */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // AUTH-02: with SAS_TWO_STEP on, staff must use multi-factor authentication; accounts without it are sent to set it up first.
        if (config('sas.two_step.enabled')) {
            $panel->multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired: true);
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('SAS Admin')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->unsavedChangesAlerts()
            ->login()
            ->profile() // where staff set up and manage their authenticator app
            ->colors(['primary' => Color::hex('#3b6fc4'), 'warning' => Color::hex('#caa249')])
            ->favicon(asset('images/favicon-64.png'))
            ->font('Inter Variable', url: asset('fonts/inter.css'), provider: LocalFontProvider::class)
            ->navigationGroups(['Website', 'Editorial', 'Service desk', 'Finance', 'Recruiting agents', 'Content', 'Reference data', 'Administration'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([OperationsOverview::class])
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
            ->authMiddleware([Authenticate::class]);
    }
}
