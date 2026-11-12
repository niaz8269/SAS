{{-- The title, status, notices and the page bar of an application. Expects $plan, $cycle, $step (0 = none), $ctx. --}}
@php
    // The same names as Home and the menu. The first page is the degree and language of this application. No numbers here: the menu numbers the whole journey.
    $steps = [1 => ['Degree and language', 'applications.show'], 2 => ['Your details', 'applications.form'], 3 => ['Documents', 'applications.materials'], 4 => ['Send to SAS', 'applications.submit']];
    $notice = fn ($text, $tone = 'warn') => '<div class="mt-5 flex items-start gap-3 rounded-xl border p-4 text-base '.($tone === 'bad' ? 'border-red-300 bg-red-50 text-red-900' : ($tone === 'good' ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-gold bg-gold/10 text-ink')).'" role="alert"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-700 text-sm font-bold text-white" aria-hidden="true">!</span><div>'.$text.'</div></div>';
    $statusTone = match ($plan->stage) { 'passed' => 'text-emerald-800', 'returned' => 'text-red-700', 'submitted' => 'text-navy-700', default => 'text-ink' };
@endphp
<div>
    <h1 class="text-2xl font-extrabold">{{ $cycle->displayName() }}</h1>
    <p class="mt-1 text-base text-muted">Application number {{ $plan->application_no }}@if ($ctx->isAgent()) &middot; for {{ $student->name }}@endif &middot; Status: <strong class="{{ $statusTone }}">{{ $plan->applicantLabel() }}</strong>
        @if ($plan->stage === 'passed') &middot; <a class="text-navy-700 underline" href="{{ $ctx->route('applications.journey', $plan) }}">What happens next: {{ app(\App\Domain\Journey\Journey::class)->label(app(\App\Domain\Journey\Journey::class)->currentStage($plan)) }}</a>@endif</p>
    @if ($plan->isEditable())<p class="mt-1 text-base text-muted">You are preparing this application. <strong>Nothing goes to the scholarship from here.</strong> When it is ready you press <strong>Send to SAS</strong>, and SAS checks it first.</p>@endif
</div>

@if ($plan->stage === 'returned')
    {!! $notice('<strong>SAS returned this application.</strong> Please fix the following, then press Send to SAS again on the last page.<p class="mt-2 whitespace-pre-line">'.e($plan->review_remarks).'</p>', 'bad') !!}
@elseif ($plan->stage === 'submitted')
    {!! $notice('<strong>Sent to SAS</strong> on '.e($plan->review_requested_at?->format('j M Y')).'. SAS is checking it. You cannot change it until SAS passes it or sends it back.') !!}
@elseif ($plan->stage === 'passed')
    {!! $notice('<strong>SAS passed this application.</strong> '.e((string) $plan->review_remarks).($case ? ' SAS status: '.e($case->statusLabel()).'.' : ''), 'good') !!}
@endif

@if ($step > 0)
    @php
        // A tick means the work of that page is really done (the same checks decide whether the application can be sent); an application already with SAS has all of them.
        $parts = $plan->isEditable() ? app(\App\Domain\StudentWorkspace\ApplicationReview::class)->parts($plan) : ['level' => null, 'language' => null, 'details' => null, 'documents' => null];
        $done = [1 => $parts['level'] === null && $parts['language'] === null, 2 => $parts['details'] === null, 3 => $parts['documents'] === null, 4 => ! $plan->isEditable()];
    @endphp
    <ol class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Pages of this application">
        @foreach ($steps as $n => [$label, $route])
            <li><a href="{{ $ctx->route($route, $plan) }}" @if ($step === $n) aria-current="step" @endif
                   class="flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition {{ $step === $n ? 'border-navy-700 bg-navy-700 text-white' : ($done[$n] ? 'border-navy-200 bg-navy-50 text-navy-900 hover:bg-navy-100' : 'border-line bg-white text-ink hover:bg-navy-50') }}">
                @if ($done[$n])<span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $step === $n ? 'bg-white text-navy-800' : 'bg-navy-700 text-white' }} text-xs" aria-hidden="true">✓</span><span class="sr-only">(done) </span>@endif{{ $label }}</a></li>
        @endforeach
    </ol>
@endif
