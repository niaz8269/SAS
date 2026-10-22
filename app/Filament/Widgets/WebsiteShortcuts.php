<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\CmsPages\CmsPageResource;
use App\Filament\Resources\Countries\CountryResource;
use App\Filament\Resources\HomeSections\HomeSectionResource;
use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Filament\Resources\ScholarshipCycles\ScholarshipCycleResource;
use App\Filament\Resources\Services\ServiceResource;
use Filament\Widgets\Widget;

/** Dashboard start point: one-click links to every editable part of the public website. */
class WebsiteShortcuts extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.website-shortcuts';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    protected function getViewData(): array
    {
        $links = [
            ['Site settings', 'Name, logo, contact details, footer, announcement bar', SiteSettings::getUrl(), SiteSettings::canAccess()],
            ['Homepage', 'Edit, hide, reorder or add sections', HomeSectionResource::getUrl(), HomeSectionResource::canViewAny()],
            ['Menus', 'Header and footer links', MenuItemResource::getUrl(), MenuItemResource::canViewAny()],
            ['Pages', 'About, legal pages and any new page', CmsPageResource::getUrl(), CmsPageResource::canViewAny()],
            ['Scholarships', 'Programmes, cycles, deadlines', ScholarshipCycleResource::getUrl(), ScholarshipCycleResource::canViewAny()],
            ['Articles', 'Guides and resources', ArticleResource::getUrl(), ArticleResource::canViewAny()],
            ['Services', 'Service pages and pricing wording', ServiceResource::getUrl(), ServiceResource::canViewAny()],
            ['Destinations', 'Countries and study guidance', CountryResource::getUrl(), CountryResource::canViewAny()],
        ];

        return ['links' => array_values(array_filter($links, fn ($l) => $l[3])), 'siteUrl' => url('/')];
    }
}
