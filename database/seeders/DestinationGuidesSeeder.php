<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Destination pages with short factual pointers to the official sources that were read for the scholarship data.
 * No costs, visa or entry rules are summarised, and no review date is recorded. Only fills destinations that have no summary yet,
 * so anything staff wrote is kept. Production-safe.
 */
class DestinationGuidesSeeder extends Seeder
{
    public const GUIDES = [
        'CN' => ['Browse universities and programmes listed on the official study-in-China system, and scholarships such as the Chinese Government Scholarship (CSC).',
            [['Study in China: official university and programme listings', 'https://www.campuschina.org/'], ['Chinese Government Scholarship (CSC): official system', 'https://studyinchina.csc.edu.cn/']], true],
        'DE' => ['Search funding for study and research in Germany in the DAAD scholarship database, the official search of the German Academic Exchange Service.',
            [['DAAD scholarship database', 'https://www2.daad.de/deutschland/stipendium/datenbank/en/21148-scholarship-database/']], true],
        'TR' => ['The Turkish Government\'s Türkiye Burslari scholarships support international students at several levels of study.',
            [['Türkiye Burslari', 'https://turkiyeburslari.gov.tr/']], true],
        'GB' => ['Awards for study in the UK include Chevening, the Commonwealth Master\'s Scholarships, Gates Cambridge and the Rhodes Scholarship.',
            [['Chevening Scholarships', 'https://www.chevening.org/'], ['Commonwealth Master\'s Scholarships', 'https://cscuk.fcdo.gov.uk/scholarships/commonwealth-masters-scholarships/']], true],
        'KR' => ['The Global Korea Scholarship (GKS) funds undergraduate and graduate study in Korea; applications go through Korean embassies or universities.',
            [['Study in Korea: Global Korea Scholarship', 'https://www.studyinkorea.go.kr/ko/plan/scholarship.do?tab=gks-tab1']], false],
        'JP' => ['The Japanese Government (MEXT) Scholarship is applied for through the Japanese embassy or consulate general in your country.',
            [['Study in Japan: MEXT Scholarship, embassy recommendation', 'https://studyinjapan.go.jp/en/smap-stopj-applications-research.html']], false],
        'US' => ['The Fulbright Foreign Student Program supports graduate study and research in the United States; apply through the Fulbright office or U.S. Embassy in your home country.',
            [['Fulbright Foreign Student Program', 'https://foreign.fulbrightonline.org/']], false],
        'NZ' => ['Manaaki New Zealand Scholarships fund academic study and short-term training for students from partner countries.',
            [['Manaaki New Zealand Scholarships', 'https://www.nzscholarships.govt.nz/']], false],
        'CH' => ['The Swiss Government Excellence Scholarships support research and art study; applications go through Swiss embassies.',
            [['Swiss Government Excellence Scholarships', 'https://www.sbfi.admin.ch/sbfi/en/home/education/scholarships-and-grants/swiss-government-excellence-scholarships.html']], false],
    ];

    public function run(): void
    {
        foreach (self::GUIDES as $code => [$summary, $links, $featured]) {
            $country = Country::where('code', $code)->first();
            if (! $country || filled($country->summary)) {
                continue;
            }
            $list = collect($links)->map(fn ($l) => '<li><a href="'.e($l[1]).'">'.e($l[0]).'</a></li>')->implode('');
            $country->forceFill([
                'is_destination' => true, 'guidance_published' => true, 'featured' => $featured, 'summary' => $summary,
                'study_guidance' => '<p>This guide only points to official sources for now. Costs, visa and entry rules are not summarised here: confirm them with the official authority.</p><ul>'.$list.'</ul>',
                'guidance_sources' => collect($links)->map(fn ($l) => ['label' => $l[0], 'url' => $l[1]])->all(),
            ])->save();
        }
    }
}
