@php
    $steps = ['new' => 'Opened', 'in_progress' => 'In progress', 'submitted' => 'Submitted', 'accepted' => 'Accepted'];
    $order = array_keys($steps);
    $at = array_search($case->status === 'rejected' ? 'submitted' : $case->status, $order, true); // a rejected case went through every step up to submission
    $ended = in_array($case->status, ['rejected', 'withdrawn'], true);
@endphp
<x-layouts.student :title="$case->title">
    <p class="text-sm"><a class="text-navy-700 underline" href="{{ route('student.cases.index') }}">&larr; Messages and fees</a></p>
    <h1 class="mt-2 text-2xl font-extrabold">{{ $case->title }}</h1>
    <p class="mt-1 text-sm text-muted">Counsellor: {{ $case->advisor?->name ?? 'Being assigned' }} &middot; opened {{ $case->opened_at?->format('j M Y') }}</p>

    <section class="card mt-5 p-5" aria-labelledby="status-h">
        <h2 id="status-h" class="font-bold">Where it stands: {{ $case->statusLabel() }}</h2>
        <ol class="mt-3 flex flex-wrap gap-2 text-sm">
            @foreach ($steps as $key => $label)
                @php($done = $at !== false && array_search($key, $order, true) <= $at && (! $ended || $case->status === 'rejected'))
                <li class="rounded-full border px-3 py-1 {{ $done ? 'border-navy-700 bg-navy-700 text-white' : 'border-line bg-white text-muted' }}">{{ $label }}</li>
            @endforeach
            @if ($ended)<li class="rounded-full border border-alert bg-alert-soft px-3 py-1 text-alert">{{ $case->statusLabel() }}</li>@endif
        </ol>
        <p class="mt-3 text-xs text-muted">SAS does not guarantee selection, admission or a visa decision. The funder or university decides.</p>
    </section>

    <section class="card mt-5 p-5" aria-labelledby="fees-h">
        <h2 id="fees-h" class="font-bold">Fees</h2>
        <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">
            <caption class="sr-only">Fees for this case</caption>
            <thead><tr class="border-b border-line text-muted"><th scope="col" class="py-2 pr-3">Fee</th><th scope="col" class="py-2 pr-3">Amount</th><th scope="col" class="py-2 pr-3">Status</th><th scope="col" class="py-2">Refund</th></tr></thead>
            <tbody>
            @foreach ($case->invoices as $i)
                <tr class="border-b border-line/60 align-top">
                    <td class="py-2 pr-3 font-medium">{{ \App\Models\Invoice::KINDS[$i->kind] ?? $i->kind }}<br><span class="text-xs font-normal text-muted">{{ $i->description }}</span></td>
                    <td class="py-2 pr-3 whitespace-nowrap">{{ $i->amount() }}</td>
                    <td class="py-2 pr-3">{{ \App\Models\Invoice::STATUSES[$i->status] ?? $i->status }}@if ($i->paid_at) <span class="block text-xs text-muted">paid {{ $i->paid_at->format('j M Y') }}@if ($i->payment_reference), ref. {{ $i->payment_reference }}@endif</span>@endif</td>
                    <td class="py-2">@if ($i->refund_status === 'due')Refund of {{ $i->refundAmount() }} is being processed @elseif ($i->refund_status === 'paid')Refunded {{ $i->refundAmount() }} on {{ $i->refunded_at?->format('j M Y') }} @else &mdash; @endif</td>
                </tr>
            @endforeach
            </tbody></table></div>
        @if ($case->invoices->where('status', 'issued')->isNotEmpty())
            @include('public.legal.payment-options')
            <p class="text-sm text-muted">After you pay, tell your counsellor the reference, and they will record it here. Fees and refunds are explained in the <a class="text-navy-700 underline" href="{{ route('legal.show', 'refunds') }}">cancellation and refund policy</a>.</p>
        @endif
    </section>


    @if ($plan)
        <section class="card mt-5 p-5" aria-labelledby="docs-h">
            <h2 id="docs-h" class="font-bold">Your documents</h2>
            <p class="mt-1 text-sm text-muted">@if ($docProgress['required'] === 0) No document is mandatory for this scholarship. @elseif ($docProgress['missing']) <strong>{{ $docProgress['ready'] }} of {{ $docProgress['required'] }}</strong> mandatory documents provided. @else All {{ $docProgress['required'] }} mandatory documents are provided. @endif</p>
            <a class="btn-primary mt-3" href="{{ route('student.applications.materials', $plan) }}">{{ $docProgress['missing'] ? 'Upload my documents' : 'See my documents' }}</a>
        </section>
    @endif
    <section id="messages" class="card mt-5 p-5" aria-labelledby="msg-h">
        <h2 id="msg-h" class="font-bold">Messages with your counsellor</h2>
        <div class="mt-3 space-y-3">
            @forelse ($messages as $m)
                @php($mine = $m->author_id === $case->user_id)
                <div class="rounded-md border p-3 text-sm {{ $mine ? 'border-line bg-white' : 'border-navy-300 bg-navy-50' }}">
                    <p class="text-xs text-muted"><strong>{{ $mine ? 'You' : ($m->author?->name ?? 'SAS team') }}</strong> &middot; {{ $m->created_at->format('j M Y, H:i') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $m->body }}</p>
                </div>
            @empty
                <p class="text-sm text-muted">No messages yet. Ask your counsellor a question about this application below.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('student.cases.messages.store', $case) }}" class="mt-4">
            @csrf
            <label for="msg-body" class="label">Your message</label>
            <textarea id="msg-body" name="body" rows="3" required minlength="2" maxlength="2000" class="input">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1 text-sm text-alert" role="alert">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-muted">Do not put passwords or payment card numbers in a message. Share documents below instead.</p>
            <button class="btn-primary mt-3">Send message</button>
        </form>
    </section>

    <section id="form" class="card mt-5 p-5" aria-labelledby="form-h">
        <h2 id="form-h" class="font-bold">Let your counsellor see your details</h2>
        <p class="mt-1 text-sm text-muted">Your counsellor can only read what you share. Your <a class="text-navy-700 underline" href="{{ route('student.form.show') }}">application form</a> is {{ $formPercent }}% filled in.</p>
        <form method="POST" action="{{ route('student.cases.form.update', $case) }}" class="mt-3">
            @csrf @method('PUT')
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="share" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked($case->form_shared_at)> <span>{{ $formConsent }}</span></label>
            @if ($case->form_shared_at)<p class="mt-1 text-xs text-muted">Shared since {{ $case->form_shared_at->format('j M Y') }}.</p>@endif
            <button class="btn-primary mt-3">Save</button>
        </form>
    </section>
    <section id="share" class="card mt-5 p-5" aria-labelledby="share-h">
        <h2 id="share-h" class="font-bold">Documents shared with your counsellor</h2>
        <p class="mt-1 text-sm text-muted">Your documents are private. Tick only what your counsellor needs for this application. You can untick a document at any time and they will no longer be able to open it.</p>
        @if ($documents->isEmpty())
            <p class="mt-3 text-sm">You have no documents yet. <a class="text-navy-700 underline" href="{{ route('student.documents.index') }}">Upload documents</a> first.</p>
        @else
            <form method="POST" action="{{ route('student.cases.documents.update', $case) }}" class="mt-3">
                @csrf @method('PUT')
                <fieldset>
                    <legend class="sr-only">Documents to share</legend>
                    <ul class="divide-y divide-line">
                        @foreach ($documents as $d)
                            <li class="py-2"><label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" name="documents[]" value="{{ $d->id }}" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked(in_array($d->id, old('documents', $sharedIds)))>
                                <span><span class="font-medium">{{ $d->title }}</span> <span class="text-muted">({{ \App\Models\DocumentKind::label($d->type) }})</span>
                                    @if (! $d->currentVersion?->isDownloadable())<span class="block text-xs text-muted">Not available yet: still being checked.</span>@endif</span>
                            </label></li>
                        @endforeach
                    </ul>
                </fieldset>
                <label class="mt-3 flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-slate-300 text-navy-700" @checked(old('consent'))> <span>{{ $consent }}</span></label>
                @error('consent')<p class="mt-1 text-sm text-alert" role="alert">{{ $message }}</p>@enderror
                <button class="btn-primary mt-3">Save sharing</button>
            </form>
        @endif
    </section>
</x-layouts.student>
