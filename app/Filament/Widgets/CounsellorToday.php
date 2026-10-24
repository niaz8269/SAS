<?php

namespace App\Filament\Widgets;

use App\Domain\Counselling\Workload;
use App\Filament\Resources\Students\StudentResource;
use Filament\Widgets\Widget;

/** The counsellor's work list for today: who is waiting, who is due, who has gone quiet, and which deadlines are close. */
class CounsellorToday extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.counsellor-today';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission('students.assigned');
    }

    protected function getViewData(): array
    {
        $work = app(Workload::class)->for(auth()->user());
        $url = fn ($student) => StudentResource::getUrl('view', ['record' => $student]);

        return ['work' => $work, 'url' => $url, 'days' => Workload::STALE_DAYS, 'deadlineDays' => Workload::DEADLINE_DAYS];
    }
}
