@props(['cycle', 'detailed' => false])
@php
    $svc = app(\App\Domain\Scholarship\DeadlineService::class);
    $status = $cycle->effectiveStatus();
    $d = $svc->headline($cycle);
    $info = $d ? $svc->describe($d, auth()->user()?->timezone) : null;
    $badge = match ($status->value) {
        'open' => 'badge-green', 'rolling' => 'badge-gold', 'upcoming' => 'badge-neutral',
        'closed' => 'badge-neutral', 'suspended' => 'badge-alert', default => 'badge-warn',
    };
@endphp
<div {{ $attributes->class(['text-sm']) }}>
    <p class="flex flex-wrap items-center gap-2">
        <span class="{{ $badge }}">{{ $status->label() }}</span>
        @if ($info)
            <span class="font-medium">{{ $info['primary'] }}</span>
        @else
            <span class="text-muted">Deadline not confirmed</span>
        @endif
    </p>
    @if ($info)
        @if ($info['note'])<p class="mt-1 text-xs text-muted">{{ $info['note'] }}</p>@endif
        @if ($info['secondary'])<p class="mt-1 text-xs text-muted">{{ $info['secondary'] }}</p>@endif
        @if ($info['countdown'] && $info['closes_at'])
            {{-- Countdown only for exact, future deadlines (DEAD-02). Guests also get their own time zone equivalent. --}}
            <p class="mt-1 text-xs text-muted" x-data="{ at: new Date('{{ $info['closes_at'] }}'), now: Date.now(), tick() { this.now = Date.now() } }" x-init="setInterval(() => tick(), 60000)">
                <span x-text="(() => { const ms = at - now; if (ms <= 0) return 'Closed'; const d = Math.floor(ms / 864e5); const h = Math.floor(ms % 864e5 / 36e5); return d > 0 ? 'Closes in ' + d + ' day' + (d === 1 ? '' : 's') + ' ' + h + ' h' : 'Closes in ' + h + ' h ' + Math.floor(ms % 36e5 / 6e4) + ' min'; })()"></span>
                @guest
                    <span class="block" x-text="'Your time: ' + at.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })"></span>
                @endguest
            </p>
        @endif
    @endif
</div>