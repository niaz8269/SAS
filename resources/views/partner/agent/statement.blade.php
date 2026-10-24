<x-layouts.partner title="Statement" crumb="Statement">
    <x-agent.title>Fees statement
        <x-slot name="actions"><a class="inline-flex items-center gap-2 bg-[#307cc7] px-5 py-2.5 text-[0.875rem] text-white hover:bg-[#2a6fb4]" href="{{ route('partner.statement', ['month' => $month->format('Y-m'), 'format' => 'csv']) }}">Download CSV</a></x-slot>
    </x-agent.title>
    <p class="mt-3 text-[0.8125rem] text-[#555]">The fees SAS issued for your students and the payments recorded, month by month. Only your own students are included.</p>
    <div class="mt-4 flex flex-wrap items-center gap-3 text-[0.875rem]">
        <a class="border border-[#ccc] bg-white px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('partner.statement', ['month' => $previous]) }}">&laquo; {{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $previous.'-01')->format('F Y') }}</a>
        <strong class="text-[1.125rem] font-normal text-[#3a87ad]">{{ $month->format('F Y') }}</strong>
        @unless ($isCurrent)<a class="border border-[#ccc] bg-white px-4 py-2 hover:bg-[#f0f6fa]" href="{{ route('partner.statement', ['month' => $next]) }}">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $next.'-01')->format('F Y') }} &raquo;</a>@endunless
    </div>
    <dl class="mt-4 grid gap-3 text-[0.875rem] sm:grid-cols-3">
        <div class="border border-[#ddd] bg-[#f9f9f9] p-4"><dt class="text-[#666]">Issued this month</dt><dd class="text-[1.25rem]">{{ $issued }}</dd></div>
        <div class="border border-[#ddd] bg-[#f9f9f9] p-4"><dt class="text-[#666]">Paid this month</dt><dd class="text-[1.25rem]">{{ $paid }}</dd></div>
        <div class="border border-[#ddd] bg-[#f9f9f9] p-4"><dt class="text-[#666]">Owed now (all months)</dt><dd class="text-[1.25rem]">{{ $owed }}</dd></div>
    </dl>
    <x-agent.list title="Fees" class="mt-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[0.8125rem]">
                <caption class="sr-only">Fees in {{ $month->format('F Y') }}</caption>
                <thead><tr><x-agent.th name="s" :sortable="false">Student</x-agent.th><x-agent.th name="c" :sortable="false">Case</x-agent.th><x-agent.th name="f" :sortable="false">Fee</x-agent.th><x-agent.th name="a" :sortable="false">Amount</x-agent.th><x-agent.th name="st" :sortable="false">Status</x-agent.th><x-agent.th name="i" :sortable="false">Issued</x-agent.th><x-agent.th name="p" :sortable="false">Paid</x-agent.th></tr></thead>
                <tbody>
                @forelse ($invoices as $i)
                    <tr class="border-b border-[#e5e5e5]"><td class="px-3 py-3">{{ $i->student?->name }}</td><td class="px-3 py-3">{{ $i->serviceCase?->title }}</td><td class="px-3 py-3">{{ \App\Models\Invoice::KINDS[$i->kind] ?? $i->kind }}</td><td class="px-3 py-3">{{ $i->amount() }}</td><td class="px-3 py-3">{{ $i->feeLabel() }}</td><td class="px-3 py-3">{{ $i->issued_at?->format('j M Y') }}</td><td class="px-3 py-3">{{ $i->paid_at?->format('j M Y') ?? '-' }}</td></tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-[#777]">No fees were issued or paid in {{ $month->format('F Y') }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-agent.list>
</x-layouts.partner>