<x-dynamic-component :component="$ctx->layout()" title="My application">
    <h1 class="text-[26px] font-normal uppercase tracking-wide text-[#555]">{{ $ctx->isAgent() ? 'Applications of '.$student->name : 'My application' }}</h1>
    <div class="mt-2 border-b-2 border-dotted border-[#ccc]" aria-hidden="true"></div>

    <nav class="mt-6 flex flex-wrap items-end gap-1 text-[14px]" aria-label="Filter applications">
        @foreach (['all' => 'All', 'unfinished' => 'Unfinished', 'finished' => 'Finished'] as $k => $l)
            <a href="{{ $ctx->route('applications.index', $k === 'all' ? [] : ['show' => $k]) }}" @if ($filter === $k) aria-current="page" @endif class="border border-b-0 px-7 py-2.5 {{ $filter === $k ? 'border-[#ccc] bg-white text-[#333]' : 'border-[#ddd] bg-[#f5f5f5] text-[#666] hover:text-[#333]' }}">{{ $l }}</a>
        @endforeach
        <a class="ml-auto mb-1 bg-[#0b4a8f] px-5 py-2 text-white hover:bg-[#093d75]" href="{{ $ctx->route('choose.index') }}">+ Add application</a>
    </nav>

    <div class="overflow-x-auto border border-[#ccc]">
        <table class="w-full text-left text-[14px]">
            <caption class="sr-only">Applications</caption>
            <thead><tr class="border-b border-[#333] bg-[#f5f5f5] text-[#333]"><th scope="col" class="px-4 py-3 font-bold">Application Number</th><th scope="col" class="px-4 py-3 font-bold">Apply for</th><th scope="col" class="px-4 py-3 font-bold">Status</th><th scope="col" class="px-4 py-3 font-bold">Last operation time</th><th scope="col" class="px-4 py-3 font-bold">Review Remarks</th><th scope="col" class="px-4 py-3 font-bold">Progress</th></tr></thead>
            <tbody>
            @forelse ($plans as $p)
                @php($d = $docs[$p->id])
                @php($case = $cases[$p->cycle_id] ?? null)
                <tr class="border-b border-[#e5e5e5] align-middle">
                    <td class="whitespace-nowrap bg-[#f5f5f5] px-4 py-5">{{ $p->application_no }}</td>
                    <td class="px-4 py-5"><a class="text-[#0b6ec7] hover:underline" href="{{ $ctx->route('applications.show', $p) }}">{{ $p->cycle->displayName() }}</a><br><span class="text-[#666]">{{ $p->degreeLevel?->name ?? ($p->cycle->degreeLevels->count() === 1 ? $p->cycle->degreeLevels->first()->name : 'Degree not chosen') }}</span></td>
                    <td class="px-4 py-5 {{ match ($p->stage) { 'passed' => 'text-[#1a1aff]', 'returned' => 'text-[#e00]', 'submitted' => 'text-[#d97706]', default => 'text-[#555]' } }}">{{ $p->statusFor($case) }}</td>
                    <td class="whitespace-nowrap px-4 py-5">{{ $p->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="max-w-xs whitespace-pre-line px-4 py-5 text-[#e00]">{{ $p->review_remarks }}</td>
                    <td class="px-4 py-5 text-[13px]">
                        <p>Form {{ $formPercent >= 100 ? 'completed' : 'not completed' }} <a class="text-[#0b6ec7] hover:underline" href="{{ $ctx->route('applications.form', $p) }}">View</a></p>
                        <p>Attachment {{ $d['missing'] ? 'not completed' : 'completed' }} <a class="text-[#0b6ec7] hover:underline" href="{{ $ctx->route('applications.materials', $p) }}">View</a></p>
                        <p><a class="text-[#0b6ec7] hover:underline" href="{{ $ctx->route('applications.preview', $p) }}" target="_blank" rel="noopener">Preview Application Form</a></p>
                        <p><a class="text-[#0b6ec7] hover:underline" href="{{ $ctx->route($p->isEditable() ? 'applications.submit' : 'applications.show', $p) }}">{{ $p->isEditable() ? 'Apply Submit' : 'View Details' }}</a></p>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-[#777]">No applications here yet. Press <strong>+ Add application</strong> to choose a scholarship.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <section class="mt-5 border border-[#ddd] bg-[#f5f5f5] p-5 text-[13px] leading-7 text-[#d9251d]" aria-labelledby="legend-h">
        <h2 id="legend-h" class="sr-only">What the statuses mean</h2>
        <p>"Unfinished", you are still filling the application in. Nothing has been sent to SAS.</p>
        <p>"Submitted for review", SAS is checking your form and documents.</p>
        <p>"Returned", something needs to be fixed. Follow the prompts in the "Review Remarks" column, fix it, then submit again.</p>
        <p>"Review passed", SAS found your application complete. SAS tells you the next step and its own status is shown after this.</p>
    </section>
</x-dynamic-component>
