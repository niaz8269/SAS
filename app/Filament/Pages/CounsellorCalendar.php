<?php

namespace App\Filament\Pages;

use App\Domain\Counselling\Workload;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Consultation;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** The next 30 days as one agenda: consultations, follow-ups the counsellor set, and closing dates of their students' unfinished applications. */
class CounsellorCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Service desk';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'My calendar';

    protected static ?string $slug = 'my-calendar';

    protected string $view = 'filament.pages.counsellor-calendar';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff() && auth()->user()->hasPermission('students.assigned');
    }

    /** @return array<string,list<array{time:?string,kind:string,text:string,url:?string}>> keyed by date (Y-m-d), in date order */
    public function agenda(): array
    {
        $user = auth()->user();
        $workload = app(Workload::class);
        $students = $workload->students($user);
        $items = [];
        $add = function ($date, string $kind, string $text, ?string $url = null, ?string $time = null) use (&$items) {
            $items[$date->format('Y-m-d')][] = ['time' => $time, 'kind' => $kind, 'text' => $text, 'url' => $url];
        };

        foreach ($students as $s) {
            $f = $s->latestFollowUp;
            if ($f?->follow_up_on && $f->follow_up_on->lte(today()->addDays(30))) {
                $add($f->follow_up_on->lt(today()) ? today() : $f->follow_up_on, 'Follow-up', $s->name.($f->follow_up_on->lt(today()) ? ' (overdue since '.$f->follow_up_on->format('j M').')' : '').($f->comment ? ': '.str($f->comment)->limit(70) : ''), StudentResource::getUrl('view', ['record' => $s]));
            }
        }
        foreach ($workload->for($user)['deadlines'] as $row) {
            $add($row['closes'], 'Deadline', $row['student']->name.': '.$row['plan']->cycle->programme->title.' closes', StudentResource::getUrl('view', ['record' => $row['student']]));
        }
        Consultation::where('advisor_id', $user->id)->whereNotNull('confirmed_start_utc')->whereBetween('confirmed_start_utc', [now()->startOfDay(), now()->addDays(30)])->with('user')->get()
            ->each(fn ($c) => $add($c->confirmed_start_utc, 'Consultation', ($c->user?->name ?: 'Client').' ('.$c->reference.')', null, $c->confirmed_start_utc->format('H:i').' UTC'));

        ksort($items);

        return $items;
    }

    protected function getViewData(): array
    {
        return ['agenda' => $this->agenda()];
    }
}
