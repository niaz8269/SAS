<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')

    <div class="mt-6 space-y-4">
        <section class="card" aria-labelledby="prog-h">
            <h2 id="prog-h" class="border-b border-line px-4 py-3 text-lg font-bold">This scholarship</h2>
            <dl class="grid gap-x-8 gap-y-3 p-4 text-base sm:grid-cols-2">
                <div><dt class="text-sm text-muted">Scholarship</dt><dd>{{ $cycle->displayName() }}</dd></div>
                <div><dt class="text-sm text-muted">Funder</dt><dd>{{ $cycle->programme->funder?->name ?? 'Not listed' }}</dd></div>
                <div><dt class="text-sm text-muted">Country</dt><dd>{{ $cycle->destinations->pluck('name')->implode(', ') ?: ($cycle->programme->hostCountry?->name ?? 'Not listed') }}</dd></div>
                <div><dt class="text-sm text-muted">Deadline</dt><dd><x-deadline :cycle="$cycle" /></dd></div>
            </dl>
            <p class="border-t border-line px-4 py-3 text-base text-muted"><a class="text-navy-700 underline" href="{{ route('scholarships.show', [$cycle->programme->slug, $cycle->slug]) }}" target="_blank" rel="noopener">Read the scholarship details</a>. Nothing is sent to the funder from here.
                @if ($case) SAS is helping with this application. Status: {{ $case->statusLabel() }}.@unless ($ctx->isAgent()) <a class="text-navy-700 underline" href="{{ route('student.cases.show', $case) }}">Open messages and fees</a>@endunless @endif</p>
        </section>

        <section id="level" class="card" aria-labelledby="lvl-h">
            <h2 id="lvl-h" class="border-b border-line px-4 py-3 text-lg font-bold">Degree you are applying for</h2>
            <div class="p-4 text-base">
                @if (count($levels) > 1 && ! $locked)
                    <form method="POST" action="{{ $ctx->route('applications.level.update', $plan) }}" class="flex flex-wrap items-end gap-3">
                        @csrf @method('PUT')
                        <div><label for="level-select" class="mb-1 block text-sm text-muted">The documents you upload depend on it</label>
                            <select id="level-select" name="degree_level_id" class="rounded-lg border border-line bg-white px-3 py-2"><option value="">Choose a degree</option>@foreach ($levels as $l)<option value="{{ $l->id }}" @selected($levelId === $l->id)>{{ $l->name }}</option>@endforeach</select></div>
                        <button class="btn-outline">Save</button>
                    </form>
                @else
                    <p>{{ collect($levels)->firstWhere('id', $levelId)?->name ?? 'Not chosen' }}</p>
                @endif
            </div>
        </section>

        <section id="language" class="card" aria-labelledby="lang-h">
            <h2 id="lang-h" class="border-b border-line px-4 py-3 text-lg font-bold">Language of study</h2>
            <div class="p-4 text-base">
                @if (count($languageOptions) > 1 && ! $locked)
                    <form method="POST" action="{{ $ctx->route('applications.language.update', $plan) }}" class="flex flex-wrap items-end gap-3">
                        @csrf @method('PUT')
                        <div><label for="study-language" class="mb-1 block text-sm text-muted">English, or the official language of {{ $cycle->destinations->pluck('name')->implode(', ') ?: ($cycle->programme->hostCountry?->name ?? 'the country') }}</label>
                            <select id="study-language" name="study_language" class="rounded-lg border border-line bg-white px-3 py-2"><option value="">Choose a language</option>@foreach ($languageOptions as $l)<option value="{{ $l }}" @selected($plan->study_language === $l)>{{ $l }}</option>@endforeach</select></div>
                        <button class="btn-outline">Save</button>
                    </form>
                @else
                    <p>{{ $plan->study_language ?: 'English' }}@if (count($languageOptions) === 1). No other official language is listed for this scholarship's country.@endif</p>
                @endif
            </div>
        </section>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
        <a class="text-base text-navy-700 underline" href="{{ $ctx->route('applications.tools', $plan) }}">Notes, deadlines and tracker</a>
        <a class="btn-primary" href="{{ $ctx->route('applications.form', $plan) }}">Next: Your details</a>
    </div>
</x-dynamic-component>
