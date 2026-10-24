<x-dynamic-component :component="$ctx->layout()" title="Choose scholarships">
    <h1 class="text-[26px] font-normal uppercase tracking-wide text-[#555]">{{ $ctx->isAgent() ? 'Add an application for '.$student->name : 'Choose scholarships' }}</h1>
    <div class="mt-2 border-b-2 border-dotted border-[#ccc]" aria-hidden="true"></div>
    <p class="mt-3 text-[13px] text-[#666]">Press <strong>Apply</strong> next to a scholarship to start an application. You then follow four steps: apply, application form, upload materials, submit. You can start more than one.</p>

    @if ($planned)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border border-[#f0b46a] bg-[#fdebd0] p-3 text-[14px] text-[#b4530b]">
            <p><strong>{{ count($planned) }}</strong> {{ \Illuminate\Support\Str::plural('application', count($planned)) }} started.</p>
            <a href="{{ $ctx->route('applications.index') }}" class="bg-[#0b4a8f] px-5 py-1.5 text-white hover:bg-[#093d75]">Go to the applications</a>
        </div>
    @endif

    @if ($recommended->isNotEmpty())
        <div class="mt-5 border border-[#2f7cc4]">
            <div class="bg-[#307cc7] px-4 py-2.5 text-[14px] text-white">Recommended for {{ $ctx->isAgent() ? $student->name : 'you' }}, from {{ $ctx->isAgent() ? 'their' : 'your' }} application form and goals</div>
            <ul class="divide-y divide-[#e5e5e5] bg-white">
                @foreach ($recommended as $m)
                    @php($c = $m['cycle'])
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-[13px]">
                        <div class="min-w-0 flex-1"><p class="text-[15px] font-semibold">{{ $c->programme->title }}</p><p class="text-[#666]">Fits: {{ implode(', ', $m['why']) }} &middot; <a class="text-[#428bb6] underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">Read details</a></p></div>
                        @if (isset($planned[$c->id]))
                            <a class="border border-[#bbb] bg-white px-5 py-2 hover:bg-[#f5f5f5]" href="{{ $ctx->route('applications.show', $planned[$c->id]) }}">✓ Open application</a>
                        @else
                            <form method="POST" action="{{ $ctx->route('applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}"><button class="bg-[#0b4a8f] px-7 py-2 font-semibold text-white hover:bg-[#093d75]" aria-label="Apply for {{ $c->programme->title }}">Apply</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ $ctx->route('choose.index') }}" class="mt-5 flex flex-wrap items-end gap-3 border border-[#ddd] bg-[#f9f9f9] p-4 text-[13px]" role="search">
        <div class="min-w-[12rem] flex-1"><label for="q" class="mb-1 block text-[#666]">Search by name</label><input id="q" name="q" type="search" value="{{ $filters['q'] }}" maxlength="80" class="w-full border border-[#ccc] bg-white px-3 py-2" placeholder="For example Chevening"></div>
        <div><label for="level" class="mb-1 block text-[#666]">Degree</label>
            <select id="level" name="level" class="border border-[#ccc] bg-white px-3 py-2"><option value="">All degrees</option>@foreach ($levels as $l)<option value="{{ $l->code }}" @selected($filters['level'] === $l->code)>{{ $l->name }}</option>@endforeach</select></div>
        <button class="bg-[#307cc7] px-6 py-2 text-white hover:bg-[#2a6db0]">Search</button>
        <label class="flex w-full items-center gap-2"><input type="checkbox" name="show_closed" value="1" class="border-slate-300" @checked($filters['show_closed'])> Also show scholarships that are closed now</label>
    </form>

    <form id="many" method="POST" action="{{ $ctx->route('applications.store-many') }}" class="mt-5 flex flex-wrap items-center justify-between gap-3 border border-[#ddd] bg-[#f9f9f9] p-3 text-[13px]">@csrf
        <p>Tick several scholarships in the list and start them all at once.</p>
        <button class="bg-[#0b4a8f] px-6 py-2 font-semibold text-white hover:bg-[#093d75]">Start the ticked applications</button>
        @error('cycle_ids')<p class="w-full text-[#d9534f]" role="alert">{{ $message }}</p>@enderror
    </form>
    <div class="mt-3 border border-[#2f7cc4]">
        <div class="bg-[#307cc7] px-4 py-2.5 text-[14px] text-white" aria-live="polite">List &middot; {{ $cycles->count() }} {{ \Illuminate\Support\Str::plural('scholarship', $cycles->count()) }} shown</div>
        <ul class="divide-y divide-[#e5e5e5] bg-white">
            @forelse ($cycles as $c)
                @php($next = app(\App\Domain\Scholarship\DeadlineService::class)->nextClosing($c))
                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    @unless (isset($planned[$c->id]))<input type="checkbox" form="many" name="cycle_ids[]" value="{{ $c->id }}" class="h-4 w-4" aria-label="Select {{ $c->programme->title }}">@else<span class="inline-block w-4"></span>@endunless
                    <div class="min-w-0 flex-1 text-[13px]">
                        <p class="text-[15px] font-semibold text-[#333]">{{ $c->programme->title }}</p>
                        <p class="mt-0.5 text-[#666]">{{ $c->destinations->pluck('name')->implode(', ') ?: 'Country not listed' }} &middot; {{ $c->degreeLevels->pluck('name')->implode(', ') ?: 'Degree not listed' }}</p>
                        <p class="mt-0.5">@if ($next) Closes {{ $next->format('j M Y') }} @else <span class="text-[#777]">{{ $c->effectiveStatus()->label() }}</span> @endif
                            &middot; <a class="text-[#428bb6] underline" href="{{ route('scholarships.show', [$c->programme->slug, $c->slug]) }}">Read details</a></p>
                    </div>
                    @if (isset($planned[$c->id]))
                        <a class="border border-[#bbb] bg-white px-5 py-2 text-[13px] hover:bg-[#f5f5f5]" href="{{ $ctx->route('applications.show', $planned[$c->id]) }}"><span aria-hidden="true">✓</span> Open application</a>
                    @else
                        <form method="POST" action="{{ $ctx->route('applications.store') }}">@csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}"><button class="bg-[#0b4a8f] px-7 py-2 text-[13px] font-semibold text-white hover:bg-[#093d75]" aria-label="Apply for {{ $c->programme->title }}">Apply</button></form>
                    @endif
                </li>
            @empty
                <li class="px-4 py-10 text-center text-[13px] text-[#777]">No scholarships match. Try another name or degree.</li>
            @endforelse
        </ul>
    </div>
</x-dynamic-component>
