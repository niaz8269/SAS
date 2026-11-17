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

/** What is on the website right now, at a glance. Sections that are still empty (no articles yet, for example) are left out. */
class ContentOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Website';

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('site.manage') || $user?->can('taxonomy.manage') || $user?->can('content.draft'));
    }

    protected function getStats(): array
    {
        $live = ScholarshipCycle::where('publication_state', PublicationState::Published->value)->count();
        $drafts = ScholarshipCycle::whereIn('publication_state', [PublicationState::Draft->value, PublicationState::InReview->value])->count();

        $stats = [
            Stat::make('Live scholarships', number_format($live))->description($drafts ? $drafts.' draft or in review' : 'All published')->icon('heroicon-o-academic-cap'),
            Stat::make('Universities', number_format(University::count()))->description(number_format(UniversityProgramme::count()).' programmes')->icon('heroicon-o-building-library'),
        ];
        foreach ([
            ['Articles published', Article::published()->count(), 'heroicon-o-newspaper', null],
            ['Custom pages live', CmsPage::published()->count(), 'heroicon-o-document-text', null],
            ['Testimonials public', Testimonial::visible()->count(), 'heroicon-o-chat-bubble-bottom-center-text', 'Only with recorded consent'],
            ['Media files', MediaFile::count(), 'heroicon-o-photo', null],
        ] as [$label, $n, $icon, $hint]) {
            if ($n > 0) {
                $stats[] = Stat::make($label, number_format($n))->description($hint)->icon($icon);
            }
        }

        return $stats;
    }
}
