<?php

namespace App\Console\Commands;

use App\Models\University;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Finds a real photograph of each university's campus on Wikipedia/Wikimedia Commons (via Wikidata's "image" property, then the
 * article's lead image), keeps it only if its licence allows reuse with credit, crops it to 960x540 and stores it under
 * public/images/universities with the photographer and licence in data/seed/university-photos.json (shown on /image-credits).
 * Logos, seals, maps and non-free or non-commercial images are never used. Universities without a match keep their city photo.
 */
class FetchUniversityPhotos extends Command
{
    protected $signature = 'sas:fetch-university-photos {--limit=0 : stop after this many universities} {--only= : slug or part of a name} {--force : redo universities that already have a photo} {--dry-run} {--shard= : k/n, process only universities whose id % n == k (lets several runs share the work)}';

    protected $description = 'Fetch openly licensed campus photographs for universities from Wikimedia Commons.';

    private const API = 'https://en.wikipedia.org/w/api.php';

    private const BAD_FILE = '/logo|seal|emblem|badge|crest|\bmap\b|flag|coat[_ ]of|signature|diagram|\.svg|\.gif|\.pdf|\.tif|portrait|poster|plaque|banner/i';

    public function handle(): int
    {
        $path = base_path('data/seed/university-photos.json');
        $credits = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $http = Http::withUserAgent('SAS-scholarships-site/1.0 (webai.auto@gmail.com)')->timeout(40)->retry(2, 800, throw: false);
        $done = 0;
        $found = 0;
        $query = University::query()->orderBy('id');
        if ($only = $this->option('only')) {
            $query->where(fn ($q) => $q->where('slug', $only)->orWhere('name', 'like', "%{$only}%"));
        }
        @mkdir(public_path('images/universities'), 0775, true);

        [$shard, $of] = array_map('intval', explode('/', (string) ($this->option('shard') ?: '0/1')) + [0, 1]);
        foreach ($query->cursor() as $u) {
            if ($of > 1 && $u->id % $of !== $shard) {
                continue;
            }
            $file = public_path("images/universities/{$u->slug}.jpg");
            if (! $this->option('force') && (isset($credits[$u->slug]) || is_file($file))) {
                continue;
            }
            if ($this->option('limit') && $done >= (int) $this->option('limit')) {
                break;
            }
            $done++;
            $name = $u->name_en_official ?: $u->name;
            try {
                $hit = $this->find($http, $name, $u);
                $photo = $hit ? $this->pickPhoto($http, $hit) : null;
            } catch (\Throwable $e) {
                $this->warn("{$name}: {$e->getMessage()}");

                continue;
            }
            if (! $photo) {
                $this->line("-  {$name}: no openly licensed campus photo");

                continue;
            }
            $found++;
            $this->info("OK {$name}: {$photo['title']} ({$photo['license']})");
            if ($this->option('dry-run')) {
                continue;
            }
            $bytes = $http->get($photo['thumb'])->body();
            if (! $this->saveCropped($bytes, $file)) {
                $this->warn("   could not process the image for {$name}");

                continue;
            }
            $this->record($path, $u->slug, ['subject' => $name, 'artist' => $photo['artist'], 'license' => $photo['license'], 'url' => $photo['page'], 'article' => $hit['title']]);
            usleep(250000);
        }
        $this->info("Checked {$done} universities, found {$found} photographs.");

        return self::SUCCESS;
    }

    /** Adds one credit to the shared file under a lock, so several runs can work at the same time. */
    private function record(string $path, string $slug, array $credit): void
    {
        $h = fopen($path, 'c+');
        flock($h, LOCK_EX);
        $all = json_decode((string) stream_get_contents($h), true) ?: [];
        $all[$slug] = $credit;
        ksort($all);
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        flock($h, LOCK_UN);
        fclose($h);
    }

    /** The Wikipedia article for the university , only when the title is close to its name and the text is about the right country. */
    private function find(PendingRequest $http, string $name, University $u): ?array
    {
        $r = $http->get(self::API, ['action' => 'query', 'format' => 'json', 'generator' => 'search', 'gsrsearch' => $name, 'gsrlimit' => 4, 'prop' => 'pageprops|extracts|pageimages',
            'ppprop' => 'wikibase_item', 'exintro' => 1, 'explaintext' => 1, 'exchars' => 500, 'exlimit' => 4, 'piprop' => 'name', 'redirects' => 1])->json('query.pages') ?? [];
        usort($r, fn ($a, $b) => ($a['index'] ?? 9) <=> ($b['index'] ?? 9));
        $country = $u->country?->name ?? 'China';
        foreach ($r as $p) {
            if ($this->similar($p['title'], $name) < 0.8) {
                continue;
            }
            if (! preg_match('/'.preg_quote($country, '/').'|Chinese|PRC/i', $p['extract'] ?? '')) {
                continue;
            }
            if (! preg_match('/universit|college|institute|academy|school/i', $p['extract'] ?? '')) {
                continue;
            }

            return ['title' => $p['title'], 'qid' => $p['pageprops']['wikibase_item'] ?? null, 'pageimage' => $p['pageimage'] ?? null];
        }

        return null;
    }

    private function similar(string $a, string $b): float
    {
        $n = fn ($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(preg_replace('/\(.*?\)/', '', Str::ascii($s)))));
        similar_text($n($a), $n($b), $pct);

        return $pct / 100;
    }

    private function pickPhoto(PendingRequest $http, array $hit): ?array
    {
        $files = [];
        if ($hit['qid']) {
            $claims = $http->get('https://www.wikidata.org/w/api.php', ['action' => 'wbgetentities', 'format' => 'json', 'ids' => $hit['qid'], 'props' => 'claims'])->json("entities.{$hit['qid']}.claims.P18") ?? [];
            usort($claims, fn ($a, $b) => ($b['rank'] === 'preferred') <=> ($a['rank'] === 'preferred'));
            foreach ($claims as $c) {
                if ($v = $c['mainsnak']['datavalue']['value'] ?? null) {
                    $files[] = $v;
                }
            }
        }
        if ($hit['pageimage']) {
            $files[] = $hit['pageimage'];
        }
        foreach (array_unique($files) as $f) {
            if (preg_match(self::BAD_FILE, $f)) {
                continue;
            }
            $info = $http->get(self::API, ['action' => 'query', 'format' => 'json', 'titles' => 'File:'.str_replace('_', ' ', $f), 'prop' => 'imageinfo',
                'iiprop' => 'url|size|mime|extmetadata', 'iiurlwidth' => 1280])->json('query.pages');
            $ii = $info ? (array_values($info)[0]['imageinfo'][0] ?? null) : null;
            if (! $ii || ! in_array($ii['mime'] ?? '', ['image/jpeg', 'image/png'], true) || ($ii['width'] ?? 0) < 900 || ($ii['height'] ?? 0) < 480) {
                continue;
            }
            $ratio = $ii['width'] / max(1, $ii['height']);
            if ($ratio < 1.15 || $ratio > 3.2) {
                continue;
            }
            $m = $ii['extmetadata'] ?? [];
            $license = trim(strip_tags($m['LicenseShortName']['value'] ?? ''));
            $nonFree = strtolower($m['NonFree']['value'] ?? 'false') === 'true';
            if ($nonFree || ! preg_match('/^(CC0|Public domain|PD|CC BY(-SA)? [0-9.]+|CC BY(-SA)?$|Attribution)/i', $license) || preg_match('/NC|ND|fair use/i', $license)) {
                continue;
            }
            $artist = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m['Artist']['value'] ?? ''))));

            return ['title' => $f, 'license' => $license, 'artist' => Str::limit($artist ?: 'Wikimedia Commons contributor', 120, ''), 'page' => $ii['descriptionurl'], 'thumb' => $ii['thumburl'] ?? $ii['url']];
        }

        return null;
    }

    /** Centre-crop to 16:9 and resize to exactly 960x540 so every cover has the same width and height. */
    private function saveCropped(string $bytes, string $file): bool
    {
        $im = @imagecreatefromstring($bytes);
        if (! $im) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $target = 16 / 9;
        if ($w / $h > $target) {
            $cw = (int) round($h * $target);
            $ch = $h;
            $cx = (int) round(($w - $cw) / 2);
            $cy = 0;
        } else {
            $cw = $w;
            $ch = (int) round($w / $target);
            $cx = 0;
            $cy = (int) round(($h - $ch) * 0.4);
        }
        $out = imagecreatetruecolor(960, 540);
        imagecopyresampled($out, $im, 0, 0, $cx, $cy, 960, 540, $cw, $ch);

        return imagejpeg($out, $file, 72);
    }
}
