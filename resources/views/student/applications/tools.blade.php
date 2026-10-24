<x-dynamic-component :component="$ctx->layout()" title="Notes and tracker">
    @include('student.wizard._header')
    <h2 class="mt-5 border-b border-[#cfd8e3] pb-2 text-[1.125rem] text-[#0b6ec7]">Notes, deadlines and tracker</h2>
    <p class="mt-1 text-sm text-[#777]">Optional tools: a personal checklist, the deadline, your own notes and linked documents. The four steps above are all you need to apply.</p>

    @if ($changed)
        <div class="mt-4 rounded-md border border-warn/40 bg-warn-soft p-4 text-sm" role="alert">
            <strong>Requirements updated.</strong> The official requirements for this call have changed since you created your checklist.
            <form method="POST" action="{{ $ctx->route('applications.acknowledge', $plan) }}" class="mt-2">@csrf<button class="border border-[#bbb] bg-white px-4 py-1.5 hover:bg-[#f5f5f5]">Review and add the changes</button></form>
        </div>
    @endif
    <div class="mt-5 grid gap-6 lg:grid-cols-[1fr_21.25rem]">
        <div class="space-y-6">
            <section class="border border-[#ddd] bg-white p-4 text-[0.8125rem]" aria-labelledby="tasks">
                <div class="flex items-center justify-between"><h2 id="tasks" class="text-[0.9375rem] font-bold">Checklist</h2><p class="text-sm text-[#777]">{{ $progress['done'] }}/{{ $progress['total'] }} done</p></div>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($plan->tasks as $t)
                        <li class="flex items-start gap-3 py-3">
                            <form method="POST" action="{{ $ctx->route('applications.tasks.update', [$plan, $t]) }}">@csrf @method('PATCH')
                                <input type="hidden" name="completed" value="{{ $t->completed_at ? 0 : 1 }}">
                                <button class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded border {{ $t->completed_at ? 'border-[#0b4a8f] bg-[#0b4a8f] text-white' : 'border-slate-400 bg-white' }}" aria-label="{{ $t->completed_at ? 'Mark not done' : 'Mark done' }}: {{ $t->title }}" aria-pressed="{{ $t->completed_at ? 'true' : 'false' }}">@if($t->completed_at)&#10003;@endif</button></form>
                            <div class="min-w-0 flex-1"><p class="{{ $t->completed_at ? 'text-[#777] line-through' : '' }}">{{ $t->title }}
                                @if ($t->requirement_updated)<span class="ml-1 inline-block bg-[#fdebd0] px-2 py-0.5 text-[0.6875rem] text-[#b4530b]">Updated requirement</span>@endif
                                @if ($t->is_custom)<span class="ml-1 inline-block bg-[#eee] px-2 py-0.5 text-[0.6875rem] text-[#555]">My task</span>@endif</p>
                                @if ($t->due_at)<p class="text-xs text-[#777]">Due {{ $t->due_at->format('j M Y') }}</p>@endif</div>
                            @if ($t->is_custom)<form method="POST" action="{{ $ctx->route('applications.tasks.destroy', [$plan, $t]) }}">@csrf @method('DELETE')<button class="text-xs text-[#d9534f] underline" aria-label="Remove task {{ $t->title }}">Remove</button></form>@endif
                        </li>
                    @empty
                        <li class="py-3 text-sm text-[#777]">The requirements for this call are not confirmed yet. Add your own tasks below.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ $ctx->route('applications.tasks.store', $plan) }}" class="mt-4 grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
                    @csrf
                    <div><label for="task-title" class="sr-only">New task</label><input id="task-title" name="title" required maxlength="180" placeholder="Add your own task" class="w-full border border-[#ccc] bg-white px-3 py-2"></div>
                    <div><label for="task-due" class="sr-only">Due date</label><input id="task-due" type="date" name="due_at" class="w-full border border-[#ccc] bg-white px-3 py-2"></div>
                    <button class="border border-[#bbb] bg-white px-4 py-2 hover:bg-[#f5f5f5]">Add</button>
                </form>
            </section>

            <section class="border border-[#ddd] bg-white p-4 text-[0.8125rem]" aria-labelledby="docs"><h2 id="docs" class="text-[0.9375rem] font-bold">Linked documents</h2>
                <p class="mt-1 text-xs text-[#777]">Linking is your choice and stays private to you in this release.</p>
                <ul class="mt-3 space-y-2 text-sm">@forelse ($attached as $a)
                    <li class="flex items-center justify-between gap-2"><span>{{ $a->document->title }} <span class="ml-1 inline-block bg-[#eee] px-2 py-0.5 text-[0.6875rem] text-[#555]">{{ $a->document->currentVersion?->scan_status->label() }}</span></span>
                        <form method="POST" action="{{ $ctx->route('applications.documents.detach', [$plan, $a->document]) }}">@csrf @method('DELETE')<button class="text-xs text-[#d9534f] underline">Unlink</button></form></li>
                @empty<li class="text-[#777]">No documents linked.</li>@endforelse</ul>
                @if ($documents->isNotEmpty())
                    <form method="POST" action="{{ $ctx->route('applications.documents.attach', $plan) }}" class="mt-3 flex gap-2">@csrf
                        <label for="doc" class="sr-only">Document</label><select id="doc" name="document" class="w-full border border-[#ccc] bg-white px-3 py-2">@foreach ($documents as $d)<option value="{{ $d->public_id }}">{{ $d->title }}</option>@endforeach</select><button class="border border-[#bbb] bg-white px-4 py-2 hover:bg-[#f5f5f5]">Link</button></form>
                @else
                    <p class="mt-3 text-sm text-[#777]">Upload documents in {!! $ctx->isAgent() ? 'the Upload Materials step' : '<a class="text-[#428bb6] underline" href="'.e($ctx->route('documents.index')).'">your document vault</a>' !!} first.</p>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="border border-[#ddd] bg-white p-4 text-[0.8125rem]"><h2 class="text-[0.9375rem] font-bold">Deadline</h2><x-deadline :cycle="$cycle" class="mt-2" /></section>

            <section class="border border-[#ddd] bg-white p-4 text-[0.8125rem]" aria-labelledby="status"><h2 id="status" class="text-[0.9375rem] font-bold">Status</h2>
                <p class="mt-1 text-sm"><span class="inline-block bg-[#e3f1e6] px-2 py-0.5 text-[0.75rem] text-[#2f8f4e]">{{ $plan->status->label() }}</span>
                    @if ($plan->status === \App\Enums\PlanStatus::Submitted || $plan->submitted_at) <span class="text-xs text-[#777]">{{ $plan->status_source === 'staff_confirmed' ? 'Confirmed by staff' : 'Reported by you' }}</span>@endif</p>
                @if ($plan->submitted_at)<p class="mt-1 text-xs text-[#777]">Reported submitted on {{ $plan->submitted_at->format('j M Y') }}@if($plan->submission_reference), ref. {{ $plan->submission_reference }}@endif</p>@endif

                <form method="POST" action="{{ $ctx->route('applications.update', $plan) }}" class="mt-4 space-y-3" x-data="{ s: '{{ $plan->status->value }}' }">
                    @csrf @method('PATCH')
                    <div><label for="status-select" class="mb-1 block text-[#666]">Change status</label>
                        <select id="status-select" name="status" x-model="s" class="w-full border border-[#ccc] bg-white px-3 py-2">@foreach (\App\Enums\PlanStatus::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                        @error('status')<p class="mt-1 text-[#d9534f]" role="alert">{{ $message }}</p>@enderror</div>
                    <div x-show="s === 'submitted'" x-cloak class="space-y-3 rounded-md bg-[#f5f5f5] p-3">
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm_submitted" value="1" class="mt-1 rounded border-slate-300 text-[#0b4a8f]"><span>I confirm I submitted this application.</span></label>
                        @error('confirm_submitted')<p class="mt-1 text-[#d9534f]" role="alert">{{ $message }}</p>@enderror
                        <div><label for="sub-at" class="mb-1 block text-[#666]">Date submitted</label><input id="sub-at" type="date" name="submitted_at" max="{{ now()->toDateString() }}" class="w-full border border-[#ccc] bg-white px-3 py-2"></div>
                        <div><label for="sub-ref" class="mb-1 block text-[#666]">Reference (optional)</label><input id="sub-ref" name="submission_reference" class="w-full border border-[#ccc] bg-white px-3 py-2"></div>
                        <div><label for="sub-ev" class="mb-1 block text-[#666]">Note or evidence (optional)</label><input id="sub-ev" name="evidence_note" class="w-full border border-[#ccc] bg-white px-3 py-2"></div>
                    </div>
                    <div><label for="notes" class="mb-1 block text-[#666]">Notes</label><textarea id="notes" name="notes" rows="3" class="w-full border border-[#ccc] bg-white px-3 py-2">{{ old('notes', $plan->notes) }}</textarea></div>
                    <button class="w-full bg-[#0b4a8f] px-4 py-2.5 font-semibold text-white hover:bg-[#093d75]">Save</button>
                </form>
            </section>

            <form method="POST" action="{{ $ctx->route('applications.destroy', $plan) }}" onsubmit="return confirm('Delete this plan and its checklist? This cannot be undone.')">@csrf @method('DELETE')<button class="w-full border border-[#d9534f] bg-white px-4 py-2.5 text-[#d9534f] hover:bg-[#fdecea]">Delete plan</button></form>
        </aside>
    </div>
</x-dynamic-component>
