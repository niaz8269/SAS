<?php

namespace App\Console\Commands;

use App\Domain\Seo\Sitemap;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Tells IndexNow search engines (Bing, Yandex, Seznam, Naver and others) which public pages are new or changed, so they are crawled within hours
 * instead of weeks. Bing's index is the one ChatGPT search and Copilot read. The list comes from the sitemap; by default only pages changed in the
 * last few days are sent, --all sends every page. Only the live site sends (a copy on a laptop would announce the wrong addresses).
 */
class IndexNow extends Command
{
    protected $signature = 'sas:indexnow {--all : send every page in the sitemap} {--days=2 : pages changed in this many days} {--dry-run : list, send nothing}';

    protected $description = 'Send new and changed public pages to IndexNow (Bing and others)';

    public function handle(Sitemap $sitemap): int
    {
        $base = rtrim((string) config('app.url'), '/');
        $host = (string) parse_url($base, PHP_URL_HOST);
        $key = (string) config('sas.indexnow_key');
        if ($key === '' || ! is_file(public_path($key.'.txt'))) {
            $this->error('No IndexNow key file in public/.');

            return self::FAILURE;
        }

        $since = Carbon::now()->subDays(max(1, (int) $this->option('days')));
        $urls = collect(['pages', 'scholarships', 'universities', 'destinations', 'blog'])
            ->flatMap(fn ($section) => $sitemap->entries($section))
            ->filter(fn ($e) => $this->option('all') || ($e[1] !== null && Carbon::parse($e[1])->greaterThanOrEqualTo($since)))
            ->map(fn ($e) => $e[0])->filter(fn ($u) => str_starts_with($u, $base))->unique()->values();

        $this->info($urls->count().' page(s) to send.');
        if ($urls->isEmpty() || $this->option('dry-run')) {
            return self::SUCCESS;
        }
        if (! app()->isProduction() || ! str_starts_with($base, 'https://')) {
            $this->warn('Not the live site: nothing sent.');

            return self::SUCCESS;
        }

        foreach ($urls->chunk(10000) as $chunk) {
            $response = Http::timeout(30)->acceptJson()->post('https://api.indexnow.org/indexnow', [
                'host' => $host, 'key' => $key, 'keyLocation' => $base.'/'.$key.'.txt', 'urlList' => $chunk->values()->all(),
            ]);
            $this->line('IndexNow answered '.$response->status().' for '.$chunk->count().' page(s).');
            if ($response->failed() && $response->status() !== 422) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
