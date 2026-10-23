<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')
    <h2 class="mt-5 text-lg font-bold">Step 4: Apply submit</h2>

    <section class="card mt-3 p-5" aria-labelledby="sum-h">
        <h3 id="sum-h" class="font-bold">Check everything before you submit</h3>
        @php($forms = app(\App\Domain\Applications\ApplicationFormService::class))
        @php($formMissing = $forms->missing($forms->formFor($student)))
        @php($docs = app(\App\Domain\Documents\Requirements::class)->progress(app(\App\Domain\Documents\Requirements::class)->forPlan($plan)))
        <ul class="mt-3 divide-y divide-line text-sm">
            <li class="flex flex-wrap items-center justify-between gap-2 py-3"><span><strong>Scholarship, degree and language</strong><br><span class="text-muted">{{ $cycle->displayName() }}@if ($plan->degreeLevel) &middot; {{ $plan->degreeLevel->name }}@endif @if ($plan->study_language) &middot; {{ $plan->study_language }}@endif</span></span><a class="text-navy-700 underline" href="{{ $ctx->route('applications.show', $plan) }}">Step 1</a></li>
            <li class="flex flex-wrap items-center justify-between gap-2 py-3"><span><strong>Application form</strong> {!! $formMissing ? '<span class="badge-warn">'.count($formMissing).' to answer</span>' : '<span class="badge-green">✓ Complete</span>' !!}<br><span class="text-muted">{{ $formPercent }}% of the required answers are filled in.</span></span>
                <span class="flex gap-3"><a class="text-navy-700 underline" href="{{ $ctx->route('applications.preview', $plan) }}" target="_blank" rel="noopener">Preview application form</a><a class="text-navy-700 underline" href="{{ $ctx->route('applications.form', $plan) }}">Step 2</a></span></li>
            <li class="flex flex-wrap items-center justify-between gap-2 py-3"><span><strong>Documents</strong> {!! $docs['missing'] ? '<span class="badge-warn">'.count($docs['missing']).' missing</span>' : '<span class="badge-green">✓ Done</span>' !!}<br><span class="text-muted">{{ $docs['ready'] }} of {{ $docs['required'] }} mandatory documents provided @if ($docs['excused']) ({{ $docs['excused'] }} with a reason)@endif.</span></span><a class="text-navy-700 underline" href="{{ $ctx->route('applications.materials', $plan) }}">Step 3</a></li>
        </ul>
    </section>

    @if ($plan->isEditable())
        @if ($problems)
            <div class="mt-4 rounded-lg border border-gold/60 bg-gold-soft p-4 text-sm" role="alert">
                <p class="font-semibold">Not ready to submit yet:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="POST" action="{{ $ctx->route('applications.submit.store', $plan) }}" class="mt-5 flex flex-wrap items-center justify-between gap-3">
            @csrf
            <a class="btn-outline" href="{{ $ctx->route('applications.materials', $plan) }}">Previous</a>
            <div class="text-right">
                <button class="btn-primary {{ $problems ? 'cursor-not-allowed opacity-50' : '' }}" @disabled($problems)>{{ $plan->stage === 'returned' ? 'Submit again' : 'Submit application' }}</button>
                <p class="mt-1 text-xs text-muted">SAS reviews it and either passes it or returns it with remarks. Submitting costs nothing.</p>
            </div>
        </form>
    @endif
</x-dynamic-component>
