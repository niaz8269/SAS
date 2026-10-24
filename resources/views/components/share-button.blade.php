@props(['url', 'title', 'label' => 'Share this post'])
{{-- One Share button. On a phone it opens the phone's own share sheet; elsewhere it opens a small menu with the common apps and a copy-link button. --}}
<div x-data="{
        open: false, copied: false, url: @js($url), title: @js($title),
        async share() {
            if (navigator.share) { try { await navigator.share({ title: this.title, url: this.url }); } catch (e) {} return; }
            this.open = ! this.open;
        },
        async copy() {
            try { await navigator.clipboard.writeText(this.url); this.copied = true; setTimeout(() => this.copied = false, 2500); }
            catch (e) { window.prompt('Copy this link', this.url); }
        }
    }" @keydown.escape.window="open = false" @click.outside="open = false" class="relative inline-block">
    <button type="button" class="btn-primary" @click="share()" :aria-expanded="open.toString()" aria-haspopup="true" aria-label="{{ $label }}">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
        Share
    </button>
    <div x-show="open" x-cloak x-transition.opacity role="menu" class="absolute left-0 z-30 mt-2 w-64 rounded-xl border border-line bg-white p-2 text-sm shadow-xl">
        <a role="menuitem" class="block rounded-lg px-3 py-2 hover:bg-navy-50" rel="noopener" target="_blank" :href="'https://wa.me/?text=' + encodeURIComponent(title + ' ' + url)">Share on WhatsApp</a>
        <a role="menuitem" class="block rounded-lg px-3 py-2 hover:bg-navy-50" rel="noopener" target="_blank" :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url)">Share on Facebook</a>
        <a role="menuitem" class="block rounded-lg px-3 py-2 hover:bg-navy-50" rel="noopener" target="_blank" :href="'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title)">Share on X</a>
        <a role="menuitem" class="block rounded-lg px-3 py-2 hover:bg-navy-50" rel="noopener" target="_blank" :href="'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url)">Share on LinkedIn</a>
        <a role="menuitem" class="block rounded-lg px-3 py-2 hover:bg-navy-50" :href="'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(url)">Send by email</a>
        <button type="button" role="menuitem" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-navy-50" @click="copy()"><span x-show="! copied">Copy link</span><span x-show="copied" x-cloak class="font-semibold text-navy-700">Link copied</span></button>
    </div>
</div>
