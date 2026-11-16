<x-filament-widgets::widget>
    <x-filament::section heading="Today" :description="$work['total'] === 0 ? 'Nothing needs you right now.' : $work['total'].' '.\Illuminate\Support\Str::plural('thing', $work['total']).' need your attention.'" compact>
        @if ($work['total'] > 0)
            {{-- Only the lists that have something in them are shown. --}}
            @php($box = 'border:1px solid rgba(128,128,128,.35);border-radius:.75rem;padding:1rem')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.75rem;font-size:.85rem">
                @if ($work['replies']->isNotEmpty())
                    <div style="{{ $box }}">
                        <p style="font-weight:600">Waiting for your reply ({{ $work['replies']->count() }})</p>
                        @foreach ($work['replies'] as $r)
                            <p style="margin-top:.4rem"><a href="{{ $url($r['student']) }}" style="text-decoration:underline">{{ $r['student']->name }}</a> <span style="opacity:.7">wrote {{ $r['since']->diffForHumans() }}</span></p>
                        @endforeach
                    </div>
                @endif
                @if ($work['due']->isNotEmpty())
                    <div style="{{ $box }}">
                        <p style="font-weight:600">Follow-ups due ({{ $work['due']->count() }})</p>
                        @foreach ($work['due'] as $r)
                            <p style="margin-top:.4rem"><a href="{{ $url($r['student']) }}" style="text-decoration:underline">{{ $r['student']->name }}</a> <span style="opacity:.7">{{ $r['date']->isToday() ? 'today' : 'since '.$r['date']->format('j M') }}@if ($r['followUp']->comment): {{ \Illuminate\Support\Str::limit($r['followUp']->comment, 50) }}@endif</span></p>
                        @endforeach
                    </div>
                @endif
                @if ($work['stale']->isNotEmpty())
                    <div style="{{ $box }}">
                        <p style="font-weight:600">Gone quiet ({{ $work['stale']->count() }})</p>
                        @foreach ($work['stale'] as $r)
                            <p style="margin-top:.4rem"><a href="{{ $url($r['student']) }}" style="text-decoration:underline">{{ $r['student']->name }}</a> <span style="opacity:.7">{{ $r['never'] ? 'never updated' : 'no update for '.$r['days'].' days' }}</span></p>
                        @endforeach
                    </div>
                @endif
                @if ($work['deadlines']->isNotEmpty())
                    <div style="{{ $box }}">
                        <p style="font-weight:600">Deadlines in {{ $deadlineDays }} days ({{ $work['deadlines']->count() }})</p>
                        @foreach ($work['deadlines'] as $r)
                            <p style="margin-top:.4rem"><a href="{{ $url($r['student']) }}" style="text-decoration:underline">{{ $r['student']->name }}</a> <span style="opacity:.7">{{ $r['plan']->cycle->programme->title }} closes {{ $r['closes']->format('j M') }} ({{ $r['plan']->stageLabel() }})</span></p>
                        @endforeach
                    </div>
                @endif
                @if ($work['tasks']->isNotEmpty())
                    <div style="{{ $box }}">
                        <p style="font-weight:600">Student tasks past their date ({{ $work['tasks']->count() }})</p>
                        @foreach ($work['tasks'] as $r)
                            <p style="margin-top:.4rem"><a href="{{ $url($r['student']) }}" style="text-decoration:underline">{{ $r['student']->name }}</a> <span style="opacity:.7">{{ \Illuminate\Support\Str::limit($r['task']->title, 50) }} (due {{ $r['task']->due_on->format('j M') }})</span></p>
                        @endforeach
                    </div>
                @endif
                @if ($work['returned']->isNotEmpty())
                    <div style="{{ $box }}">
                        <p style="font-weight:600">Sent back by SAS ({{ $work['returned']->count() }})</p>
                        @foreach ($work['returned'] as $r)
                            <p style="margin-top:.4rem"><a href="{{ $url($r['student']) }}" style="text-decoration:underline">{{ $r['student']->name }}</a> <span style="opacity:.7">{{ $r['plan']->application_no }}: {{ \Illuminate\Support\Str::limit((string) $r['plan']->review_remarks, 60) }}</span></p>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
