<?php

namespace App\Console\Commands;

use App\Domain\Scholarship\DeadlineService;
use App\Enums\PublicationState;
use App\Models\ScholarshipCycle;
use Illuminate\Console\Command;

class EvaluateDeadlines extends Command
{
    protected $signature = 'sas:evaluate-deadlines';

    protected $description = 'Refresh stored application status and sort keys from current deadlines (JOB-02, every minute).';

    public function handle(DeadlineService $deadlines): int
    {
        $n = 0;
        ScholarshipCycle::query()
            ->where('publication_state', PublicationState::Published->value)
            ->where(fn ($q) => $q->where('next_closing_sort', '<=', now())->orWhereIn('application_status', ['upcoming', 'open']))
            ->with('currentDeadlines')
            ->chunkById(200, function ($cycles) use ($deadlines, &$n) {
                foreach ($cycles as $cycle) {
                    $before = $cycle->application_status;
                    $deadlines->refresh($cycle);
                    $n += $before !== $cycle->application_status ? 1 : 0;
                }
            });
        $this->info("Status changes: {$n}");

        return self::SUCCESS;
    }
}
