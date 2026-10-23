<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Country;
use App\Models\Funder;
use App\Models\ScholarshipProgramme;
use App\Models\University;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Removes the fictional "[DEMO]" records and demo accounts, and replaces placeholder destination text with factual pointers to official sources. */
class RemoveDemoData extends Command
{
    protected $signature = 'sas:remove-demo-data {--force : do not ask for confirmation}';

    protected $description = 'Delete [DEMO] scholarships, funders, universities, articles and @example.test accounts; replace placeholder destination text.';

    /** Factual starting points built only from official pages that were read for the seed data. */
    private const DESTINATIONS = [
        'CN' => ['Browse universities and programmes listed on the official study-in-China system, and scholarships such as the Chinese Government Scholarship (CSC).',
            [['Study in China: official university and programme listings', 'https://www.campuschina.org/'], ['Chinese Government Scholarship (CSC): official system', 'https://studyinchina.csc.edu.cn/']]],
        'DE' => ['Search funding for study and research in Germany in the DAAD scholarship database, the official search of the German Academic Exchange Service.',
            [['DAAD scholarship database', 'https://www2.daad.de/deutschland/stipendium/datenbank/en/21148-scholarship-database/']]],
        'TR' => ['The Turkish Government\'s Türkiye Burslari scholarships support international students at several levels of study.',
            [['Türkiye Burslari', 'https://turkiyeburslari.gov.tr/']]],
        'GB' => ['Awards for study in the UK include Chevening, the Commonwealth Master\'s Scholarships, Gates Cambridge and the Rhodes Scholarship.',
            [['Chevening Scholarships', 'https://www.chevening.org/'], ['Commonwealth Master\'s Scholarships', 'https://cscuk.fcdo.gov.uk/scholarships/commonwealth-masters-scholarships/']]],
    ];

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
            foreach (User::where('email', 'like', '%@example.test')->get() as $u) {
                $u->userRoles()->delete();
                $u->delete();
                $counts['accounts']++;
            }

            $counts['destinations'] = 0;
            foreach (self::DESTINATIONS as $code => [$summary, $links]) {
                $country = Country::where('code', $code)->first();
                if ($country && str_contains((string) $country->summary.(string) $country->study_guidance, '[DEMO]')) {
                    $list = collect($links)->map(fn ($l) => '<li><a href="'.e($l[1]).'">'.e($l[0]).'</a></li>')->implode('');
                    $country->forceFill([
                        'summary' => $summary,
                        'study_guidance' => '<p>This guide only points to official sources for now. Costs, visa and entry rules are not summarised here: confirm them with the official authority.</p><ul>'.$list.'</ul>',
                        'guidance_sources' => collect($links)->map(fn ($l) => ['label' => $l[0], 'url' => $l[1]])->all(),
                    ])->save();
                    $counts['destinations']++;
                }
            }
        });

        foreach ($counts as $what => $n) {
            $this->line(sprintf('%-14s %d', $what, $n));
        }
        $this->info('Demo data removed.');

        return self::SUCCESS;
    }
}
