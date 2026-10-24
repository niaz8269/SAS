<x-dynamic-component :component="$ctx->layout()" :title="$cycle->displayName()">
    @include('student.wizard._header')

    <div class="mx-auto mt-6 max-w-4xl space-y-4">
        @php($forms = app(\App\Domain\Applications\ApplicationFormService::class))
        @php($formMissing = $forms->missing($forms->formFor($student)))
        @php($docs = app(\App\Domain\Documents\Requirements::class)->progress(app(\App\Domain\Documents\Requirements::class)->forPlan($plan)))
        <section class="border border-[#ddd] bg-white" aria-labelledby="sum-h">
            <h2 id="sum-h" class="border-b border-[#ddd] bg-[#ececec] px-3 py-2 text-[0.9375rem] font-bold">Apply Submit: check everything before you submit</h2>
            <ul class="divide-y divide-[#eee] text-[0.875rem]">
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"><span><strong>Scholarship, degree and language</strong><br><span class="text-[#666]">{{ $cycle->displayName() }}@if ($plan->degreeLevel) &middot; {{ $plan->degreeLevel->name }}@endif @if ($plan->study_language) &middot; {{ $plan->study_language }}@endif</span></span><a class="text-[#428bb6] underline" href="{{ $ctx->route('applications.show', $plan) }}">Apply Now</a></li>
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"><span><strong>Application Form</strong> {!! $formMissing ? '<span class="font-semibold text-[#d97706]">'.count($formMissing).' to answer</span>' : '<span class="font-semibold text-[#2f8f4e]">✓ Complete</span>' !!}<br><span class="text-[#666]">{{ $formPercent }}% of the required answers are filled in.</span></span>
                    <span class="flex gap-4"><a class="text-[#428bb6] underline" href="{{ $ctx->route('applications.download', $plan) }}">Download Application Form</a><a class="text-[#428bb6] underline" href="{{ $ctx->route('applications.preview', $plan) }}" target="_blank" rel="noopener">Preview</a><a class="text-[#428bb6] underline" href="{{ $ctx->route('applications.form', $plan) }}">Application Form</a></span></li>
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"><span><strong>Upload Materials</strong> {!! $docs['missing'] ? '<span class="font-semibold text-[#d97706]">'.count($docs['missing']).' missing</span>' : '<span class="font-semibold text-[#2f8f4e]">✓ Done</span>' !!}<br><span class="text-[#666]">{{ $docs['ready'] }} of {{ $docs['required'] }} mandatory documents provided @if ($docs['excused']) ({{ $docs['excused'] }} with a reason)@endif.</span></span><a class="text-[#428bb6] underline" href="{{ $ctx->route('applications.materials', $plan) }}">Upload Materials</a></li>
            </ul>
        </section>

        @if ($plan->isEditable())
            @if ($problems)
                <div class="border border-[#f0b46a] bg-[#fdebd0] p-4 text-[0.875rem] text-[#b4530b]" role="alert">
                    <p class="font-semibold">Not ready to submit yet:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
                </div>
            @endif
            <form method="POST" action="{{ $ctx->route('applications.submit.store', $plan) }}" class="flex flex-wrap items-center justify-center gap-3 pt-2 text-[0.9375rem]">
                @csrf
                <a class="border border-[#bbb] bg-white px-8 py-2.5 hover:bg-[#f5f5f5]" href="{{ $ctx->route('applications.materials', $plan) }}">Previous</a>
                <button class="bg-[#0b4a8f] px-10 py-2.5 font-semibold text-white hover:bg-[#093d75] {{ $problems ? 'cursor-not-allowed opacity-50' : '' }}" @disabled($problems)>{{ $plan->stage === 'returned' ? 'Submit again' : 'Submit' }}</button>
            </form>
            <p class="text-center text-[0.75rem] text-[#777]">SAS reviews it and either passes it or returns it with remarks. Submitting costs nothing.</p>
        @endif
    </div>
</x-dynamic-component>
