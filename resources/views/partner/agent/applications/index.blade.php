<x-layouts.partner title="Application list" crumb="Application list">
    <x-agent.title>Application list
        <x-slot name="actions"><button type="button" class="inline-flex items-center gap-2 bg-[#d9534f] px-5 py-2.5 text-[14px] text-white hover:bg-[#c9453f]" onclick="document.getElementById('advanced').toggleAttribute('hidden')" aria-controls="advanced"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 5 5"/></svg>Advanced Search</button></x-slot>
    </x-agent.title>

    <form id="advanced" method="GET" action="{{ route('partner.applications.index') }}" class="mt-4 flex flex-wrap items-end gap-3 border border-[#ddd] bg-[#f9f9f9] p-4" @if (! ($filters['stage'] || $filters['from'] || $filters['to'])) hidden @endif>
        <input type="hidden" name="q" value="{{ $filters['q'] }}">
        <div><label for="stage" class="mb-1 block text-sm font-semibold">Status</label>
            <select id="stage" name="stage" class="border border-[#ccc] bg-white px-3 py-2"><option value="">All</option>@foreach (\App\Models\ApplicationPlan::STAGES as $k => $l)<option value="{{ $k }}" @selected($filters['stage'] === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="from" class="mb-1 block text-sm font-semibold">Last operation from</label><input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="border border-[#ccc] bg-white px-3 py-2"></div>
        <div><label for="to" class="mb-1 block text-sm font-semibold">to</label><input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="border border-[#ccc] bg-white px-3 py-2"></div>
        <button class="bg-[#307cc7] px-5 py-2 text-white">Search</button>
        <a class="border border-[#ccc] bg-white px-5 py-2" href="{{ route('partner.applications.index') }}">Reset</a>
    </form>

    <x-agent.list title="List">
        <x-slot name="actions"><a class="inline-flex items-center gap-2 hover:underline" href="{{ route('partner.export.applications') }}">Export CSV</a></x-slot>
        <x-agent.pager :paginator="$plans" search-label="Name/Email/Application number" :q="$filters['q']" :per-page="$perPage" />
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <caption class="sr-only">Applications of your students</caption>
                <thead><tr>
                    <x-agent.th name="id" :sort="$sort" :dir="$dir">ID</x-agent.th>
                    <x-agent.th name="application_no" :sort="$sort" :dir="$dir">Application number</x-agent.th>
                    <x-agent.th name="scholarship" :sortable="false">Scholarship</x-agent.th>
                    <x-agent.th name="name" :sort="$sort" :dir="$dir">Name</x-agent.th>
                    <x-agent.th name="gender" :sortable="false">gender</x-agent.th>
                    <x-agent.th name="country" :sortable="false">Country of Citizenship</x-agent.th>
                    <x-agent.th name="updated_at" :sort="$sort" :dir="$dir">Last operation time</x-agent.th>
                    <x-agent.th name="status" :sortable="false">Application status</x-agent.th>
                    <x-agent.th name="notes" :sortable="false">Review notes</x-agent.th>
                    <x-agent.th name="fee" :sortable="false">Application fee payment status</x-agent.th>
                    <x-agent.th name="charge" :sortable="false">Agency charge payment status</x-agent.th>
                    <x-agent.th name="operate" :sortable="false"><span class="text-[#428bb6]">operate</span></x-agent.th>
                </tr></thead>
                <tbody>
                @forelse ($plans as $p)
                    @php($data = $forms->formFor($p->user)->data ?? [])
                    @php($case = ($cases[$p->user_id.'-'.$p->cycle_id] ?? collect())->first())
                    @php($fee = fn ($kind) => $case?->invoices->where('kind', $kind)->where('status', '!=', 'void')->last()?->feeLabel() ?? '-')
                    <tr class="border-b border-[#e5e5e5] align-top">
                        <td class="px-3 py-5">{{ $p->id }}</td>
                        <td class="whitespace-nowrap px-3 py-5">{{ $p->application_no }}</td>
                        <td class="px-3 py-5">{{ $p->cycle->displayName() }}</td>
                        <td class="px-3 py-3 uppercase">{{ $p->user->name }}</td>
                        <td class="px-3 py-5">{{ $data['gender'] ?? '-' }}</td>
                        <td class="px-3 py-5">{{ $data['nationality'] ?? '-' }}</td>
                        <td class="whitespace-nowrap px-3 py-5">{{ $p->updated_at->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-3 {{ match ($p->stage) { 'passed' => 'text-[#1a1aff]', 'returned' => 'text-[#e00]', 'submitted' => 'text-[#d97706]', default => 'text-[#555]' } }}">{{ $p->statusFor($case) }}</td>
                        <td class="max-w-[16rem] whitespace-pre-line px-3 py-5">{{ $p->review_remarks }}</td>
                        <td class="px-3 py-5">{{ $fee('application_fee') }}</td>
                        <td class="px-3 py-5">{{ $fee('agency_charge') }}</td>
                        <td class="px-3 py-3 text-[#428bb6]"><a class="block hover:underline" href="{{ route('partner.student.applications.show', [$p->user->public_id, $p->public_id]) }}">{{ $p->isEditable() ? 'Continue' : 'View Details' }}</a>
                            <a class="block hover:underline" href="{{ route('partner.student.applications.download', [$p->user->public_id, $p->public_id]) }}">Download Application Form</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="px-4 py-10 text-center text-[#777]">No applications match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pb-2"></div>
    </x-agent.list>
</x-layouts.partner>
