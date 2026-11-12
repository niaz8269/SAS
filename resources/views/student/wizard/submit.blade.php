<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')

    <div class="mt-6 space-y-4">
        @php($forms = app(\App\Domain\Applications\ApplicationFormService::class))
        @php($formMissing = $forms->missing($forms->formFor($student)))
        @php($docs = app(\App\Domain\Documents\Requirements::class)->progress(app(\App\Domain\Documents\Requirements::class)->forPlan($plan)))
        <section class="card" aria-labelledby="sum-h">
            <h2 id="sum-h" class="border-b border-line px-4 py-3 text-lg font-bold">Send to SAS: check everything first</h2>
            <ul class="divide-y divide-line text-base">
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"><span><strong>Scholarship, degree and language</strong><br><span class="text-muted">{{ $cycle->displayName() }}@if ($plan->degreeLevel) &middot; {{ $plan->degreeLevel->name }}@endif @if ($plan->study_language) &middot; {{ $plan->study_language }}@endif</span></span><a class="text-navy-700 underline" href="{{ $ctx->route('applications.show', $plan) }}">Change</a></li>
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"><span><strong>Your details</strong> {!! $formMissing ? '<span class="font-semibold text-alert">'.count($formMissing).' to answer</span>' : '<span class="font-semibold text-emerald-800">✓ Complete</span>' !!}<br><span class="text-muted">{{ $formPercent }}% of the required answers are filled in.</span></span>
                    <span class="flex flex-wrap gap-4"><a class="text-navy-700 underline" href="{{ $ctx->route('applications.download', $plan) }}">Download Application Form</a><a class="text-navy-700 underline" href="{{ $ctx->route('applications.preview', $plan) }}" target="_blank" rel="noopener">Preview</a><a class="text-navy-700 underline" href="{{ $ctx->route('applications.form', $plan) }}">Open your details</a></span></li>
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"><span><strong>Documents</strong> {!! $docs['missing'] ? '<span class="font-semibold text-alert">'.count($docs['missing']).' missing</span>' : '<span class="font-semibold text-emerald-800">✓ Done</span>' !!}<br><span class="text-muted">{{ $docs['ready'] }} of {{ $docs['required'] }} mandatory documents provided{{ $docs['excused'] ? ' ('.$docs['excused'].' with a reason)' : '' }}.</span></span><a class="text-navy-700 underline" href="{{ $ctx->route('applications.materials', $plan) }}">Open documents</a></li>
            </ul>
        </section>

        @if ($plan->isEditable())
            @if ($problems)
                <div class="rounded-xl border border-gold bg-gold/10 p-4 text-base text-ink" role="alert">
                    <p class="font-semibold">Not ready to submit yet:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="card p-4 text-base">
                <p class="font-semibold">What happens when you press Send to SAS</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-muted">
                    <li>SAS checks your details and documents. It costs nothing.</li>
                    <li>SAS either <strong>passes</strong> the application or <strong>sends it back</strong> with notes. You see the answer here.</li>
                    <li>Nothing goes to the scholarship until SAS has passed it. While SAS checks it, you cannot change it.</li>
                </ol>
            </div>
            <form method="POST" action="{{ $ctx->route('applications.submit.store', $plan) }}" class="flex flex-wrap items-center justify-between gap-3">
                @csrf
                <a class="btn-outline" href="{{ $ctx->route('applications.materials', $plan) }}">Previous</a>
                <button class="btn-primary {{ $problems ? 'cursor-not-allowed opacity-50' : '' }}" @disabled($problems)>{{ $plan->stage === 'returned' ? 'Send to SAS again' : 'Send to SAS' }}</button>
            </form>
        @endif
    </div>
</x-dynamic-component>
