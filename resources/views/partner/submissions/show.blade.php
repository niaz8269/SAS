<x-layouts.partner :title="$submission->title">
    <p class="text-sm"><a class="text-navy-700 underline" href="{{ route('partner.submissions.index') }}">&larr; Submissions</a></p>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-extrabold">{{ $submission->title }}</h1><span class="badge-neutral">{{ $submission->statusLabel() }}</span></div>

    @if ($submission->editor_note)
        <div class="mt-4 rounded-lg border border-gold/50 bg-gold-soft p-4 text-sm" role="note"><p class="font-semibold">Message from our editors</p><p class="mt-1 whitespace-pre-line">{{ $submission->editor_note }}</p></div>
    @endif
    @if ($submission->status === 'accepted')<p class="mt-4 rounded-lg border border-line bg-white p-4 text-sm">Accepted. Your scholarship is now going through our normal review before it is published.</p>@endif

    <dl class="card mt-5 grid gap-x-8 gap-y-4 p-5 text-sm sm:grid-cols-2">
        <div><dt class="font-semibold">Funder</dt><dd>{{ $submission->funder_name ?: '—' }}</dd></div>
        <div><dt class="font-semibold">Held in</dt><dd>{{ $submission->country?->name ?: '—' }}</dd></div>
        <div><dt class="font-semibold">Degree levels</dt><dd>{{ collect($submission->degree_codes)->map(fn ($c) => \App\Models\DegreeLevel::where('code', $c)->value('name') ?? $c)->implode(', ') ?: '—' }}</dd></div>
        <div><dt class="font-semibold">Intake</dt><dd>{{ trim(($submission->cycle_year ?? '').' '.($submission->intake_term ?? '')) ?: '—' }}</dd></div>
        <div><dt class="font-semibold">Funding</dt><dd>{{ \App\Models\PartnerSubmission::FUNDING[$submission->funding_type] ?? $submission->funding_type }}@if ($submission->funding_summary): {{ $submission->funding_summary }}@endif</dd></div>
        <div><dt class="font-semibold">Teaching language</dt><dd>{{ $submission->teaching_language ?: '—' }}</dd></div>
        <div><dt class="font-semibold">Closing date</dt><dd>{{ $submission->deadline_on ? $submission->deadline_on->format('j F Y') : 'Not stated' }}@if ($submission->deadline_note) <span class="text-muted">({{ $submission->deadline_note }})</span>@endif</dd></div>
        <div><dt class="font-semibold">Official page</dt><dd><a class="text-navy-700 underline" rel="noopener nofollow" target="_blank" href="{{ $submission->official_url }}">{{ $submission->official_url }}</a></dd></div>
        <div class="sm:col-span-2"><dt class="font-semibold">Summary</dt><dd class="whitespace-pre-line">{{ $submission->summary ?: '—' }}</dd></div>
        <div class="sm:col-span-2"><dt class="font-semibold">Eligibility</dt><dd class="whitespace-pre-line">{{ $submission->eligibility ?: '—' }}</dd></div>
    </dl>
    @can('update', $submission)
        <p class="mt-5"><a class="btn-primary rounded-lg" href="{{ route('partner.submissions.edit', $submission) }}">Edit and resend</a></p>
    @endcan
</x-layouts.partner>