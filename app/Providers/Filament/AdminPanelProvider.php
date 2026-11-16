<?php

namespace App\Providers\Filament;

use App\Domain\Site\Site;
use App\Filament\Widgets\OperationsOverview;
use App\Http\Middleware\SecurityHeaders;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
            ->defaultThemeMode(ThemeMode::Light) // the light SAS look, like the agent and student portals; staff can still switch to dark
            ->colors(['primary' => Color::hex('#1c4b95'), 'warning' => Color::hex('#caa249')]) // the navy of the SAS logo and public site, so buttons and links look like one product
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.sas-style'))
            ->favicon(fn () => Site::mediaUrl('favicon_path') ?? asset('images/favicon-64.png'))
            ->font('Inter Variable', url: asset('fonts/inter.css'), provider: LocalFontProvider::class)
            // Daily work first, then the content people write, then how the site looks, then the money and the set-up. Rarely used groups start folded.
            ->navigationGroups([
                NavigationGroup::make('Service desk'),
                NavigationGroup::make('Editorial'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('Website'),
                NavigationGroup::make('Recruiting agents')->collapsed(),
                NavigationGroup::make('Finance')->collapsed(),
                NavigationGroup::make('Reports')->collapsed(),
                NavigationGroup::make('Reference data')->collapsed(),
                NavigationGroup::make('Administration')->collapsed(),
            ])
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
                SecurityHeaders::class, // the staff panel has its own middleware list, so the headers must be added here too
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
