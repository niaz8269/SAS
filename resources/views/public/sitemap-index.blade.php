{{-- the declaration is built in pieces: a literal question-mark tag would start PHP on servers that have short_open_tag on --}}{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($files as [$url, $changed])
    <sitemap><loc>{{ $url }}</loc>@if ($changed)<lastmod>{{ $changed->toDateString() }}</lastmod>@endif</sitemap>
@endforeach
</sitemapindex>
