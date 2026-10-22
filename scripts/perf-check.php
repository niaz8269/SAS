<?php

// NFR-02 style check: 10k programmes / 50k cycles on a SCRATCH MySQL/MariaDB database named sas_perf (it is wiped by seed).
// usage: php scripts/perf-check.php seed   (once)   then   php scripts/perf-check.php run
// Edit the DB_* values below for your server. Never point this at a real database.
putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_DATABASE=sas_perf');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = 'sas_perf';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = '';
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Domain\Scholarship\ScholarshipSearch;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

$mode = $argv[1] ?? 'run';
if ($mode === 'seed') {
    Artisan::call('migrate:fresh', ['--force' => true]);
    Artisan::call('db:seed', ['--class' => 'TaxonomySeeder', '--force' => true]);
    mt_srand(42);
    $countries = DB::table('countries')->pluck('id')->all();
    $degrees = DB::table('degree_levels')->pluck('id')->all();
    $subjects = DB::table('subjects')->pluck('id')->all();
    DB::table('countries')->whereIn('id', array_slice($countries, 0, 20))->update(['is_destination' => 1]);
    $words = ['Global', 'Future', 'Leaders', 'Excellence', 'Merit', 'Research', 'Innovation', 'Heritage', 'Pioneer', 'Horizon', 'Scholars', 'Fellowship', 'Talent', 'Bridge', 'Quantum'];
    $funders = [];
    for ($i = 1; $i <= 300; $i++) {
        $funders[] = ['name' => "Funder $i", 'slug' => "funder-$i", 'type' => 'foundation', 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('funders')->insert($funders);
    $unis = [];
    for ($i = 1; $i <= 800; $i++) {
        $unis[] = ['name' => 'University of '.$words[$i % 15].' '.$i, 'slug' => "uni-$i", 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('universities')->insert($unis);
    $uniIds = DB::table('universities')->pluck('id')->all();

    $progs = [];
    for ($p = 1; $p <= 10000; $p++) {
        $progs[] = ['title' => $words[$p % 15].' '.$words[($p * 7) % 15].' Scholarship '.$p, 'slug' => "prog-$p", 'funder_id' => 1 + ($p % 300), 'host_country_id' => $countries[$p % 20], 'summary' => 'Demo summary '.$p, 'created_at' => now(), 'updated_at' => now()];
        if (count($progs) === 1000) {
            DB::table('scholarship_programmes')->insert($progs);
            $progs = [];
        }
    }
    $cycleRows = [];
    $meta = [];
    $id = 0;
    for ($p = 1; $p <= 10000; $p++) {
        for ($y = 0; $y < 5; $y++) {
            $id++;
            $closes = now()->addDays(mt_rand(-200, 300));
            $status = $closes->isPast() ? 'closed' : 'open';
            $cycleRows[] = ['id' => $id, 'programme_id' => $p, 'cycle_key' => (string) (2026 + $y), 'slug' => (string) (2026 + $y), 'intake_year' => 2026 + $y,
                'publication_state' => $id % 20 === 0 ? 'draft' : 'published', 'application_status' => $status, 'next_closing_sort' => $closes->isPast() ? null : $closes,
                'funding_type' => ['full', 'partial', 'tuition_only', 'unknown'][$id % 4], 'fully_funded_verified' => $id % 8 === 0, 'teaching_language' => ['English', 'Chinese', 'German'][$id % 3],
                'delivery_mode' => 'on_campus', 'any_nationality' => $id % 3 === 0, 'summary' => 'Demo cycle '.$id, 'featured' => $id % 500 === 0, 'published_at' => now(),
                'last_verified_at' => now(), 'review_due_at' => now()->addDays(20), 'created_at' => now(), 'updated_at' => now()];
            $meta[$id] = $closes;
            if (count($cycleRows) === 1000) {
                DB::table('scholarship_cycles')->insert($cycleRows);
                $cycleRows = [];
            }
        }
    }
    DB::table('scholarship_cycles')->insert($cycleRows);
    echo 'cycles: '.DB::table('scholarship_cycles')->count()."\n";
    $dest = $deg = $subj = $dl = [];
    foreach ($meta as $cid => $closes) {
        $dest[] = ['cycle_id' => $cid, 'country_id' => $countries[$cid % 20]];
        $deg[] = ['cycle_id' => $cid, 'degree_level_id' => $degrees[$cid % count($degrees)]];
        $subj[] = ['cycle_id' => $cid, 'subject_id' => $subjects[$cid % count($subjects)]];
        $dl[] = ['cycle_id' => $cid, 'date_type' => 'exact', 'precision' => 'minute', 'source_timezone' => 'UTC', 'closes_at_utc' => $closes, 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
        if (count($dest) === 2000) {
            DB::table('cycle_destinations')->insert($dest);
            DB::table('cycle_degree_levels')->insert($deg);
            DB::table('cycle_subjects')->insert($subj);
            DB::table('cycle_deadlines')->insert($dl);
            $dest = $deg = $subj = $dl = [];
        }
    }
    foreach ([['cycle_destinations', $dest], ['cycle_degree_levels', $deg], ['cycle_subjects', $subj], ['cycle_deadlines', $dl]] as [$t, $rows]) {
        if ($rows) {
            DB::table($t)->insert($rows);
        }
    }
    echo "seeded\n";
    exit;
}

$search = app(ScholarshipSearch::class);
$destSlug = DB::table('countries')->where('is_destination', 1)->value('slug');
$degSlug = DB::table('degree_levels')->where('code', 'master')->value('slug');
$queries = [
    'default listing' => [],
    'destination+degree+full' => ['destination' => [$destSlug], 'degree' => [$degSlug], 'funding' => ['full']],
    'keyword title' => ['q' => 'Quantum Pioneer'],
    'keyword + filters' => ['q' => 'Global', 'destination' => [$destSlug]],
    'nationality filter' => ['nationality' => ['pakistan']],
    'sort closing' => ['sort' => 'closing'],
    'deadline range' => ['deadline_from' => now()->toDateString(), 'deadline_to' => now()->addDays(30)->toDateString()],
    'page 50 closing' => ['sort' => 'closing', 'page' => 50],
];
foreach ($queries as $name => $filters) {
    $times = [];
    for ($i = 0; $i < 25; $i++) {
        $t = microtime(true);
        $page = $filters['page'] ?? 1;
        Paginator::currentPageResolver(fn () => $page);
        $r = $search->paginate($filters, 12);
        $r->items();
        $times[] = (microtime(true) - $t) * 1000;
    }
    sort($times);
    printf("%-26s total=%-6d median=%6.0f ms  p95=%6.0f ms  queries/page=%d\n", $name, $r->total(), $times[12], $times[23], 0);
}
