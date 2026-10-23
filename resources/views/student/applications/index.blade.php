<x-dynamic-component :component="$ctx->layout()" title="My applications">
    <h1 class="text-2xl font-extrabold">{{ $ctx->isAgent() ? 'Applications of '.$student->name : 'My applications' }}</h1>
    <p class="mt-1 text-sm text-muted">Each application has four steps: apply, application form, upload materials, submit. Open one to continue.</p>

    <nav class="mt-4 flex flex-wrap gap-2 text-sm" aria-label="Filter applications">
        @foreach (['all' => 'All', 'unfinished' => 'Unfinished', 'finished' => 'Finished'] as $k => $l)
            <a href="{{ $ctx->route('applications.index', $k === 'all' ? [] : ['show' => $k]) }}" @if ($filter === $k) aria-current="page" @endif class="rounded-t-lg border px-4 py-2 font-medium {{ $filter === $k ? 'border-navy-700 bg-white text-navy-900' : 'border-line bg-ivory-deep text-muted hover:text-ink' }}">{{ $l }}</a>
        @endforeach
        <a class="btn-primary ml-auto !py-1.5" href="{{ $ctx->route('choose.index') }}">+ Add application</a>
    </nav>

    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Applications</caption>
            <thead><tr class="border-b border-line text-muted"><th scope="col" class="px-4 py-3">Application number</th><th scope="col" class="px-4 py-3">Apply for</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Last operation time</th><th scope="col" class="px-4 py-3">Review remarks</th><th scope="col" class="px-4 py-3">Progress</th></tr></thead>
            <tbody>
            @forelse ($plans as $p)
                @php($d = $docs[$p->id])
                @php($case = $cases[$p->cycle_id] ?? null)
                <tr class="border-b border-line/60 align-top">
                    <td class="px-4 py-4 whitespace-nowrap">{{ $p->application_no }}</td>
                    <td class="px-4 py-4"><a class="font-medium text-navy-700 hover:underline" href="{{ $ctx->route('applications.show', $p) }}">{{ $p->cycle->displayName() }}</a><br><span class="text-muted">{{ $p->degreeLevel?->name ?? ($p->cycle->degreeLevels->count() === 1 ? $p->cycle->degreeLevels->first()->name : 'Degree not chosen') }}</span></td>
                    <td class="px-4 py-4"><span class="{{ match ($p->stage) { 'passed' => 'badge-green', 'returned' => 'badge-alert', 'submitted' => 'badge-warn', default => 'badge-neutral' } }}">{{ $p->statusFor($case) }}</span></td>
                    <td class="px-4 py-4 whitespace-nowrap">{{ $p->updated_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-4 max-w-xs whitespace-pre-line text-alert">{{ $p->review_remarks }}</td>
                    <td class="px-4 py-4">
                        <ul class="space-y-0.5">
                            <li>Form {{ $formPercent >= 100 ? 'completed' : 'not completed' }} <a class="text-navy-700 underline" href="{{ $ctx->route('applications.form', $p) }}">View</a></li>
                            <li>Attachments {{ $d['missing'] ? 'not completed' : 'completed' }} <a class="text-navy-700 underline" href="{{ $ctx->route('applications.materials', $p) }}">View</a></li>
                            <li><a class="text-navy-700 underline" href="{{ $ctx->route('applications.preview', $p) }}" target="_blank" rel="noopener">Preview application form</a></li>
                            <li><a class="font-medium text-navy-700 underline" href="{{ $ctx->route($p->isEditable() ? 'applications.submit' : 'applications.show', $p) }}">{{ $p->isEditable() ? 'Apply submit' : 'View details' }}</a></li>
                        </ul>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-muted">No applications here yet. Press <strong>+ Add application</strong> to choose a scholarship.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <section class="card mt-5 p-4 text-sm" aria-labelledby="legend-h">
        <h2 id="legend-h" class="font-bold">What the statuses mean</h2>
        <dl class="mt-2 space-y-1 text-muted">
            <div><dt class="inline font-semibold text-ink">Unfinished:</dt> <dd class="inline">you are still filling it in. Nothing has been sent to SAS.</dd></div>
            <div><dt class="inline font-semibold text-ink">Submitted for review:</dt> <dd class="inline">SAS is checking the form and documents.</dd></div>
            <div><dt class="inline font-semibold text-ink">Returned:</dt> <dd class="inline">something needs to be fixed. Follow the remarks, then submit again.</dd></div>
            <div><dt class="inline font-semibold text-ink">Review passed:</dt> <dd class="inline">SAS found it complete. SAS tells you the next step and shows its own status after this.</dd></div>
        </dl>
    </section>
</x-dynamic-component>
