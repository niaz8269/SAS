<div style="font-size:.85rem;line-height:1.45">
    @forelse ($events as $e)
        <div style="display:grid;grid-template-columns:8.5rem 6.5rem 1fr;gap:.6rem;padding:.45rem 0;border-bottom:1px solid rgba(128,128,128,.25)">
            <span style="opacity:.7">{{ $e['at']->format('j M Y, H:i') }}</span>
            <span style="font-weight:600">{{ $e['kind'] }}</span>
            <span style="white-space:pre-line">{{ $e['text'] }}</span>
        </div>
    @empty
        <p>Nothing has happened yet.</p>
    @endforelse
</div>