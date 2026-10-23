@php($cycle = $plan->cycle)
<x-layouts.student :title="$cycle->label()">
    <p class="text-sm"><a class="text-navy-700 underline" href="{{ route('student.applications.index') }}">&larr; All plans</a></p>
    <h1 class="mt-1 text-2xl font-extrabold">{{ $cycle->label() }}</h1>
    <p class="mt-1 text-sm text-muted">Private plan. Tracking here does not mean anything was submitted to the scholarship provider.
        <a class="text-navy-700 underline" href="{{ route('scholarships.show', [$cycle->programme->slug, $cycle->slug]) }}">View public listing</a></p>

    @if ($case)
        <div class="mt-4 rounded-md border border-navy-300 bg-navy-50 p-4 text-sm">
            <strong>SAS is supporting this application.</strong> Status: {{ $case->statusLabel() }}.
            <a class="text-navy-700 underline" href="{{ route('student.cases.show', $case) }}">Open the case for fees, messages and sharing documents</a>
        </div>
    @endif

    @if ($changed)
        <div class="mt-4 rounded-md border border-warn/40 bg-warn-soft p-4 text-sm" role="alert">
            <strong>Requirements updated.</strong> The official requirements for this call have changed since you created your checklist.
            <form method="POST" action="{{ route('student.applications.acknowledge', $plan) }}" class="mt-2">@csrf<button class="btn-outline !py-1.5">Review and add the changes</button></form>
            <p class="mt-1 text-xs">Your completed items and notes will be kept.</p>
        </div>
    @endif

    <div class="mt-5 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <section class="card p-5" aria-labelledby="tasks">
                <div class="flex items-center justify-between"><h2 id="tasks" class="text-lg font-bold">Checklist</h2><p class="text-sm text-muted">{{ $progress['done'] }}/{{ $progress['total'] }} done</p></div>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($plan->tasks as $t)
                        <li class="flex items-start gap-3 py-3">
                            <form method="POST" action="{{ route('student.applications.tasks.update', [$plan, $t]) }}">@csrf @method('PATCH')
                                <input type="hidden" name="completed" value="{{ $t->completed_at ? 0 : 1 }}">
                                <button class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded border {{ $t->completed_at ? 'border-navy-700 bg-navy-700 text-white' : 'border-slate-400 bg-white' }}" aria-label="{{ $t->completed_at ? 'Mark not done' : 'Mark done' }}: {{ $t->title }}" aria-pressed="{{ $t->completed_at ? 'true' : 'false' }}">@if($t->completed_at)&#10003;@endif</button></form>
                            <div class="min-w-0 flex-1"><p class="{{ $t->completed_at ? 'text-muted line-through' : '' }}">{{ $t->title }}
                                @if ($t->requirement_updated)<span class="badge-warn">Updated requirement</span>@endif
                                @if ($t->is_custom)<span class="badge-neutral">My task</span>@endif</p>
                                @if ($t->due_at)<p class="text-xs text-muted">Due {{ $t->due_at->format('j M Y') }}</p>@endif</div>
                            @if ($t->is_custom)<form method="POST" action="{{ route('student.applications.tasks.destroy', [$plan, $t]) }}">@csrf @method('DELETE')<button class="text-xs text-alert underline" aria-label="Remove task {{ $t->title }}">Remove</button></form>@endif
                        </li>
                    @empty
                        <li class="py-3 text-sm text-muted">The requirements for this call are not confirmed yet. Add your own tasks below.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('student.applications.tasks.store', $plan) }}" class="mt-4 grid gap-2 sm:grid-cols-[1fr_160px_auto]">
                    @csrf
                    <div><label for="task-title" class="sr-only">New task</label><input id="task-title" name="title" required maxlength="180" placeholder="Add your own task" class="input"></div>
                    <div><label for="task-due" class="sr-only">Due date</label><input id="task-due" type="date" name="due_at" class="input"></div>
                    <button class="btn-outline">Add</button>
                </form>
            </section>

            <section class="card p-5" aria-labelledby="docs"><h2 id="docs" class="text-lg font-bold">Linked documents</h2>
                <p class="mt-1 text-xs text-muted">Linking is your choice and stays private to you in this release.</p>
                <ul class="mt-3 space-y-2 text-sm">@forelse ($attached as $a)
                    <li class="flex items-center justify-between gap-2"><span>{{ $a->document->title }} <span class="badge-neutral">{{ $a->document->currentVersion?->scan_status->label() }}</span></span>
                        <form method="POST" action="{{ route('student.applications.documents.detach', [$plan, $a->document]) }}">@csrf @method('DELETE')<button class="text-xs text-alert underline">Unlink</button></form></li>
                @empty<li class="text-muted">No documents linked.</li>@endforelse</ul>
                @if ($documents->isNotEmpty())
                    <form method="POST" action="{{ route('student.applications.documents.attach', $plan) }}" class="mt-3 flex gap-2">@csrf
                        <label for="doc" class="sr-only">Document</label><select id="doc" name="document" class="input">@foreach ($documents as $d)<option value="{{ $d->public_id }}">{{ $d->title }}</option>@endforeach</select><button class="btn-outline">Link</button></form>
                @else
                    <p class="mt-3 text-sm text-muted">Upload documents in <a class="text-navy-700 underline" href="{{ route('student.documents.index') }}">your document vault</a> first.</p>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="card p-5"><h2 class="text-lg font-bold">Deadline</h2><x-deadline :cycle="$cycle" class="mt-2" /></section>

            <section class="card p-5" aria-labelledby="status"><h2 id="status" class="text-lg font-bold">Status</h2>
                <p class="mt-1 text-sm"><span class="badge-green">{{ $plan->status->label() }}</span>
                    @if ($plan->status === \App\Enums\PlanStatus::Submitted || $plan->submitted_at) <span class="text-xs text-muted">{{ $plan->status_source === 'staff_confirmed' ? 'Confirmed by staff' : 'Reported by you' }}</span>@endif</p>
                @if ($plan->submitted_at)<p class="mt-1 text-xs text-muted">Reported submitted on {{ $plan->submitted_at->format('j M Y') }}@if($plan->submission_reference), ref. {{ $plan->submission_reference }}@endif</p>@endif

                <form method="POST" action="{{ route('student.applications.update', $plan) }}" class="mt-4 space-y-3" x-data="{ s: '{{ $plan->status->value }}' }">
                    @csrf @method('PATCH')
                    <div><label for="status-select" class="label">Change status</label>
                        <select id="status-select" name="status" x-model="s" class="input">@foreach (\App\Enums\PlanStatus::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                        @error('status')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                    <div x-show="s === 'submitted'" x-cloak class="space-y-3 rounded-md bg-ivory-deep p-3">
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm_submitted" value="1" class="mt-1 rounded border-slate-300 text-navy-700"><span>I confirm I submitted this application.</span></label>
                        @error('confirm_submitted')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        <div><label for="sub-at" class="label">Date submitted</label><input id="sub-at" type="date" name="submitted_at" max="{{ now()->toDateString() }}" class="input"></div>
                        <div><label for="sub-ref" class="label">Reference (optional)</label><input id="sub-ref" name="submission_reference" class="input"></div>
                        <div><label for="sub-ev" class="label">Note or evidence (optional)</label><input id="sub-ev" name="evidence_note" class="input"></div>
                    </div>
                    <div><label for="notes" class="label">Notes</label><textarea id="notes" name="notes" rows="3" class="input">{{ old('notes', $plan->notes) }}</textarea></div>
                    <button class="btn-primary w-full">Save</button>
                </form>
            </section>

            <form method="POST" action="{{ route('student.applications.destroy', $plan) }}" onsubmit="return confirm('Delete this plan and its checklist? This cannot be undone.')">@csrf @method('DELETE')<button class="btn-danger w-full">Delete plan</button></form>
        </aside>
    </div>
</x-layouts.student>
