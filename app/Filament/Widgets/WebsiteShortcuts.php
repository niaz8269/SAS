<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\CmsPages\CmsPageResource;
use App\Filament\Resources\Countries\CountryResource;
use App\Filament\Resources\HomeSections\HomeSectionResource;
use App\Filament\Resources\MediaFiles\MediaFileResource;
use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Filament\Resources\PageBlocks\PageBlockResource;
use App\Filament\Resources\ScholarshipCycles\ScholarshipCycleResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Filament\Resources\TrustItems\TrustItemResource;
use App\Filament\Resources\Universities\UniversityResource;
use Filament\Widgets\Widget;

/** Dashboard start point: one-click links to every editable part of the public website. */
class WebsiteShortcuts extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.website-shortcuts';

    /** Only people who can open at least one of these screens see the card (an advisor, for example, sees nothing here). */
    public static function canView(): bool
    {
        return (bool) auth()->user()?->isStaff() && static::links() !== [];
    }

    /** @return list<array{0:string,1:string,2:string,3:bool}> */
    private static function links(): array
    {
        $links = [
            ['Site settings', 'Name, logo, contact details, footer, announcement bar', SiteSettings::getUrl(), SiteSettings::canAccess()],
            ['Homepage', 'Edit, hide, reorder or add sections', HomeSectionResource::getUrl(), HomeSectionResource::canViewAny()],
            ['Menus', 'Header and footer links', MenuItemResource::getUrl(), MenuItemResource::canViewAny()],
            ['Other pages', 'Directory, destinations, services, resources, contact', PageBlockResource::getUrl(), PageBlockResource::canViewAny()],
            ['Pages', 'About, legal pages and any new page', CmsPageResource::getUrl(), CmsPageResource::canViewAny()],
            ['Media library', 'Images and PDFs', MediaFileResource::getUrl(), MediaFileResource::canViewAny()],
            ['Trust section', 'Promises and partner logos on the homepage', TrustItemResource::getUrl(), TrustItemResource::canViewAny()],
            ['Testimonials', 'Real quotes, only with recorded consent', TestimonialResource::getUrl(), TestimonialResource::canViewAny()],
            ['Universities', 'Chinese universities and their programmes', UniversityResource::getUrl(), UniversityResource::canViewAny()],
            ['Scholarships', 'Programmes, cycles, deadlines', ScholarshipCycleResource::getUrl(), ScholarshipCycleResource::canViewAny()],
            ['Articles', 'Guides and resources', ArticleResource::getUrl(), ArticleResource::canViewAny()],
            ['Services', 'Service pages and pricing wording', ServiceResource::getUrl(), ServiceResource::canViewAny()],
            ['Destinations', 'Countries and study guidance', CountryResource::getUrl(), CountryResource::canViewAny()],
        ];

        return array_values(array_filter($links, fn ($l) => $l[3]));
    }

    protected function getViewData(): array
    {
        return ['links' => static::links(), 'siteUrl' => url('/')];
    }
}
