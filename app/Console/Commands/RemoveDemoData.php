<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Country;
use App\Models\Funder;
use App\Models\ScholarshipProgramme;
use App\Models\University;
use App\Models\User;
use Database\Seeders\DestinationGuidesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Removes the fictional "[DEMO]" records and demo accounts, and replaces placeholder destination text with factual pointers to official sources. */
class RemoveDemoData extends Command
{
    protected $signature = 'sas:remove-demo-data {--force : run in production} {--with-accounts : also delete the @example.test demo logins}';

    protected $description = 'Delete [DEMO] scholarships, funders, universities and articles (and, with --with-accounts, the @example.test demo logins); replace placeholder destination text.';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $counts = [];
        DB::transaction(function () use (&$counts) {
            $counts['scholarships'] = 0;
            foreach (ScholarshipProgramme::where('title', 'like', '[DEMO]%')->get() as $p) {
                $p->cycles()->each(fn ($c) => $c->delete());
                $p->delete();
                $counts['scholarships']++;
            }
            $counts['funders'] = Funder::where('name', 'like', '[DEMO]%')->delete();
            $counts['universities'] = University::where('name', 'like', '[DEMO]%')->delete();
            $counts['articles'] = Article::where('title', 'like', '[DEMO]%')->delete();

            $counts['accounts'] = 0;
            if ($this->option('with-accounts')) {
                foreach (User::where('email', 'like', '%@example.test')->get() as $u) {
                    $u->userRoles()->delete();
                    $u->delete();
                    $counts['accounts']++;
                }
            }

            // Placeholder destination text is cleared, then the real pointers are filled in by the seeder.
            $counts['destinations'] = Country::where('summary', 'like', '%DEMO%')->orWhere('study_guidance', 'like', '%DEMO%')->update(['summary' => null, 'study_guidance' => null, 'guidance_sources' => null]);
            (new DestinationGuidesSeeder)->run();
        });

        foreach ($counts as $what => $n) {
            $this->line(sprintf('%-14s %d', $what, $n));
        }
        $this->info('Demo data removed.');

        return self::SUCCESS;
    }
}
