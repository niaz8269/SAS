<?php

namespace Tests\Concerns;

use App\Domain\Scholarship\ChangeDeadline;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipRoute;

trait BuildsScholarships
{
    /** A published cycle with one route and (optionally) a deadline. */
    protected function publishedCycle(array $attrs = [], ?array $deadline = null, ?Country $destination = null, ?DegreeLevel $degree = null): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->published()->create($attrs);
        ScholarshipRoute::create(['cycle_id' => $cycle->id, 'route_type' => 'funder_portal', 'name' => 'Portal', 'application_url' => 'https://apply.example.org/start']);
        if ($destination) {
            $cycle->destinations()->attach($destination);
        }
        if ($degree) {
            $cycle->degreeLevels()->attach($degree);
        }
        if ($deadline) {
            app(ChangeDeadline::class)($cycle, $deadline);
        }

        return $cycle->fresh(['programme']);
    }

    protected function exactDeadline(string $daysFromNow = '+20 days'): array
    {
        return ['date_type' => 'exact', 'local_closes_at' => now()->modify($daysFromNow)->format('Y-m-d').' 12:00', 'source_timezone' => 'UTC'];
    }
}
