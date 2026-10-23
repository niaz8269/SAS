{{-- A small file icon by extension: PDF (red), Word (blue) or picture (green). Expects $name. --}}
@php
    $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
    [$color, $label] = match (true) { $ext === 'pdf' => ['#d9372b', 'PDF'], $ext === 'docx' || $ext === 'doc' => ['#2b6cc4', 'W'], default => ['#3c9a5b', 'IMG'] };
@endphp
<svg class="h-14 w-12" viewBox="0 0 48 56" role="img" aria-label="{{ $label }} file"><path d="M6 2h26l10 10v42H6V2Z" fill="#fff" stroke="{{ $color }}" stroke-width="2"/><path d="M32 2v10h10" fill="none" stroke="{{ $color }}" stroke-width="2"/><rect x="6" y="30" width="36" height="14" fill="{{ $color }}"/><text x="24" y="41" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="700" fill="#fff">{{ $label }}</text></svg>
