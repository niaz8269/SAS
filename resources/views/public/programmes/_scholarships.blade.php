@php
    $subject = $filters['q'] !== '' ? '“'.$filters['q'].'”' : 'these programmes';
@endphp
@if ($forMajor && ($forMajor['matched']->isNotEmpty() || $forMajor['general']->isNotEmpty()))
    <section class="mt-6" aria-labelledby="major-scholarships">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 id="major-scholarships" class="section-title">Scholarships for {{ $subject }}</h2>
            <a class="text-sm font-semibold text-navy-700 underline" href="{{ $forMajor['link'] }}">{{ $forMajor['matchedTotal'] > 0 ? 'See all '.$forMajor['matchedTotal'].' in the scholarship search' : 'Browse scholarships for China' }} &rarr;</a>
        </div>

        @if ($forMajor['matched']->isNotEmpty())
            <p class="mt-1 text-sm text-muted">{{ $forMajor['matchedTotal'] }} {{ \Illuminate\Support\Str::plural('scholarship', $forMajor['matchedTotal']) }} for study in China{{ $filters['q'] !== '' ? ' whose published details name '.$subject : '' }}{{ $forMajor['degree'] ? ' at this degree level' : '' }}. Open calls come first; closed ones show what to prepare for the next call.</p>
            <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">@foreach ($forMajor['matched'] as $c)<x-cycle-card :cycle="$c" />@endforeach</div>
        @endif

        @if ($forMajor['general']->isNotEmpty())
            <h3 class="mt-6 font-semibold">Scholarships that fund any subject</h3>
            <p class="mt-1 text-sm text-muted">These are open to students in any field of study, so they can be used for {{ $subject }} too. Check each page for what the award covers: a particular degree, courses each institution chooses, or a listed set of courses. See also the <a class="text-navy-700 underline" href="{{ route('csc.guide') }}">Chinese Government Scholarship guide</a>: programmes marked &#9733; below are open to its applicants.</p>
            <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">@foreach ($forMajor['general'] as $c)<x-cycle-card :cycle="$c" />@endforeach</div>
        @endif
    </section>
@endif
