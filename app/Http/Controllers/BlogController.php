<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    public function index(Request $request, ?BlogCategory $category = null)
    {
        $data = $request->validate(['q' => 'nullable|string|max:80', 'page' => 'nullable|integer|min:1']);
        $q = trim((string) ($data['q'] ?? ''));
        $plain = $q === '' && ! $category && (int) ($data['page'] ?? 1) === 1;

        // The newest featured post is shown large at the top of the first, unfiltered page.
        $featured = $plain ? BlogPost::published()->where('featured', true)->with(['category', 'author'])->first() : null;

        $posts = BlogPost::published()->with(['category', 'author'])
            ->when($featured, fn ($query) => $query->where('id', '!=', $featured->id))
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
                $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body', 'like', $like));
            })
            ->paginate(9)->withQueryString();

        return view('public.blog.index', [
            'posts' => $posts, 'featured' => $featured, 'category' => $category, 'q' => $q,
            'categories' => BlogCategory::query()->withCount(['posts as live_posts_count' => fn ($p) => $p->where('status', 'published')->where('published_at', '<=', now())])
                ->orderBy('sort')->orderBy('name')->get()->filter(fn ($c) => $c->live_posts_count > 0)->values(),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $post = BlogPost::with(['category', 'author'])->where('slug', $slug)->firstOrFail();

        // Staff who manage the blog can open a draft or a scheduled post to see how it will look; nobody else can.
        $preview = ! $post->isLive();
        abort_unless(! $preview || $request->user()?->can('articles.manage'), 404);

        $related = BlogPost::published()->with('category')->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($query) => $query->orderByRaw('category_id = ? DESC', [$post->category_id]))
            ->limit(3)->get();

        return view('public.blog.show', [
            'post' => $post, 'related' => $related, 'preview' => $preview,
            'recent' => BlogPost::published()->where('id', '!=', $post->id)->limit(5)->get(['title', 'slug', 'published_at']),
            'categories' => BlogCategory::query()->whereHas('posts', fn ($p) => $p->published())->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    /** RSS for people who follow the blog in a reader. */
    public function feed()
    {
        $xml = Cache::remember('blog.feed', 600, fn () => view('public.blog.feed', ['posts' => BlogPost::published()->with(['category', 'author'])->limit(20)->get()])->render());

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
