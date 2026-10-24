<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ImportBlogPosts extends Command
{
    protected $signature = 'sas:import-blog-posts {file=data/blog/launch-posts.json : JSON file with the posts} {--update : replace posts that already exist (same web address)}';

    protected $description = 'Add blog posts from a JSON file. A post that already exists is left alone unless --update is given. A post is public only if its entry says "published": true.';

    public function handle(): int
    {
        if (! is_file($this->argument('file'))) {
            $this->error('No such file: '.$this->argument('file'));

            return self::FAILURE;
        }
        $posts = json_decode((string) file_get_contents($this->argument('file')), true);
        if (! is_array($posts)) {
            $this->error('The file is not valid JSON.');

            return self::FAILURE;
        }

        $added = $updated = $skipped = 0;
        foreach ($posts as $i => $p) {
            if (blank($p['body'] ?? null) && filled($p['body_file'] ?? null)) { // the text of a post can sit in its own HTML file next to the list
                $path = dirname($this->argument('file')).'/'.$p['body_file'];
                $p['body'] = is_file($path) ? (string) file_get_contents($path) : null;
            }
            foreach (['slug', 'title', 'body'] as $required) {
                if (blank($p[$required] ?? null)) {
                    $this->error('Entry '.($i + 1)." has no $required.");

                    return self::FAILURE;
                }
            }
            $category = filled($p['category'] ?? null) ? BlogCategory::firstOrCreate(['slug' => Str::slug($p['category'])], ['name' => $p['category'], 'sort' => 10]) : null;
            $values = [
                'title' => $p['title'], 'category_id' => $category?->id, 'excerpt' => $p['excerpt'] ?? null, 'body' => $p['body'],
                'image_path' => $p['image'] ?? null, 'image_alt' => $p['image_alt'] ?? null, 'image_credit' => $p['image_credit'] ?? null,
                'seo_title' => $p['seo_title'] ?? null, 'seo_description' => $p['seo_description'] ?? null, 'featured' => (bool) ($p['featured'] ?? false),
                'status' => ($p['published'] ?? false) ? 'published' : 'draft', 'published_at' => ($p['published'] ?? false) ? Carbon::parse($p['published_at'] ?? 'now') : null,
            ];
            $existing = BlogPost::firstWhere('slug', $p['slug']);
            if ($existing && ! $this->option('update')) {
                $skipped++;

                continue;
            }
            if ($existing) {
                $existing->update($values);
                $updated++;
            } else {
                BlogPost::create($values + ['slug' => $p['slug']]);
                $added++;
            }
        }
        AuditLog::record('blog.imported', null, null, ['added' => $added, 'updated' => $updated, 'skipped' => $skipped]);
        $this->info("Blog posts: $added added, $updated updated, $skipped left as they were.");

        return self::SUCCESS;
    }
}
