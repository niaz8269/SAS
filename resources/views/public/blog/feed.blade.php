{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title>{{ config('sas.brand.name') }} blog</title>
    <link>{{ route('blog.index') }}</link>
    <description>News, advice and stories on scholarships, applications, universities and studying abroad.</description>
    <language>en</language>
    <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
@foreach ($posts as $post)
    <item>
        <title>{{ $post->title }}</title>
        <link>{{ route('blog.show', $post->slug) }}</link>
        <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
        <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
@if ($post->category)
        <category>{{ $post->category->name }}</category>
@endif
        <description>{{ $post->summary(300) }}</description>
    </item>
@endforeach
</channel>
</rss>
