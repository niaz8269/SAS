<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * ADM-03: allowlist HTML sanitiser for editorial rich text. Scripts, styles, event handlers and unknown tags are removed;
 * only plain formatting and http(s)/mailto links survive. Applied when content is rendered so stored data can never run code.
 */
class Html
{
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'code', 'pre', 'small', 'span', 'img'];

    /** Dropped together with everything inside them. */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'title', 'head'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="sas-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('sas-root');
        if (! $root) {
            return e(strip_tags($html));
        }
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * Text written in the admin's rich editor, safe to print as it is. Older entries were plain text with line breaks: those are shown as paragraphs.
     */
    public static function rich(?string $value): HtmlString
    {
        $value = trim((string) $value);
        if ($value === '') {
            return new HtmlString('');
        }
        if (! preg_match('/<\/?[a-z][^>]*>/i', $value)) {
            $paragraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', e($value))), 'strlen');

            return new HtmlString(implode('', array_map(fn ($p) => '<p>'.nl2br($p, false).'</p>', $paragraphs)));
        }

        return new HtmlString(self::clean($value));
    }

    /** True for nothing, spaces, or the editor's empty paragraph; false when there is any text or a picture. */
    public static function isBlank(?string $value): bool
    {
        $value = (string) $value;

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '' && ! str_contains($value, '<img');
    }

    /** The same text without any formatting, on one line: for cards, page descriptions and the search index. */
    public static function text(?string $value, ?int $limit = null): string
    {
        $value = str_replace(['</p>', '</li>', '</h2>', '</h3>', '<br>', '<br/>', '<br />'], ' ', self::clean((string) $value)); // cleaned first, so the words of a pasted script do not leak into a description
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $limit ? Str::limit($plain, $limit) : $plain;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                    $node->removeChild($child);

                    continue;
                }
                self::walk($child);
                if ($tag === 'img' && ! self::safeImage($child->getAttribute('src'))) {
                    $node->removeChild($child); // only images that live in our own media storage are allowed

                    continue;
                }
                if (! in_array($tag, self::ALLOWED, true)) {
                    // Unknown tag: keep its (already cleaned) children, drop the wrapper.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }
                self::cleanAttributes($child, $tag);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $keep = ($tag === 'a' && $name === 'href' && self::safeUrl($attr->value))
                || ($tag === 'img' && in_array($name, ['src', 'alt', 'width', 'height'], true));
            if (! $keep && ! ($tag === 'a' && in_array($name, ['title'], true))) {
                $el->removeAttribute($attr->name);
            }
        }
        if ($tag === 'a') {
            // links inside our own site pass on normally; links to other sites are marked so nobody gains from pasting them
            $href = trim($el->getAttribute('href'));
            if (str_starts_with($href, '/') || str_starts_with($href, '#') || str_starts_with($href, rtrim((string) config('app.url'), '/').'/')) {
                $el->removeAttribute('rel');
            } else {
                $el->setAttribute('rel', 'noopener nofollow ugc');
            }
        }
    }

    /** Images are allowed only from the site's own public media storage (the media library and editor attachments). */
    private static function safeImage(string $src): bool
    {
        $src = trim(preg_replace('/[\x00-\x20]+/', '', $src) ?? '');
        $base = rtrim(Storage::disk('public_media')->url(''), '/').'/';

        return $src !== '' && (str_starts_with($src, $base) || str_starts_with($src, '/storage/')) && ! str_contains($src, '..');
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');

        return (bool) preg_match('#^(https?://|mailto:|/(?![/\\\\])|\#)#i', $url); // a leading "/\" is read by browsers as "//", so it is not a local link
    }
}
