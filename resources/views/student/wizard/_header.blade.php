{{-- Title, status and the four-step bar of an application. Expects $plan, $cycle, $step (0 = none), $ctx. --}}
@php
    $steps = [1 => ['Apply now', 'applications.show'], 2 => ['Application form', 'applications.form'], 3 => ['Upload materials', 'applications.materials'], 4 => ['Apply submit', 'applications.submit']];
@endphp
<p class="text-sm"><a class="text-navy-700 underline" href="{{ $ctx->route('applications.index') }}">&larr; My applications</a></p>
<h1 class="mt-2 text-xl font-extrabold sm:text-2xl">The scholarship you are applying for is {{ $cycle->displayName() }}</h1>
<p class="mt-1 text-sm text-muted">Application number {{ $plan->application_no }}@if ($ctx->isAgent()) &middot; for {{ $student->name }}@endif</p>

@if ($plan->stage === 'returned')
    <div class="mt-4 rounded-lg border border-alert bg-alert-soft p-4 text-sm" role="alert"><strong>SAS returned this application.</strong> Please fix the following and submit it again in step 4.<p class="mt-2 whitespace-pre-line">{{ $plan->review_remarks }}</p></div>
@elseif ($plan->stage === 'submitted')
    <div class="mt-4 rounded-lg border border-navy-300 bg-navy-50 p-4 text-sm"><strong>Submitted for review</strong> on {{ $plan->review_requested_at?->format('j M Y') }}. SAS is checking it. You cannot change it until SAS passes it or returns it.</div>
@elseif ($plan->stage === 'passed')
    <div class="mt-4 rounded-lg border border-navy-300 bg-navy-50 p-4 text-sm"><strong>Review passed.</strong> @if ($plan->review_remarks)<span class="whitespace-pre-line">{{ $plan->review_remarks }}</span>@endif @if ($case) SAS status: {{ $case->statusLabel() }}.@endif</div>
@endif

@if ($step > 0)
    <ol class="mt-5 grid gap-2 sm:grid-cols-4" aria-label="Application steps">
        @foreach ($steps as $n => [$label, $route])
            <li><a href="{{ $ctx->route($route, $plan) }}" @if ($step === $n) aria-current="step" @endif
                   class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition {{ $step === $n ? 'border-navy-700 bg-navy-700 text-white' : ($step > $n ? 'border-navy-300 bg-navy-50 text-navy-900' : 'border-line bg-white text-muted hover:border-navy-300') }}">
                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs {{ $step === $n ? 'bg-white text-navy-800' : 'bg-navy-100 text-navy-800' }}" aria-hidden="true">{{ $step > $n ? '✓' : $n }}</span>{{ $label }}</a></li>
        @endforeach
    </ol>
@endif
