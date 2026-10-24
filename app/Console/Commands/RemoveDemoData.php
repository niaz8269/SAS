<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Country;
use App\Models\Funder;
use App\Models\PartnerOrganisation;
use App\Models\ScholarshipProgramme;
use App\Models\University;
use App\Models\User;
use Database\Seeders\DestinationGuidesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the fictional records used while building and testing the site: anything named "[DEMO]" or "Demo ...", the demo partner scholarship,
 * the demo cost line on two destinations, and (with --with-accounts) the demo agency and every @example.test login with what belongs to it.
 * Placeholder destination text is replaced by factual pointers to official sources.
 */
class RemoveDemoData extends Command
{
    protected $signature = 'sas:remove-demo-data {--force : run in production} {--with-accounts : also delete the demo agency and the @example.test logins}';

    protected $description = 'Delete demo scholarships, funders, universities and articles, the demo cost lines, and (with --with-accounts) the demo agency and @example.test logins.';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $demoName = fn ($query, string $column) => $query->where($column, 'like', '[DEMO]%')->orWhere($column, 'like', 'Demo %');
        $counts = [];
        DB::transaction(function () use (&$counts, $demoName) {
            $counts['scholarships'] = 0;
            $programmes = ScholarshipProgramme::where(fn ($q) => $demoName($q, 'title'))
                ->orWhereHas('cycles', fn ($c) => $c->where('external_key', 'like', 'partner-%'))->get();
            foreach ($programmes as $p) {
                $p->cycles()->each(fn ($c) => $c->delete());
                $p->delete();
                $counts['scholarships']++;
            }
            $counts['funders'] = Funder::where(fn ($q) => $demoName($q, 'name'))->delete();
            $counts['universities'] = University::where(fn ($q) => $demoName($q, 'name'))->delete();
            $counts['articles'] = Article::where(fn ($q) => $demoName($q, 'title'))->delete();
            $counts['cost_lines'] = Country::where('cost_notes', 'like', '%Not real%')->update(['cost_notes' => null]);

            $counts['accounts'] = $counts['agencies'] = 0;
            if ($this->option('with-accounts')) {
                $orgs = PartnerOrganisation::where(fn ($q) => $demoName($q, 'name'))->orWhere('contact_email', 'like', '%@example.test')->pluck('id');
                $users = User::where('email', 'like', '%@example.test')->orWhereIn('partner_organisation_id', $orgs)->orWhereIn('managed_by_organisation_id', $orgs)->get();
                foreach ($users as $u) {
                    $u->userRoles()->delete();
                    $u->delete();
                    $counts['accounts']++;
                }
                $counts['agencies'] = PartnerOrganisation::whereIn('id', $orgs)->delete();
                // what the removed accounts leave behind: their notifications, and the import history of the demo partner scholarship
                DB::table('notifications')->where('notifiable_type', User::class)->whereNotIn('notifiable_id', User::query()->select('id'))->delete();
                DB::table('import_rows')->where('payload', 'like', '%partner-%')->delete();
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
