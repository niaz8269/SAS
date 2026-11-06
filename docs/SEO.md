# SEO audit and indexing plan (2026-11-06)

Audit of https://sas.cas.moe by fetching the live sitemap and reading 400 of its pages (all 384 non-scholarship pages and 16 of the 118 scholarship pages; each scholarship view is counted in the analytics, so the rest were left out).

## What was already good

- All 400 pages returned 200, none was `noindex`, none had a missing or duplicate title, every page had one H1, a canonical address and a description, and no image lacked alt text.
- `sitemap.xml` and `robots.txt` came from the app and followed the database; the sitemap listed 502 addresses (308 universities, 118 scholarships, 49 destinations, 10 blog posts, 10 services and the main pages).
- Structured data existed on the home page, blog posts, universities, scholarships (breadcrumbs) and stories.

## What was holding indexing back, and what changed

| Finding | Why it matters | Change |
|---|---|---|
| `robots.txt` blocked `/universities?*` and `/scholarships?*`, which also blocked `?page=2`, `?page=3`... | Only 24 of 308 universities and 12 of 118 scholarships were reachable by links. The rest could only be found through the sitemap, which Google treats as a hint and indexes more slowly. | Numbered pages are allowed (`Allow: /universities?page=*` and the same for scholarships and programmes). Search and filter combinations stay blocked. |
| Page 2 and later named page 1 as their canonical address, with the same title and description | Google may drop them as copies, and the links on them count for less. | A numbered page names itself as canonical and gets "(page N)" in its title and "Page N." in its description. A search or filter still names the plain address. |
| No page linked to every page | Search engines follow links first. | New `/site-map` page (a link in every footer) lists all 500+ pages by kind, built from the same data as the sitemap. |
| One flat sitemap | Search Console could not show how many pages of each kind are indexed. | `/sitemap.xml` is now an index of five files: `sitemap-pages.xml`, `-scholarships.xml`, `-universities.xml`, `-destinations.xml`, `-blog.xml`. Search Console reports each one. |
| List pages had no date in the sitemap; the home page was listed without the `/` the page itself names as canonical | Small crawl-signal gaps. | A list page now carries the date of the newest thing on it (from the data; fixed pages carry no date). Home is listed as `https://sas.cas.moe/`. `/be-a-partner`, `/image-credits`, privacy and terms were added. |
| 101 titles under 25 characters ("About", "Contact", "Blog", "Study in Austria") and 8 service descriptions under 70 | Short titles say little to a searcher and rank for little. | Titles now say what is on the page (for example "Study in Austria: costs and scholarships"; "Study abroad services: advice, translation, visas"). A short service summary is lengthened with a plain sentence. The blog categories repeated the blog's own description; each now has its own. |
| 72 pages had no structured data (destinations, services, hubs, about, contact) | Breadcrumbs in results and clearer page meaning. | Breadcrumb data on every hub page, `WebPage` on destination guides, `Service` on service pages. |
| No way to verify the site with Google or Bing from the admin | Without verification there is no Search Console. | Settings > Search engines: paste the Google and Bing codes. |
| Robots meta | | Indexable pages say `index, follow, max-image-preview:large, max-snippet:-1`, which allows large previews in Google Discover and search. |

## Automatic updates

Nothing in the sitemap is typed in. Each file is built from the database each time it is asked for and kept for ten minutes, but saving or deleting a scholarship, university, destination guide, service, article, page, blog post, category or story clears it at once. Publish something in the admin and it is in the sitemap and on `/site-map` straight away; unpublish it and it is gone. The same clearing also refreshes the blog feed. Tests: `tests/Feature/Public/SeoTest.php`.

## Week-one plan (these steps are yours; they need your Google and Bing accounts)

Nobody can promise a number of indexed pages: Google decides, and a new site is crawled slowly at first. The site now offers about 500 pages that are worth indexing and are one or two links from every page, which is what makes 100 in the first week realistic rather than certain.

1. **Day 1: verify.** In [Google Search Console](https://search.google.com/search-console) add a *URL prefix* property `https://sas.cas.moe/`, choose the *HTML tag* method, copy the text after `content=`, paste it under Settings > Search engines > Google, save, then press Verify. Do the same in [Bing Webmaster Tools](https://www.bing.com/webmasters) (it can also import from Search Console).
2. **Day 1: submit** `https://sas.cas.moe/sitemap.xml` in Search Console (Sitemaps) and in Bing. Submit the index only; Google reads the five files from it.
3. **Day 1 to 3: ask for indexing** of the ten pages that matter most, with *URL Inspection > Request indexing* (about ten a day are allowed): the home page, `/scholarships`, `/universities`, `/destinations`, `/blog`, `/site-map`, your best three scholarship pages. Google then follows links from them.
4. **Day 3 and 7: read the report.** Search Console > Pages shows indexed and not-indexed pages with reasons; Sitemaps shows the five files separately. "Discovered, currently not indexed" in the first days is normal and clears with time and links.
5. **Links from outside help most.** Put `https://sas.cas.moe` in your profiles (Facebook, LinkedIn, YouTube, X, Instagram), in the 20i parent site, and in any partner agency page. A new domain with no links is crawled the slowest.
6. **Add content.** Each new blog post, scholarship and guide adds a page to the sitemap by itself. Pages with substance (a full guide, a complete scholarship page) are indexed faster than thin ones.

## Not changed, and worth knowing

- The home page and `/scholarships` answer in 1.4 to 2.4 seconds on the shared host (up to 7 s once, cold). That is acceptable for crawling but slow for visitors; caching the lists is the next speed step.
- `/index.php` serves the home page too; its canonical address points to `/`, so it is not counted twice.
- The host's firewall refuses requests whose user agent is exactly `Mozilla/5.0` (HTTP 403). Browsers and search-engine crawlers send longer ones, so they are not affected. The Search Console *URL Inspection > Live test* will show immediately if Google is ever blocked.
- Some university pages have about 180 words. They are real pages and indexable; adding a summary in the admin makes them stronger.
