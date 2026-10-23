<?php

namespace App\Console\Commands;

use App\Domain\Editorial\CycleWorkflow;
use App\Enums\PublicationState;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Publishes draft cycles through the normal workflow (submit, approve, publish) on one person's recorded authority.
 * Because the same person submits and approves, the approval is an administrator override and the reason is stored in the
 * review history and the audit log. It does not check any source: the person named takes responsibility for that.
 */
class PublishDrafts extends Command
{
    protected $signature = 'sas:publish-drafts {email : staff account that authorises and performs the publication} {--reason= : required, recorded with every step} {--only=* : external_key of cycles to publish (default: all drafts)}';

    protected $description = 'Publish draft scholarship cycles on the recorded authority of one named staff member.';

    public function handle(CycleWorkflow $workflow): int
    {
        $actor = User::where('email', $this->argument('email'))->first();
        $reason = trim((string) $this->option('reason'));
        if (! $actor || ! $actor->isStaff() || ! $actor->hasPermission('content.publish') || ! $actor->hasPermission('content.override')) {
            $this->error('The account must be an active staff member who may publish and override.');

            return self::FAILURE;
        }
        if ($reason === '') {
            $this->error('A --reason is required; it is stored with every change.');

            return self::FAILURE;
        }

        $query = ScholarshipCycle::where('publication_state', PublicationState::Draft->value)->with('programme');
        if ($only = array_filter((array) $this->option('only'))) {
            $query->whereIn('external_key', $only);
        }

        $done = 0;
        $failed = [];
        foreach ($query->get() as $cycle) {
            try {
                $workflow->submitForReview($cycle, $actor);
                $workflow->approve($cycle->fresh(), $actor, 'Approved on the named person\'s authority.', $reason);
                $workflow->publish($cycle->fresh(), $actor);
                $done++;
            } catch (\Throwable $e) {
                $failed[] = ($cycle->external_key ?: $cycle->id).': '.$e->getMessage();
            }
        }
        Cache::forget('sitemap');

        $this->info("Published {$done} cycle(s).");
        foreach ($failed as $f) {
            $this->warn($f);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
