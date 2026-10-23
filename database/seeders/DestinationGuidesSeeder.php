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
        'SA' => ['Saudi universities and the Ministry of Education offer scholarships to international students; KAUST and KFUPM fund graduate study.',
            [['Ministry of Education: government university scholarships', 'https://www.moe.gov.sa/en/education/ResidentsAndvisitors/Pages/PublicUniversitiesScholarships.aspx'], ['KFUPM graduate scholarship', 'https://cgis.kfupm.edu.sa/admission/kfupm-scholarship'], ['KAUST admissions', 'https://admissions.kaust.edu.sa/info-and-contact/faqs']], false],
        'AE' => ['MBZUAI, Khalifa University and UAE University fund graduate study for international students.',
            [['MBZUAI graduate admissions', 'https://mbzuai.ac.ae/admissions/graduate-phd-admissions'], ['Khalifa University graduate scholarship', 'https://www.ku.ac.ae/scholarship/graduate-research-teaching-scholarship-for-international-students'], ['UAE University graduate research fellowships', 'https://www.uaeu.ac.ae/en/cgs/grf_int_phds.shtml']], false],
        'QA' => ['Hamad Bin Khalifa University offers graduate scholarships for international students, and Qatar University offers an undergraduate scholarship.',
            [['Hamad Bin Khalifa University scholarships', 'https://www.hbku.edu.qa/en/scholarship'], ['Qatar University scholarships', 'https://www.qu.edu.qa/en-us/students/admission/scholarships/types']], false],
        'OM' => ['Sultan Qaboos University offers a postgraduate scholarship for international students.',
            [['Sultan Qaboos University scholarships', 'https://www.squ.edu.om/ps/en-us/scholarships']], false],
        'HU' => ['Stipendium Hungaricum is the Hungarian government scholarship programme for international students.',
            [['Stipendium Hungaricum', 'https://stipendiumhungaricum.hu/']], false],
        'PL' => ['The Banach scholarship programme of the Polish National Agency for Academic Exchange (NAWA) supports international students.',
            [['NAWA: Banach Scholarship Programme', 'https://nawa.gov.pl/en/students/foreign-students/the-banach-scholarship-programme']], false],
        'CZ' => ['The Czech Ministry of Education offers government scholarships for students from developing countries.',
            [['Ministry of Education: government scholarships', 'https://msmt.gov.cz/en/scholarships/government-scholarships-developing-countries']], false],
        'IT' => ['The Italian Ministry of Foreign Affairs (MAECI) offers government scholarships to foreign students.',
            [['Study in Italy: calls for scholarships', 'https://studyinitaly.esteri.it/ListaBandi']], false],
        'FR' => ['The Eiffel Excellence Scholarship Programme funds master\'s and PhD study in France; institutions nominate candidates.',
            [['Campus France: Eiffel Scholarship Programme', 'https://www.campusfrance.org/en/eiffel-scholarship-program-of-excellence']], false],
        'NL' => ['The NL Scholarship (formerly the Holland Scholarship) supports international students at Dutch institutions.',
            [['Study in NL: NL Scholarship', 'https://www.studyinnl.org/finances/holland-scholarship']], false],
        'AT' => ['The Ernst Mach Grant of the OeAD supports international students and researchers in Austria.',
            [['OeAD: Ernst Mach Grant', 'https://oead.at/en/study-research-teaching/overview-grants-and-scholarships/ernst-mach-grant']], false],
        'SE' => ['The Swedish Institute funds master\'s study in Sweden for global professionals.',
            [['Swedish Institute: Scholarship for Global Professionals', 'https://si.se/en/apply/scholarships/si-scholarship-for-global-professionals/']], false],
        'RU' => ['The Russian Government offers quota scholarships to foreign citizens.',
            [['Education in Russia: admission process', 'https://education-in-russia.com/how-to-proceed/admission-process']], false],
        'ES' => ['The Spanish agency AECID offers scholarships to foreign citizens, such as the Africa-Med master programme.',
            [['Official State Gazette: AECID call', 'https://www.boe.es/buscar/doc.php?id=BOE-B-2026-22411']], false],
        'KZ' => ['Kazakhstan funds international students through its government scholarship for foreign citizens, Nazarbayev University and KIMEP grants.',
            [['Nazarbayev University: international admission', 'https://nu.edu.kz/admissions/international-admission/'], ['KIMEP University: scholarships for new students', 'https://www.kimep.kz/prospective-students/en/financial-aid/scholarships-for-newly-admitted-students/?degree=ugrad']], false],
        'UZ' => ['The ICESCO-Uzbekistan scholarship programme supports students from ICESCO member states.',
            [['ICESCO: scholarship programme announcement', 'https://icesco.org/en/2026/03/27/icesco-and-uzbekistan-launch-scholarship-programme-for-outstanding-students-in-the-islamic-world/']], false],
        'AZ' => ['The Heydar Aliyev International Education Grant supports international students in Azerbaijan.',
            [['Study in Azerbaijan: grant call', 'https://studyinazerbaijan.edu.az/H.Aliyev_IEG_CALL_18.04.pdf']], false],
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
