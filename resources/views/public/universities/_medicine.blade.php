@php
    $profile = $university->profile ?? [];
    $hospitals = $profile['affiliated_hospitals'] ?? [];
    $recognitions = collect($profile['recognitions'] ?? []);
    $first = $feeStructures->first();
@endphp
<section class="bg-navy-50/60 py-10" aria-labelledby="medicine-h">
    <div class="container-page">
        <p class="eyebrow">Medicine for international students</p>
        <h2 id="medicine-h" class="section-title mt-1">Studying medicine at {{ $university->name }}</h2>
        <p class="mt-1 max-w-3xl text-sm text-muted">Fees and rules below are for self-funded international students. They change from year to year: always confirm with the university and with SAS before you pay anything.</p>

        <div class="mt-6 grid gap-5 lg:grid-cols-3">
            <div class="card p-5 lg:col-span-1">
                <h3 class="font-bold">The university</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    @if ($place ?? false)<div><dt class="font-semibold">Location</dt><dd>{{ $place }}{{ $university->country ? ', '.$university->country->name : '' }}</dd></div>@endif
                    @if (! empty($profile['established_year']))<div><dt class="font-semibold">Founded</dt><dd>{{ $profile['established_year'] }}</dd></div>@endif
                    @if (! empty($profile['institution_type']))<div><dt class="font-semibold">Type</dt><dd>{{ $profile['institution_type'] }}</dd></div>@endif
                    @if (! empty($profile['students_note']))<div><dt class="font-semibold">Students</dt><dd>{{ $profile['students_note'] }}</dd></div>@endif
                    @if (array_key_exists('moe_english_mbbs', $profile) && $profile['moe_english_mbbs'])<div><dt class="font-semibold">Ministry of Education</dt><dd>Approved to enrol international students for English-taught medicine @if (! empty($profile['moe_source_url'])) (<a class="text-navy-700 underline" rel="noopener nofollow" target="_blank" href="{{ $profile['moe_source_url'] }}">source</a>) @endif</dd></div>@endif
                    @if (! empty($profile['accommodation_note']))<div><dt class="font-semibold">Accommodation</dt><dd>{{ $profile['accommodation_note'] }}</dd></div>@endif
                </dl>
                @if (! empty($profile['summary']))<div class="rich mt-3 text-sm text-muted">{!! \App\Support\Html::rich($profile['summary']) !!}</div>@endif
            </div>

            <div class="card p-5 lg:col-span-2">
                <h3 class="font-bold">Scholarships for international students</h3>
                <ul class="mt-3 space-y-3 text-sm">
                    @foreach ($scholarships as $s)
                        <li class="rounded-md border border-line p-3">
                            <p class="font-semibold">{{ $s->name }} <span class="badge-neutral ml-1">{{ $s->providerLabel() }}</span></p>
                            @if ($s->coverage)<p class="mt-1"><span class="text-muted">Covers:</span> {{ $s->coverage }}</p>@endif
                            @if ($s->amount_text)<p><span class="text-muted">Amount:</span> {{ $s->amount_text }}</p>@endif
                            @if ($s->eligibility)<div><span class="text-muted">Who can get it:</span><div class="rich">{!! \App\Support\Html::rich($s->eligibility) !!}</div></div>@endif
                            <p class="mt-1 text-xs text-muted">
                                @if ($s->source_type === 'supplied') From the university's fee sheet given to SAS.
                                @elseif ($s->source_url) Source: <a class="text-navy-700 underline" rel="noopener nofollow" target="_blank" href="{{ $s->source_url }}">{{ $s->source_label ?: 'official page' }}</a>@if ($s->checked_on), read {{ $s->checked_on->format('j F Y') }}@endif.
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        @foreach ($feeStructures as $fs)
            @php($totals = $fs->totals())
            <article class="card mt-6 p-5" aria-labelledby="fs-{{ $fs->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 id="fs-{{ $fs->id }}" class="text-lg font-bold">{{ $fs->programme_name }}@if ($fs->intake_label) <span class="font-normal text-muted">&middot; {{ $fs->intake_label }}{{ str_contains(strtolower($fs->intake_label), 'intake') ? '' : ' intake' }}</span>@endif</h3>
                        <p class="mt-1 text-sm text-muted">
                            {{ collect([$fs->duration_text, $fs->teaching_language ? 'Taught in '.$fs->teaching_language : null, \App\Models\UniversityProgramme::LEVELS[$fs->level] ?? null])->filter()->implode(' · ') }}
                        </p>
                    </div>
                    <a href="{{ route('contact') }}" class="btn-primary">Ask SAS about applying</a>
                </div>

                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">Fees for {{ $fs->programme_name }}, {{ $fs->intake_label }}</caption>
                            <thead><tr class="border-b border-line text-muted"><th scope="col" class="py-2 pr-3">Fee</th><th scope="col" class="py-2 pr-3">First year</th><th scope="col" class="py-2">Each later year</th></tr></thead>
                            <tbody>
                                @if ($fs->tuition_amount !== null)
                                    <tr class="border-b border-line/60 align-top"><th scope="row" class="py-2 pr-3 font-medium">Tuition @if ($fs->tuition_period === 'semester') <span class="font-normal text-muted">(two semesters)</span>@endif</th><td class="py-2 pr-3">{{ $fs->money($fs->tuitionPerYear()) }}</td><td class="py-2">{{ $fs->money($fs->tuitionPerYear(true)) }}</td></tr>
                                @endif
                                @if ($fs->hostel_amount !== null)
                                    <tr class="border-b border-line/60 align-top"><th scope="row" class="py-2 pr-3 font-medium">Hostel @if ($fs->hostel_note) <span class="block text-xs font-normal text-muted">{{ $fs->hostel_note }}</span>@endif</th><td class="py-2 pr-3">{{ $fs->money($fs->hostel_amount) }}</td><td class="py-2">{{ $fs->money($fs->hostel_amount_later ?? $fs->hostel_amount) }}</td></tr>
                                @endif
                                @foreach ($fs->other_fees ?? [] as $fee)
                                    @continue(! isset($fee['amount']))
                                    @php($every = ($fee['when'] ?? 'every_year') === 'every_year')
                                    @php($amount = $fs->currency.' '.number_format($fee['amount']).(! empty($fee['amount_max']) ? ' to '.number_format($fee['amount_max']) : ''))
                                    <tr class="border-b border-line/60 align-top"><th scope="row" class="py-2 pr-3 font-medium">{{ $fee['label'] }}@if (! empty($fee['refundable'])) <span class="badge-neutral ml-1">refundable</span>@endif @if (! empty($fee['optional'])) <span class="badge-neutral ml-1">optional</span>@endif @if (! empty($fee['note']))<span class="block text-xs font-normal text-muted">{{ $fee['note'] }}</span>@endif</th><td class="py-2 pr-3">{{ $amount }}</td><td class="py-2">{{ $every ? $amount : 'None' }}</td></tr>
                                @endforeach
                                @if ($totals['first'] !== null)
                                    <tr class="font-bold"><th scope="row" class="py-3 pr-3">Total charged by the university @if ($totals['from']) <span class="font-normal text-muted">(at least)</span>@endif</th><td class="py-3 pr-3">{{ $fs->money($totals['first']) }}</td><td class="py-3">{{ $fs->money($totals['later']) }}</td></tr>
                                @endif
                            </tbody>
                        </table>
                        @if ($fs->application_fee_amount !== null)<p class="mt-2 text-xs text-muted">Application fee {{ $fs->money($fs->application_fee_amount) }}, paid when you apply and not included above.</p>@endif
                        <p class="mt-1 text-xs text-muted">Not included: flights, food, pocket money, and any fee of an agent or consultant. Refundable deposits and optional items are not added to the totals.</p>
                    </div>

                    <div class="space-y-3 text-sm">
                        <dl class="space-y-2">
                            @if ($fs->hsk_note)<div><dt class="font-semibold">Chinese language (HSK)</dt><dd>{{ $fs->hsk_note }}</dd></div>@endif
                            @if ($fs->age_note)<div><dt class="font-semibold">Age</dt><dd>{{ $fs->age_note }}</dd></div>@endif
                            @if ($fs->marks_note)<div><dt class="font-semibold">Marks needed</dt><dd>{{ $fs->marks_note }}</dd></div>@endif
                            @if ($fs->application_deadline)<div><dt class="font-semibold">Application deadline</dt><dd>{{ $fs->application_deadline }}</dd></div>@endif
                        </dl>
                        @if (! empty($fs->required_documents))
                            <div><h4 class="font-semibold">Documents you will need</h4>
                                <ul class="mt-1 list-disc space-y-0.5 pl-5">@foreach ($fs->required_documents as $doc)<li>{{ $doc }}</li>@endforeach</ul></div>
                        @endif
                    </div>
                </div>
                <p class="mt-4 border-t border-line pt-3 text-xs text-muted">Source: {{ $fs->sourceLabelText() }}@if ($fs->source_url && $fs->source_type !== 'supplied'): <a class="text-navy-700 underline" rel="noopener nofollow" target="_blank" href="{{ $fs->source_url }}">official page</a>@endif.</p>
            </article>
        @endforeach

        @if (! empty($hospitals) || ! empty($profile['affiliated_hospitals_note']) || $recognitions->isNotEmpty())
            <div class="mt-6 grid gap-5 lg:grid-cols-2">
                @if (! empty($hospitals) || ! empty($profile['affiliated_hospitals_note']))
                    <div class="card p-5"><h3 class="font-bold">Teaching hospitals</h3>
                        @if (! empty($profile['affiliated_hospitals_note']))<p class="mt-2 text-sm">{{ $profile['affiliated_hospitals_note'] }}</p>@endif
                        @if (! empty($hospitals))<ul class="mt-2 list-disc space-y-1 pl-5 text-sm">@foreach ($hospitals as $h)<li>{{ $h['name'] }}@if (! empty($h['note'])) <span class="text-muted">&mdash; {{ $h['note'] }}</span>@endif</li>@endforeach</ul>@endif
                    </div>
                @endif
                @if ($recognitions->isNotEmpty())
                    <div class="card p-5"><h3 class="font-bold">Recognition</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($recognitions as $r)
                                <li><span class="font-medium">{{ $r['body'] }}</span>
                                    @if (($r['status'] ?? '') === 'verified')<span class="badge-green ml-1">checked by SAS</span>@else<span class="badge-neutral ml-1">stated by the university, not checked by SAS</span>@endif
                                    @if (! empty($r['source_url']))<a class="ml-1 text-xs text-navy-700 underline" rel="noopener nofollow" target="_blank" href="{{ $r['source_url'] }}">{{ $r['source_label'] ?? 'source' }}</a>@endif
                                    @if (! empty($r['note']))<span class="block text-xs text-muted">{{ $r['note'] }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-muted">Check that a degree is accepted in the country where you plan to practise before you apply.</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
