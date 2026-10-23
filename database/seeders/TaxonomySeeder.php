<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Subject;
use App\Models\SubjectAlias;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * PRODUCTION-SAFE reference data (ISO country codes, degree levels, subject taxonomy). Contains no scholarship data.
 * Idempotent: safe to run on every deploy.
 */
class TaxonomySeeder extends Seeder
{
    /** ISO 3166-1 alpha-2 | name | demonym. A starter set; editors extend it in the admin. */
    private const COUNTRIES = [
        'AF|Afghanistan|Afghan', 'AL|Albania|Albanian', 'DZ|Algeria|Algerian', 'AR|Argentina|Argentine', 'AU|Australia|Australian',
        'AT|Austria|Austrian', 'BH|Bahrain|Bahraini', 'BD|Bangladesh|Bangladeshi', 'BE|Belgium|Belgian', 'BR|Brazil|Brazilian',
        'CA|Canada|Canadian', 'CN|China|Chinese', 'CO|Colombia|Colombian', 'CZ|Czechia|Czech', 'DK|Denmark|Danish',
        'EG|Egypt|Egyptian', 'ET|Ethiopia|Ethiopian', 'FI|Finland|Finnish', 'FR|France|French', 'DE|Germany|German',
        'GH|Ghana|Ghanaian', 'GR|Greece|Greek', 'HU|Hungary|Hungarian', 'IN|India|Indian', 'ID|Indonesia|Indonesian',
        'IR|Iran|Iranian', 'IQ|Iraq|Iraqi', 'IE|Ireland|Irish', 'IT|Italy|Italian', 'JP|Japan|Japanese', 'JO|Jordan|Jordanian',
        'KZ|Kazakhstan|Kazakhstani', 'KE|Kenya|Kenyan', 'KW|Kuwait|Kuwaiti', 'LB|Lebanon|Lebanese', 'LY|Libya|Libyan',
        'MY|Malaysia|Malaysian', 'MX|Mexico|Mexican', 'MA|Morocco|Moroccan', 'NP|Nepal|Nepali', 'NL|Netherlands|Dutch',
        'NZ|New Zealand|New Zealander', 'NG|Nigeria|Nigerian', 'NO|Norway|Norwegian', 'OM|Oman|Omani', 'PK|Pakistan|Pakistani',
        'PS|Palestine|Palestinian', 'PH|Philippines|Filipino', 'PL|Poland|Polish', 'PT|Portugal|Portuguese', 'QA|Qatar|Qatari',
        'RU|Russia|Russian', 'SA|Saudi Arabia|Saudi', 'SN|Senegal|Senegalese', 'SG|Singapore|Singaporean', 'ZA|South Africa|South African',
        'KR|South Korea|South Korean', 'ES|Spain|Spanish', 'LK|Sri Lanka|Sri Lankan', 'SD|Sudan|Sudanese', 'SE|Sweden|Swedish',
        'CH|Switzerland|Swiss', 'SY|Syria|Syrian', 'TZ|Tanzania|Tanzanian', 'TH|Thailand|Thai', 'TN|Tunisia|Tunisian',
        'TR|Türkiye|Turkish', 'UG|Uganda|Ugandan', 'UA|Ukraine|Ukrainian', 'AE|United Arab Emirates|Emirati',
        'GB|United Kingdom|British', 'US|United States|American', 'UZ|Uzbekistan|Uzbek', 'VN|Vietnam|Vietnamese', 'YE|Yemen|Yemeni', 'ZW|Zimbabwe|Zimbabwean',
    ];

    private const DEGREES = [
        ['bachelor', 'Bachelor', 10], ['master', "Master's", 20], ['doctorate', 'PhD / Doctorate', 30],
        ['postdoctoral', 'Postdoctoral', 40], ['diploma', 'Diploma / Certificate', 50],
    ];

    /** name => aliases (DIR-02 keyword search). */
    private const SUBJECTS = [
        'Engineering' => ['engineering', 'civil engineering', 'mechanical engineering', 'electrical engineering'],
        'Computer Science' => ['computing', 'software', 'informatics', 'AI', 'data science'],
        'Medicine and Health' => ['medicine', 'nursing', 'public health', 'pharmacy', 'dentistry'],
        'Business and Economics' => ['business', 'management', 'MBA', 'finance', 'economics'],
        'Natural Sciences' => ['physics', 'chemistry', 'biology', 'mathematics'],
        'Social Sciences' => ['sociology', 'psychology', 'political science', 'international relations', 'education'],
        'Humanities and Arts' => ['history', 'languages', 'literature', 'philosophy', 'arts', 'design'],
        'Law' => ['law', 'legal studies'],
        'Agriculture and Environment' => ['agriculture', 'environment', 'sustainability', 'forestry'],
    ];

    /** Every ISO 3166-1 alpha-2 code, so a scholarship can name any nationality or host country. Names come from the intl extension. */
    private const ALL_CODES = 'AD AE AF AG AI AL AM AO AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS XK YE YT ZA ZM ZW';

    public function run(): void
    {
        foreach (self::COUNTRIES as $row) {
            [$code, $name, $demonym] = explode('|', $row);
            Country::firstOrCreate(['code' => $code], ['name' => $name, 'slug' => Str::slug($name), 'demonym' => $demonym]);
        }
        foreach (explode(' ', self::ALL_CODES) as $code) {
            if (Country::where('code', $code)->exists()) {
                continue;
            }
            $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, 'en') : $code;
            $name = ($name === '' || $name === $code) ? $code : $name;
            $slug = Str::slug($name);
            Country::firstOrCreate(['code' => $code], ['name' => $name, 'slug' => Country::where('slug', $slug)->exists() ? $slug.'-'.strtolower($code) : $slug]);
        }
        foreach (self::DEGREES as [$code, $name, $sort]) {
            DegreeLevel::firstOrCreate(['code' => $code], ['name' => $name, 'slug' => Str::slug($code), 'sort' => $sort]);
        }
        foreach (self::SUBJECTS as $name => $aliases) {
            $subject = Subject::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
            foreach ($aliases as $alias) {
                SubjectAlias::firstOrCreate(['subject_id' => $subject->id, 'alias' => $alias]);
            }
        }
    }
}
