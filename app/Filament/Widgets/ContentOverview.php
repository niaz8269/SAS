<?php

namespace App\Filament\Widgets;

use App\Enums\PublicationState;
use App\Models\Article;
use App\Models\CmsPage;
use App\Models\MediaFile;
use App\Models\ScholarshipCycle;
use App\Models\Testimonial;
use App\Models\University;
use App\Models\UniversityProgramme;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** What is on the website right now, at a glance. */
class ContentOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Website content';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('site.manage') || $user?->can('taxonomy.manage') || $user?->can('content.draft'));
    }

    protected function getStats(): array
    {
        $live = ScholarshipCycle::where('publication_state', PublicationState::Published->value)->count();
        $drafts = ScholarshipCycle::whereIn('publication_state', [PublicationState::Draft->value, PublicationState::InReview->value])->count();

        return [
            Stat::make('Live scholarships', $live)->description($drafts.' draft or in review')->icon('heroicon-o-academic-cap'),
            Stat::make('Universities', University::count())->description(number_format(UniversityProgramme::count()).' programmes')->icon('heroicon-o-building-library'),
            Stat::make('Articles published', Article::published()->count())->icon('heroicon-o-newspaper'),
            Stat::make('Custom pages live', CmsPage::published()->count())->icon('heroicon-o-document-text'),
            Stat::make('Testimonials public', Testimonial::visible()->count())->description('Only with recorded consent')->icon('heroicon-o-chat-bubble-bottom-center-text'),
            Stat::make('Media files', MediaFile::count())->icon('heroicon-o-photo'),
        ];
    }
}
