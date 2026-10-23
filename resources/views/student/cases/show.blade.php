@php
    $steps = ['new' => 'Opened', 'in_progress' => 'In progress', 'submitted' => 'Submitted', 'accepted' => 'Accepted'];
    $order = array_keys($steps);
    $at = array_search($case->status === 'rejected' ? 'submitted' : $case->status, $order, true); // a rejected case went through every step up to submission
    $ended = in_array($case->status, ['rejected', 'withdrawn'], true);
@endphp
<x-layouts.student :title="$case->title">
    <p class="text-sm"><a class="text-navy-700 underline" href="{{ route('student.cases.index') }}">&larr; Cases and fees</a></p>
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
</x-layouts.student>