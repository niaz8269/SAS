<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')

    <section class="card mt-5 p-5" aria-labelledby="prog-h">
        <h2 id="prog-h" class="text-lg font-bold">Step 1: Apply now</h2>
        <dl class="mt-3 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-muted">Scholarship</dt><dd>{{ $cycle->displayName() }}</dd></div>
            <div><dt class="font-semibold text-muted">Funder</dt><dd>{{ $cycle->programme->funder?->name ?? 'Not listed' }}</dd></div>
            <div><dt class="font-semibold text-muted">Country</dt><dd>{{ $cycle->destinations->pluck('name')->implode(', ') ?: ($cycle->programme->hostCountry?->name ?? 'Not listed') }}</dd></div>
            <div><dt class="font-semibold text-muted">Deadline</dt><dd><x-deadline :cycle="$cycle" /></dd></div>
        </dl>
        <p class="mt-3 text-sm"><a class="text-navy-700 underline" href="{{ route('scholarships.show', [$cycle->programme->slug, $cycle->slug]) }}" target="_blank" rel="noopener">Read the scholarship details</a>. Nothing is sent to the funder from here.</p>
    </section>

    @if ($case)
        <div class="mt-4 rounded-lg border border-navy-300 bg-navy-50 p-4 text-sm"><strong>SAS is helping with this application.</strong> Status: {{ $case->statusLabel() }}.
            @unless ($ctx->isAgent())<a class="text-navy-700 underline" href="{{ route('student.cases.show', $case) }}">Open messages and fees</a>@endunless</div>
    @endif

    <section id="level" class="card mt-5 p-5" aria-labelledby="lvl-h">
        <h2 id="lvl-h" class="font-bold">Degree you are applying for</h2>
        @if (count($levels) > 1 && ! $locked)
            <form method="POST" action="{{ $ctx->route('applications.level.update', $plan) }}" class="mt-2 flex flex-wrap items-end gap-2">
                @csrf @method('PUT')
                <div><label for="level-select" class="label">The documents in step 3 depend on it</label>
                    <select id="level-select" name="degree_level_id" class="input"><option value="">Choose a degree</option>@foreach ($levels as $l)<option value="{{ $l->id }}" @selected($levelId === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
                <button class="btn-outline">Save</button>
            </form>
        @else
            <p class="mt-1 text-sm">{{ collect($levels)->firstWhere('id', $levelId)?->name ?? ($levelId ? '' : 'Not chosen') }}</p>
        @endif
    </section>

    <section id="language" class="card mt-5 p-5" aria-labelledby="lang-h">
        <h2 id="lang-h" class="font-bold">Language of study</h2>
        @if (count($languageOptions) > 1 && ! $locked)
            <form method="POST" action="{{ $ctx->route('applications.language.update', $plan) }}" class="mt-2 flex flex-wrap items-end gap-2">
                @csrf @method('PUT')
                <div><label for="study-language" class="label">English, or the official language of {{ $cycle->destinations->pluck('name')->implode(', ') ?: ($cycle->programme->hostCountry?->name ?? 'the country') }}</label>
                    <select id="study-language" name="study_language" class="input"><option value="">Choose a language</option>@foreach ($languageOptions as $l)<option value="{{ $l }}" @selected($plan->study_language === $l)>{{ $l }}</option>@endforeach</select></div>
                <button class="btn-outline">Save</button>
            </form>
        @else
            <p class="mt-1 text-sm">{{ $plan->study_language ?: 'English' }}@if (count($languageOptions) === 1). No other official language is listed for this scholarship's country.@endif</p>
        @endif
    </section>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <a class="text-sm text-navy-700 underline" href="{{ $ctx->route('applications.tools', $plan) }}">Notes, deadlines and tracker</a>
        <a class="btn-primary" href="{{ $ctx->route('applications.form', $plan) }}">Next: application form</a>
    </div>
</x-dynamic-component>
