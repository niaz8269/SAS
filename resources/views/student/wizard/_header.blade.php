{{-- The title, notices and the four-step bar of an application. Expects $plan, $cycle, $step (0 = none), $ctx. --}}
@php
    $steps = [1 => ['Apply Now', 'applications.show'], 2 => ['Application Form', 'applications.form'], 3 => ['Upload Materials', 'applications.materials'], 4 => ['Apply Submit', 'applications.submit']];
    $notice = fn ($text) => '<div class="mx-auto mt-5 flex max-w-4xl items-start gap-3 border border-[#f0b46a] bg-[#fdebd0] p-4 text-[0.875rem] text-[#b4530b]" role="alert"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f08a24] text-sm font-bold text-white" aria-hidden="true">!</span><div>'.$text.'</div></div>';
@endphp
<div class="text-center">
    <h1 class="text-[1.25rem] font-normal text-[#0b6ec7] sm:text-[1.375rem]">The program you are applying for is {{ $cycle->displayName() }}</h1>
    <p class="mt-1 text-[0.8125rem] text-[#777]">Application number {{ $plan->application_no }}@if ($ctx->isAgent()) &middot; for {{ $student->name }}@endif</p>
</div>
<p class="mt-1 text-center text-[0.8125rem]"><a class="text-[#428bb6] underline" href="{{ $ctx->route('applications.journey', $plan) }}">Journey: {{ app(\App\Domain\Journey\Journey::class)->label(app(\App\Domain\Journey\Journey::class)->currentStage($plan)) }}</a></p>
<div class="mx-auto mt-3 border-b border-[#cfd8e3]" aria-hidden="true"></div>

@if ($plan->stage === 'returned')
    {!! $notice('<strong>SAS returned this application.</strong> Please fix the following and submit it again in the last step.<p class="mt-2 whitespace-pre-line">'.e($plan->review_remarks).'</p>') !!}
@elseif ($plan->stage === 'submitted')
    {!! $notice('<strong>Submitted for review</strong> on '.e($plan->review_requested_at?->format('j M Y')).'. SAS is checking it. You cannot change it until SAS passes it or returns it.') !!}
@elseif ($plan->stage === 'passed')
    {!! $notice('<strong>Review passed.</strong> '.e((string) $plan->review_remarks).($case ? ' SAS status: '.e($case->statusLabel()).'.' : '')) !!}
@endif

@if ($step > 0)
    <ol class="mx-auto mt-6 grid max-w-3xl grid-cols-2 gap-1 sm:grid-cols-4" aria-label="Application steps">
        @foreach ($steps as $n => [$label, $route])
            <li><a href="{{ $ctx->route($route, $plan) }}" @if ($step === $n) aria-current="step" @endif
                   class="flex h-10 items-center justify-center gap-2 px-6 text-[0.8125rem] font-semibold text-white transition [clip-path:polygon(0_0,calc(100%-0.75rem)_0,100%_50%,calc(100%-0.75rem)_100%,0_100%,0.75rem_50%)] sm:first:[clip-path:polygon(0_0,calc(100%-0.75rem)_0,100%_50%,calc(100%-0.75rem)_100%,0_100%)] {{ $step === $n ? 'bg-[#0b4a8f]' : ($step > $n ? 'bg-[#2c5f9e]' : 'bg-[#6c8fbd] hover:bg-[#4f7bb0]') }}">
                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[0.6875rem] text-[#0b4a8f]" aria-hidden="true">{{ $step > $n ? '✓' : $n }}</span>{{ $label }}</a></li>
        @endforeach
    </ol>
@endif
